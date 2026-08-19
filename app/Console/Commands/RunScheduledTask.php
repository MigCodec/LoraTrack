<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ScheduledCommandStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunScheduledTask extends Command
{
    protected $signature = 'loratrack:run-scheduled {task : Identificador configurado de la tarea}';

    protected $description = 'Ejecuta una tarea programada y conserva su último resultado operacional.';

    public function handle(): int
    {
        $task = (string) $this->argument('task');
        $definition = config("scheduled-tasks.{$task}");
        if (! is_array($definition) || ! is_string($definition['command'] ?? null)) {
            $this->error("La tarea programada {$task} no está configurada.");

            return self::INVALID;
        }

        $startedAt = now();
        $startedAtMonotonic = hrtime(true);
        ScheduledCommandStatus::query()->updateOrCreate(
            ['task' => $task],
            [
                'last_started_at' => $startedAt,
                'run_requested_at' => null,
                'last_finished_at' => null,
                'last_exit_code' => null,
                'last_error' => null,
            ],
        );

        try {
            $exitCode = Artisan::call($definition['command'], $definition['arguments'] ?? []);
            $this->output->write(Artisan::output());
            $finishedAt = now();
            $attributes = [
                'last_finished_at' => $finishedAt,
                'last_duration_ms' => (int) round((hrtime(true) - $startedAtMonotonic) / 1_000_000),
                'last_exit_code' => $exitCode,
                'last_error' => $exitCode === self::SUCCESS ? null : "El comando terminó con código {$exitCode}.",
                'run_count' => DB::raw('run_count + 1'),
            ];
            $attributes[$exitCode === self::SUCCESS ? 'last_succeeded_at' : 'last_failed_at'] = $finishedAt;
            ScheduledCommandStatus::query()->whereKey($task)->update($attributes);

            return $exitCode;
        } catch (Throwable $exception) {
            ScheduledCommandStatus::query()->whereKey($task)->update([
                'last_finished_at' => now(),
                'last_failed_at' => now(),
                'last_duration_ms' => (int) round((hrtime(true) - $startedAtMonotonic) / 1_000_000),
                'last_exit_code' => self::FAILURE,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                'run_count' => DB::raw('run_count + 1'),
            ]);

            throw $exception;
        }
    }
}
