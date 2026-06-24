<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Models\TelemetryEvent;
use Illuminate\Support\Facades\DB;

class MerakiEventRetention
{
    public const HISTORY_LIMIT = 10;

    public function prune(TelemetryEvent $event, int $limit = 1000): int
    {
        if (! $event->device_id || $event->event_type !== 'meraki_location') {
            return 0;
        }

        $obsoleteIds = TelemetryEvent::query()
            ->where('organization_id', $event->organization_id)
            ->where('connector_id', $event->connector_id)
            ->where('device_id', $event->device_id)
            ->where('event_type', 'meraki_location')
            ->orderByDesc('observed_at')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->skip(self::HISTORY_LIMIT)
            ->take(max(1, min(1000, $limit)))
            ->pluck('id');

        if ($obsoleteIds->isEmpty()) {
            return 0;
        }

        return TelemetryEvent::query()
            ->where('organization_id', $event->organization_id)
            ->whereIn('id', $obsoleteIds)
            ->delete();
    }

    public function pruneAll(int $maxDeletes = 10000): int
    {
        $deleted = 0;
        foreach ($this->oversizedGroups() as $group) {
            if ($deleted >= $maxDeletes) {
                break;
            }
            $latest = TelemetryEvent::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $group->organization_id)
                ->where('connector_id', $group->connector_id)
                ->where('device_id', $group->device_id)
                ->where('event_type', 'meraki_location')
                ->latest('observed_at')
                ->latest('received_at')
                ->first();
            if (! $latest) {
                continue;
            }

            do {
                $remaining = $maxDeletes - $deleted;
                $batchDeleted = $this->prune($latest, min(1000, $remaining));
                $deleted += $batchDeleted;
            } while ($batchDeleted > 0 && $deleted < $maxDeletes);
        }

        return $deleted;
    }

    public function excessCount(): int
    {
        return $this->oversizedGroups()
            ->sum(fn (object $group): int => max(0, (int) $group->event_count - self::HISTORY_LIMIT));
    }

    private function oversizedGroups()
    {
        return DB::table('telemetry_events')
            ->select(['organization_id', 'connector_id', 'device_id'])
            ->selectRaw('COUNT(*) AS event_count')
            ->where('event_type', 'meraki_location')
            ->whereNotNull('device_id')
            ->groupBy(['organization_id', 'connector_id', 'device_id'])
            ->havingRaw('COUNT(*) > ?', [self::HISTORY_LIMIT])
            ->get();
    }
}
