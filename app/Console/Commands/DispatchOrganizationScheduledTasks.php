<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationScheduledTask;
use App\Scheduling\OrganizationTaskScheduler;
use Illuminate\Console\Command;
use Throwable;

class DispatchOrganizationScheduledTasks extends Command
{
    protected $signature = 'loratrack:dispatch-scheduled-tasks';

    protected $description = 'Ejecuta las tareas vencidas según la frecuencia configurada por organización.';

    public function handle(OrganizationTaskScheduler $scheduler): int
    {
        foreach (Organization::query()->where('active', true)->get() as $organization) {
            $scheduler->synchronize($organization);
        }

        $dueTasks = OrganizationScheduledTask::query()
            ->withoutGlobalScopes()
            ->with('organization')
            ->where('enabled', true)
            ->whereHas('organization', fn ($query) => $query->where('active', true))
            ->where(fn ($query) => $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
            ->orderBy('next_run_at')
            ->get()
            ->sortBy(fn (OrganizationScheduledTask $task): int => array_search($task->task, [
                'process-meraki-webhooks',
                'process-meraki-observations',
                'process-tti-uplinks',
                'process-mqtt-telemetry',
                'process-catalog-syncs',
                'sync-telemetry-counters',
                'evaluate-alerts',
                'manage-telemetry-storage',
                'prune-meraki-history',
            ], true) ?: 0)
            ->values();

        $succeeded = 0;
        $failed = 0;
        foreach ($dueTasks as $task) {
            try {
                $scheduler->run($task);
                if ($task->fresh()->last_exit_code === 0) {
                    $succeeded++;
                } else {
                    $failed++;
                }
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
            }
        }

        $this->info("Tareas ejecutadas: {$dueTasks->count()}; correctas: {$succeeded}; fallidas: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
