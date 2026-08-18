<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\Organization;
use App\Models\OrganizationScheduledTask;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

class OrganizationTaskScheduler
{
    /** @return Collection<int, OrganizationScheduledTask> */
    public function synchronize(Organization $organization): Collection
    {
        foreach (config('scheduled-tasks', []) as $task => $definition) {
            OrganizationScheduledTask::query()->withoutGlobalScopes()->firstOrCreate(
                ['organization_id' => $organization->id, 'task' => $task],
                [
                    'enabled' => true,
                    'interval_minutes' => (int) $definition['default_interval'],
                    'next_run_at' => now(),
                ],
            );
        }

        return OrganizationScheduledTask::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('task', array_keys(config('scheduled-tasks', [])))
            ->orderBy('task')
            ->get();
    }

    public function run(OrganizationScheduledTask $scheduledTask): int
    {
        $definition = config("scheduled-tasks.{$scheduledTask->task}");
        if (! is_array($definition) || ! is_string($definition['command'] ?? null)) {
            throw new \InvalidArgumentException('La tarea solicitada no está registrada.');
        }

        $lock = Cache::lock("scheduled-task:{$scheduledTask->organization_id}:{$scheduledTask->task}", 3600);
        if (! $lock->get()) {
            throw new \RuntimeException('La tarea ya se está ejecutando.');
        }

        $context = app(OrganizationContext::class);
        $previousOrganization = $context->organization();
        $startedAt = hrtime(true);
        $scheduledTask->forceFill([
            'last_started_at' => now(),
            'last_finished_at' => null,
            'last_exit_code' => null,
            'last_error' => null,
        ])->save();

        try {
            $context->set($scheduledTask->organization()->firstOrFail());
            $exitCode = Artisan::call($definition['command'], $definition['arguments'] ?? []);
            $finishedAt = now();
            $scheduledTask->forceFill([
                'last_finished_at' => $finishedAt,
                'last_succeeded_at' => $exitCode === 0 ? $finishedAt : $scheduledTask->last_succeeded_at,
                'last_failed_at' => $exitCode === 0 ? $scheduledTask->last_failed_at : $finishedAt,
                'last_duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'last_exit_code' => $exitCode,
                'last_error' => $exitCode === 0 ? null : mb_substr(trim(Artisan::output()) ?: "Código de salida {$exitCode}", 0, 1000),
                'run_count' => $scheduledTask->run_count + 1,
                'next_run_at' => $scheduledTask->enabled
                    ? $finishedAt->copy()->addMinutes($scheduledTask->interval_minutes)
                    : null,
            ])->save();

            return $exitCode;
        } catch (Throwable $exception) {
            $finishedAt = now();
            $scheduledTask->forceFill([
                'last_finished_at' => $finishedAt,
                'last_failed_at' => $finishedAt,
                'last_duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'last_exit_code' => 1,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                'run_count' => $scheduledTask->run_count + 1,
                'next_run_at' => $scheduledTask->enabled
                    ? $finishedAt->copy()->addMinutes($scheduledTask->interval_minutes)
                    : null,
            ])->save();

            throw $exception;
        } finally {
            $context->set($previousOrganization);
            $lock->release();
        }
    }
}
