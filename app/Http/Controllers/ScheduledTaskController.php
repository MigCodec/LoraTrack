<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganizationScheduledTask;
use App\Scheduling\OrganizationTaskScheduler;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ScheduledTaskController extends Controller
{
    public function update(Request $request, string $task, OrganizationContext $context, OrganizationTaskScheduler $scheduler): RedirectResponse
    {
        $organization = $context->organization();
        abort_unless($organization, 404);
        $definition = config("scheduled-tasks.{$task}");
        abort_unless(is_array($definition), 404);

        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'interval_minutes' => [
                'required', 'integer',
                'min:'.(int) $definition['minimum_interval'],
                'max:'.(int) $definition['maximum_interval'],
            ],
        ]);
        $scheduledTask = $scheduler->synchronize($organization)->firstWhere('task', $task);
        abort_unless($scheduledTask, 404);
        $scheduledTask->forceFill([
            'enabled' => $request->boolean('enabled'),
            'interval_minutes' => (int) $validated['interval_minutes'],
            'next_run_at' => $request->boolean('enabled')
                ? now()->addMinutes((int) $validated['interval_minutes'])
                : null,
        ])->save();

        return back()->with('status', "Programación de {$definition['label']} actualizada.");
    }

    public function run(string $task, OrganizationContext $context, OrganizationTaskScheduler $scheduler): RedirectResponse
    {
        $organization = $context->organization();
        abort_unless($organization && is_array(config("scheduled-tasks.{$task}")), 404);
        $scheduledTask = $scheduler->synchronize($organization)->firstWhere('task', $task);
        abort_unless($scheduledTask instanceof OrganizationScheduledTask, 404);

        try {
            $exitCode = $scheduler->run($scheduledTask);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['scheduler' => 'La ejecución falló: '.mb_substr($exception->getMessage(), 0, 300)]);
        }

        return $exitCode === 0
            ? back()->with('status', config("scheduled-tasks.{$task}.label").' finalizó correctamente.')
            : back()->withErrors(['scheduler' => config("scheduled-tasks.{$task}.label").' terminó con errores.']);
    }
}
