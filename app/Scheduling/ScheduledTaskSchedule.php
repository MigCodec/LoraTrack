<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\ScheduledCommandStatus;
use App\Models\SchedulerSetting;

class ScheduledTaskSchedule
{
    public function intervalMinutes(string $task, ?ScheduledCommandStatus $status = null): int
    {
        $default = max(1, (int) config("scheduled-tasks.{$task}.interval_minutes", 1));
        if ($this->usesSystemRecommended()) {
            return $default;
        }

        $configured = $status?->interval_minutes
            ?? ScheduledCommandStatus::query()->whereKey($task)->value('interval_minutes');

        return $configured === null ? $default : max(1, (int) $configured);
    }

    public function isDue(string $task): bool
    {
        $status = ScheduledCommandStatus::query()->find($task);
        if (! $status?->last_started_at) {
            return true;
        }

        return $status->last_started_at->lte(now()->subMinutes($this->intervalMinutes($task, $status)));
    }

    public function usesSystemRecommended(): bool
    {
        return (bool) (SchedulerSetting::query()->whereKey(1)->value('use_system_recommended') ?? true);
    }

    public function label(int $minutes): string
    {
        if ($minutes === 1) {
            return 'Cada minuto';
        }

        if ($minutes % 1440 === 0) {
            $days = intdiv($minutes, 1440);

            return $days === 1 ? 'Cada día' : "Cada {$days} días";
        }

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? 'Cada hora' : "Cada {$hours} horas";
        }

        return "Cada {$minutes} minutos";
    }
}
