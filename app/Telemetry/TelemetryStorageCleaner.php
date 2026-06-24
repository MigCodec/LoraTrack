<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Models\Organization;
use App\Models\TelemetryEvent;
use Illuminate\Support\Carbon;

class TelemetryStorageCleaner
{
    public const BATCH_SIZE = 1000;

    public function deleteOldestBatch(Organization $organization): int
    {
        $cutoff = now()->subDays(max(7, (int) $organization->telemetry_retention_days));
        $ids = TelemetryEvent::query()
            ->where('organization_id', $organization->id)
            ->where('received_at', '<', $cutoff)
            ->orderBy('received_at')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return TelemetryEvent::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->delete();
    }

    public function cutoff(Organization $organization): Carbon
    {
        return now()->subDays(max(7, (int) $organization->telemetry_retention_days));
    }
}
