<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAutomationSettingsRequest;
use App\Models\ScheduledCommandStatus;
use App\Models\SchedulerSetting;
use App\Scheduling\ScheduledTaskSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AutomationSettingsController extends Controller
{
    public function index(ScheduledTaskSchedule $schedule): View
    {
        $statuses = ScheduledCommandStatus::query()->get()->keyBy('task');
        $recommendedMode = $schedule->usesSystemRecommended();

        $tasks = collect(config('scheduled-tasks'))->map(function (array $definition, string $task) use ($statuses, $schedule): array {
            $status = $statuses->get($task);
            $interval = $schedule->intervalMinutes($task, $status);
            $isRunning = $status?->last_started_at
                && (! $status->last_finished_at || $status->last_started_at->gt($status->last_finished_at));
            $hasFailed = $status?->last_failed_at
                && (! $status->last_succeeded_at || $status->last_failed_at->gt($status->last_succeeded_at));

            return [
                'task' => $task,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'recommended_minutes' => (int) $definition['interval_minutes'],
                'manual_minutes' => (int) ($status?->interval_minutes ?? $definition['interval_minutes']),
                'effective_minutes' => $interval,
                'frequency' => $schedule->label($interval),
                'command' => trim($definition['command'].' '.collect($definition['arguments'] ?? [])
                    ->map(fn (mixed $value, string|int $key): string => is_int($key) ? (string) $value : "{$key}={$value}")
                    ->implode(' ')),
                'status' => $status,
                'next_run_at' => $status?->last_started_at?->copy()->addMinutes($interval),
                'state' => $isRunning ? 'running' : ($hasFailed ? 'failed' : ($status?->last_started_at ? 'healthy' : 'never')),
            ];
        })->values();

        return view('settings.automation', compact('tasks', 'recommendedMode'));
    }

    public function update(UpdateAutomationSettingsRequest $request): RedirectResponse
    {
        $recommendedMode = $request->boolean('use_system_recommended');

        DB::transaction(function () use ($request, $recommendedMode): void {
            SchedulerSetting::query()->updateOrCreate(['id' => 1], ['use_system_recommended' => $recommendedMode]);

            if (! $recommendedMode) {
                foreach (array_keys(config('scheduled-tasks')) as $task) {
                    ScheduledCommandStatus::query()->updateOrCreate(
                        ['task' => $task],
                        ['interval_minutes' => $request->integer("intervals.{$task}")],
                    );
                }
            }
        });

        return back()->with('status', $recommendedMode
            ? 'Modo recomendado activado. Se aplicaron los intervalos seguros del sistema.'
            : 'Intervalos de automatización actualizados.');
    }

    public function run(Request $request, string $task): RedirectResponse|JsonResponse
    {
        abort_unless(is_array(config("scheduled-tasks.{$task}")), 404);

        $runCountBefore = (int) (ScheduledCommandStatus::query()->whereKey($task)->value('run_count') ?? 0);
        try {
            $exitCode = Artisan::call('loratrack:run-scheduled', ['task' => $task]);
        } catch (Throwable) {
            $exitCode = 1;
        }
        $status = ScheduledCommandStatus::query()->find($task);
        $didRun = (int) ($status?->run_count ?? 0) > $runCountBefore;
        $successful = $didRun && $exitCode === 0;

        if ($request->expectsJson()) {
            return response()->json([
                'completed' => $didRun,
                'successful' => $successful,
                'message' => $didRun
                    ? ($successful ? 'Ejecución completada correctamente.' : 'La ejecución terminó con errores.')
                    : 'La tarea ya está siendo ejecutada por otro proceso.',
                'run_count' => (int) ($status?->run_count ?? 0),
                'duration_ms' => $status?->last_duration_ms,
                'exit_code' => $status?->last_exit_code,
                'last_started_at' => $status?->last_started_at?->toIso8601String(),
                'last_finished_at' => $status?->last_finished_at?->toIso8601String(),
            ], $didRun ? ($successful ? 200 : 422) : 409);
        }

        return $successful
            ? back()->with('status', 'Ejecución completada correctamente.')
            : back()->withErrors(['automation' => $didRun ? 'La ejecución terminó con errores.' : 'La tarea ya está en ejecución.']);
    }
}
