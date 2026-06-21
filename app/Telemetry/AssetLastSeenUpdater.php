<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Models\Asset;
use App\Models\AssetDeviceAssignment;
use App\Models\TelemetryEvent;
use App\Positioning\BleObservationExtractor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AssetLastSeenUpdater
{
    public function updateFromUplink(TelemetryEvent $event): void
    {
        $seenAt = $event->observed_at ?? $event->received_at;
        $assetIds = collect();

        if ($event->device_id) {
            $assetIds->push(...$this->assignmentsAt($seenAt)
                ->where('device_id', $event->device_id)
                ->pluck('asset_id'));
        }

        $observedMacs = $event->signalObservations
            ->pluck('transmitter_mac')
            ->map(fn (string $mac): string => BleObservationExtractor::normalizeMac($mac))
            ->filter()
            ->unique();

        if ($observedMacs->isNotEmpty()) {
            $beaconAssetIds = $this->assignmentsAt($seenAt)
                ->with('device:id,identifier')
                ->where('tracking_strategy', 'mobile_beacon_fixed_scanners')
                ->get()
                ->filter(fn (AssetDeviceAssignment $assignment): bool => $observedMacs->contains(
                    BleObservationExtractor::normalizeMac($assignment->device->identifier),
                ))
                ->pluck('asset_id');
            $assetIds->push(...$beaconAssetIds);
        }

        $this->advanceLastSeen($assetIds->filter()->unique()->values(), $seenAt);
    }

    private function assignmentsAt(Carbon $seenAt): Builder
    {
        return AssetDeviceAssignment::query()
            ->where('started_at', '<=', $seenAt)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('ended_at')
                ->orWhere('ended_at', '>=', $seenAt));
    }

    /** @param Collection<int, string> $assetIds */
    private function advanceLastSeen(Collection $assetIds, Carbon $seenAt): void
    {
        if ($assetIds->isEmpty()) {
            return;
        }

        Asset::query()
            ->whereIn('id', $assetIds)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', $seenAt))
            ->update(['last_seen_at' => $seenAt, 'updated_at' => now()]);
    }
}
