<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Models\TelemetryEvent;
use Illuminate\Support\Facades\DB;

class MerakiEventRetention
{
    public const HISTORY_LIMIT = 10;

    public function prune(TelemetryEvent $event): int
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
            ->take(1000)
            ->pluck('id');

        if ($obsoleteIds->isEmpty()) {
            return 0;
        }

        return TelemetryEvent::query()
            ->where('organization_id', $event->organization_id)
            ->whereIn('id', $obsoleteIds)
            ->delete();
    }

    public function pruneAll(): int
    {
        $deleted = 0;
        $groups = DB::table('telemetry_events')
            ->select(['organization_id', 'connector_id', 'device_id'])
            ->where('event_type', 'meraki_location')
            ->whereNotNull('device_id')
            ->groupBy(['organization_id', 'connector_id', 'device_id'])
            ->havingRaw('COUNT(*) > ?', [self::HISTORY_LIMIT])
            ->get();

        foreach ($groups as $group) {
            $latest = TelemetryEvent::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $group->organization_id)
                ->where('connector_id', $group->connector_id)
                ->where('device_id', $group->device_id)
                ->where('event_type', 'meraki_location')
                ->latest('observed_at')
                ->latest('received_at')
                ->first();
            if ($latest) {
                do {
                    $batchDeleted = $this->prune($latest);
                    $deleted += $batchDeleted;
                } while ($batchDeleted === 1000);
            }
        }

        return $deleted;
    }
}
