<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Models\TelemetryEvent;
use Illuminate\Support\Carbon;

class MerakiEventRetention
{
    public const RETENTION_DAYS = 6;

    public function prune(TelemetryEvent $event, int $limit = 1000): int
    {
        if (! $event->device_id || $event->event_type !== 'meraki_location') {
            return 0;
        }

        return $this->deleteStaleQuery($this->cutoff(), $limit)
            ->where('organization_id', $event->organization_id)
            ->where('connector_id', $event->connector_id)
            ->where('device_id', $event->device_id)
            ->whereKeyNot($event->id)
            ->delete();
    }

    public function pruneAll(int $maxDeletes = 10000): int
    {
        $deleted = 0;
        $cutoff = $this->cutoff();
        do {
            $remaining = $maxDeletes - $deleted;
            if ($remaining <= 0) {
                break;
            }

            $batchDeleted = $this->deleteStaleQuery($cutoff, min(1000, $remaining))->delete();
            $deleted += $batchDeleted;
        } while ($batchDeleted > 0 && $deleted < $maxDeletes);

        return $deleted;
    }

    public function staleCount(): int
    {
        return $this->staleQuery($this->cutoff())->count();
    }

    public function cutoff(): Carbon
    {
        return now()->subDays(self::RETENTION_DAYS);
    }

    private function deleteStaleQuery(Carbon $cutoff, int $limit)
    {
        $ids = $this->staleQuery($cutoff)
            ->orderBy('observed_at')
            ->orderBy('received_at')
            ->orderBy('id')
            ->limit(max(1, min(1000, $limit)))
            ->pluck('id');

        return TelemetryEvent::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids);
    }

    private function staleQuery(Carbon $cutoff)
    {
        return TelemetryEvent::query()
            ->withoutGlobalScopes()
            ->where('event_type', 'meraki_location')
            ->where(function ($query) use ($cutoff): void {
                $query->where('observed_at', '<', $cutoff)
                    ->orWhere(function ($receivedQuery) use ($cutoff): void {
                        $receivedQuery->whereNull('observed_at')
                            ->where('received_at', '<', $cutoff);
                    });
            });
    }
}
