<?php

declare(strict_types=1);

namespace App\Positioning;

use App\Models\Asset;
use App\Models\AssetDeviceAssignment;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\PositionEstimate;
use App\Models\SignalObservation;
use App\Models\TelemetryEvent;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TelemetryPositioningService
{
    public function __construct(
        private readonly RssiMultilateration $multilateration,
        private readonly ZoneClassifier $zones,
    ) {}

    public function positionEvent(TelemetryEvent $event): void
    {
        $event->loadMissing(['device', 'signalObservations']);
        if (! $event->device || $event->signalObservations->isEmpty()) {
            return;
        }

        $this->positionMobileTracker($event);
        $this->positionMobileBeacons($event);
    }

    public function repositionLatestForAsset(Asset $asset): bool
    {
        $assignment = AssetDeviceAssignment::query()
            ->where('asset_id', $asset->id)
            ->where('tracking_strategy', 'fixed_beacons_mobile_tracker')
            ->whereNull('ended_at')
            ->first();
        if (! $assignment) {
            return false;
        }

        $event = TelemetryEvent::query()
            ->where('device_id', $assignment->device_id)
            ->whereHas('signalObservations')
            ->latest('observed_at')
            ->latest('received_at')
            ->first();
        if (! $event) {
            return false;
        }

        $this->positionEvent($event);

        return PositionEstimate::query()
            ->where('asset_id', $asset->id)
            ->where('telemetry_event_id', $event->id)
            ->exists();
    }

    private function positionMobileTracker(TelemetryEvent $event): void
    {
        $assignment = AssetDeviceAssignment::query()
            ->where('device_id', $event->device_id)
            ->where('tracking_strategy', 'fixed_beacons_mobile_tracker')
            ->whereNull('ended_at')
            ->first();
        if (! $assignment) {
            return;
        }

        $signals = $event->signalObservations->keyBy('transmitter_mac');
        $installations = $this->activeInstallations('beacon')->filter(
            fn (DeviceInstallation $installation): bool => $signals->has(
                BleObservationExtractor::normalizeMac($installation->device->identifier),
            ),
        );

        $this->calculateAndPersist($assignment->asset, $event, $installations, function (DeviceInstallation $installation) use ($signals): int {
            return (int) $signals[BleObservationExtractor::normalizeMac($installation->device->identifier)]->rssi;
        });
    }

    private function positionMobileBeacons(TelemetryEvent $event): void
    {
        $assignments = AssetDeviceAssignment::query()
            ->with(['asset', 'device'])
            ->where('tracking_strategy', 'mobile_beacon_fixed_scanners')
            ->whereNull('ended_at')
            ->get()
            ->keyBy(fn (AssetDeviceAssignment $assignment): string => BleObservationExtractor::normalizeMac($assignment->device->identifier));

        foreach ($event->signalObservations as $currentSignal) {
            $assignment = $assignments->get($currentSignal->transmitter_mac);
            if (! $assignment) {
                continue;
            }

            $from = ($event->observed_at ?? $event->received_at)->copy()->subSeconds(15);
            $signals = SignalObservation::query()
                ->where('transmitter_mac', $currentSignal->transmitter_mac)
                ->whereBetween('observed_at', [$from, $event->observed_at ?? $event->received_at])
                ->latest('observed_at')
                ->get()
                ->unique('receiver_identifier')
                ->keyBy('receiver_identifier');
            $installations = $this->activeInstallations('scanner')->filter(
                fn (DeviceInstallation $installation): bool => $signals->has(
                    BleObservationExtractor::normalizeMac($installation->device->identifier),
                ),
            );

            $this->calculateAndPersist($assignment->asset, $event, $installations, function (DeviceInstallation $installation) use ($signals): int {
                return (int) $signals[BleObservationExtractor::normalizeMac($installation->device->identifier)]->rssi;
            });
        }
    }

    /** @return Collection<int, DeviceInstallation> */
    private function activeInstallations(string $deviceType): Collection
    {
        return DeviceInstallation::query()
            ->with('device')
            ->whereHas('device', fn ($query) => $query->where('type', $deviceType)->where('status', 'active'))
            ->whereNull('ended_at')
            ->whereNotNull('x')
            ->whereNotNull('y')
            ->get();
    }

    /**
     * @param  Collection<int, DeviceInstallation>  $installations
     * @param  callable(DeviceInstallation): int  $rssiFor
     */
    private function calculateAndPersist(Asset $asset, TelemetryEvent $event, Collection $installations, callable $rssiFor): void
    {
        $planGroup = $installations
            ->whereNotNull('floor_plan_id')
            ->groupBy('floor_plan_id')
            ->sortByDesc(fn (Collection $group): int => $group->count())
            ->first();
        if (! $planGroup instanceof Collection || $planGroup->count() < 3) {
            return;
        }

        $measurements = $planGroup->map(fn (DeviceInstallation $installation): AnchorMeasurement => new AnchorMeasurement(
            identifier: $installation->device->identifier,
            x: (float) $installation->x,
            y: (float) $installation->y,
            rssi: $rssiFor($installation),
            referenceRssi: (int) $installation->reference_rssi,
            pathLossExponent: (float) $installation->path_loss_exponent,
        ))->values()->all();

        try {
            $result = $this->multilateration->calculate($measurements);
        } catch (InvalidArgumentException) {
            return;
        }

        $floorPlan = FloorPlan::query()->with('zones')->find($planGroup->first()->floor_plan_id);
        $zone = $floorPlan ? $this->zones->classify($floorPlan, $result->x, $result->y) : null;

        PositionEstimate::query()->updateOrCreate(
            ['asset_id' => $asset->id, 'telemetry_event_id' => $event->id],
            [
                'location_id' => $planGroup->first()->location_id,
                'floor_plan_id' => $floorPlan?->id,
                'zone_id' => $zone?->id,
                'algorithm' => 'rssi_multilateration',
                'algorithm_version' => '1.0',
                'x' => $result->x,
                'y' => $result->y,
                'confidence' => $result->confidence,
                'accuracy_meters' => $result->accuracyMeters,
                'calculated_at' => now(),
                'evidence' => $result->evidence,
            ],
        );

        $asset->forceFill(['location_id' => $planGroup->first()->location_id])->save();
    }
}
