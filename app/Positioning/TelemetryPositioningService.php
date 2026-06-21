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
    private int $repositionEventsChecked = 0;

    private int $repositionBestAnchorCount = 0;

    private ?string $repositionFailureOverride = null;

    public function __construct(
        private readonly RssiMultilateration $multilateration,
        private readonly KalmanPositionFilter $kalman,
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
        $this->repositionEventsChecked = 0;
        $this->repositionBestAnchorCount = 0;
        $this->repositionFailureOverride = null;
        $assignment = AssetDeviceAssignment::query()
            ->where('asset_id', $asset->id)
            ->where('tracking_strategy', 'fixed_beacons_mobile_tracker')
            ->whereNull('ended_at')
            ->first();
        if (! $assignment) {
            $this->repositionFailureOverride = 'El activo no tiene un tracker LoRaWAN activo asignado.';

            return false;
        }

        $events = TelemetryEvent::query()
            ->where('device_id', $assignment->device_id)
            ->has('signalObservations', '>=', 3)
            ->latest('received_at')
            ->lazy(100);
        foreach ($events as $event) {
            if ($this->tryPositionEvent($asset, $assignment, $event)) {
                return true;
            }
        }

        $assignment->loadMissing('device');
        $identifier = $assignment->device->identifier;
        $fallbackEvents = TelemetryEvent::query()
            ->where(fn ($query) => $query->whereNull('device_id')->orWhere('device_id', '!=', $assignment->device_id))
            ->has('signalObservations', '>=', 3)
            ->latest('received_at')
            ->lazy(100);
        foreach ($fallbackEvents as $event) {
            if ($this->eventMatchesDevice($event, $identifier) && $this->tryPositionEvent($asset, $assignment, $event)) {
                return true;
            }
        }

        return false;
    }

    public function repositionFailureReason(): string
    {
        if ($this->repositionFailureOverride) {
            return $this->repositionFailureOverride;
        }
        if ($this->repositionEventsChecked === 0) {
            return 'No se encontraron uplinks históricos del tracker con al menos 3 observaciones BLE.';
        }

        return "Se revisaron {$this->repositionEventsChecked} uplinks; el máximo fue {$this->repositionBestAnchorCount} MAC coincidentes con beacons instalados en un mismo plano (se requieren 3).";
    }

    private function tryPositionEvent(Asset $asset, AssetDeviceAssignment $assignment, TelemetryEvent $event): bool
    {
        $this->repositionEventsChecked++;
        $this->repositionBestAnchorCount = max(
            $this->repositionBestAnchorCount,
            $this->positionMobileTrackerForAssignment($event, $assignment),
        );

        return PositionEstimate::query()
            ->where('asset_id', $asset->id)
            ->where('telemetry_event_id', $event->id)
            ->exists();
    }

    private function eventMatchesDevice(TelemetryEvent $event, string $identifier): bool
    {
        $candidates = array_filter([
            $event->device?->identifier,
            data_get($event->normalized_payload, 'device_identifier'),
            data_get($event->raw_payload, 'end_device_ids.dev_eui'),
            data_get($event->raw_payload, 'end_device_ids.device_id'),
        ], 'is_string');
        $plain = mb_strtoupper(trim($identifier));
        $hex = BleObservationExtractor::normalizeMac($identifier);

        foreach ($candidates as $candidate) {
            if (mb_strtoupper(trim($candidate)) === $plain) {
                return true;
            }
            if ($hex !== '' && BleObservationExtractor::normalizeMac($candidate) === $hex) {
                return true;
            }
        }

        return false;
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

        $this->positionMobileTrackerForAssignment($event, $assignment);
    }

    private function positionMobileTrackerForAssignment(TelemetryEvent $event, AssetDeviceAssignment $assignment): int
    {
        $event->loadMissing('signalObservations');

        $signals = $event->signalObservations->keyBy('transmitter_mac');
        $installations = $this->activeInstallations('beacon')->filter(
            fn (DeviceInstallation $installation): bool => $signals->has(
                BleObservationExtractor::normalizeMac($installation->device->identifier),
            ),
        );
        $bestAnchorCount = (int) ($installations->groupBy('floor_plan_id')->map->count()->max() ?? 0);

        $this->calculateAndPersist($assignment->asset, $event, $installations, function (DeviceInstallation $installation) use ($signals): int {
            return (int) $signals[BleObservationExtractor::normalizeMac($installation->device->identifier)]->rssi;
        });

        return $bestAnchorCount;
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
        $previousEstimate = PositionEstimate::query()
            ->where('asset_id', $asset->id)
            ->where('floor_plan_id', $floorPlan?->id)
            ->where('telemetry_event_id', '!=', $event->id)
            ->latest('calculated_at')
            ->first();
        $filtered = $this->kalman->filter(
            $result->x,
            $result->y,
            $result->accuracyMeters,
            $event->observed_at ?? $event->received_at,
            $previousEstimate?->filter_state,
        );
        $zone = $floorPlan ? $this->zones->classify($floorPlan, $filtered->x, $filtered->y) : null;

        PositionEstimate::query()->updateOrCreate(
            ['asset_id' => $asset->id, 'telemetry_event_id' => $event->id],
            [
                'location_id' => $planGroup->first()->location_id,
                'floor_plan_id' => $floorPlan?->id,
                'zone_id' => $zone?->id,
                'algorithm' => 'rssi_multilateration_kalman',
                'algorithm_version' => '1.0',
                'x' => $filtered->x,
                'y' => $filtered->y,
                'raw_x' => $result->x,
                'raw_y' => $result->y,
                'confidence' => $result->confidence,
                'accuracy_meters' => $filtered->accuracyMeters,
                'calculated_at' => now(),
                'evidence' => $result->evidence,
                'filter_state' => $filtered->state,
            ],
        );

        $asset->forceFill(['location_id' => $planGroup->first()->location_id])->save();
    }
}
