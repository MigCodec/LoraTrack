<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Connectors\Meraki\MerakiAccessPointRegistrar;
use App\Connectors\Meraki\MerakiEventRetention;
use App\Enums\ConnectorStatus;
use App\Models\AssetDeviceAssignment;
use App\Models\Device;
use App\Models\MerakiFloorPlanMapping;
use App\Models\PositionEstimate;
use App\Models\SignalObservation;
use App\Models\TelemetryEvent;
use App\Positioning\BleObservationExtractor;
use App\Positioning\ZoneClassifier;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessMerakiLocationObservation
{
    public function __construct(public readonly string $telemetryEventId) {}

    public function handle(
        ZoneClassifier $zones,
        MerakiEventRetention $retention,
        MerakiAccessPointRegistrar $accessPoints,
    ): void {
        $event = TelemetryEvent::query()->findOrFail($this->telemetryEventId);
        if (! $event->organization?->active) {
            throw new \RuntimeException('La organización del evento Meraki no está activa.');
        }
        $context = app(OrganizationContext::class);
        $context->set($event->organization);

        try {
            if ($event->processing_status === 'processed') {
                return;
            }
            if ($event->connector?->status === ConnectorStatus::Disabled) {
                $event->forceFill([
                    'processing_status' => 'ignored',
                    'processed_at' => now(),
                    'processing_error' => 'Conector desactivado; telemetría no procesada.',
                ])->saveQuietly();

                return;
            }

            $record = $event->raw_payload;
            $clientMac = BleObservationExtractor::normalizeMac((string) ($record['client_mac'] ?? ''));
            if ($clientMac === '') {
                throw new \InvalidArgumentException('La observación Meraki no contiene una MAC válida.');
            }

            $type = mb_strtolower((string) ($record['type'] ?? ''));
            $deviceType = str_contains($type, 'bluetooth') || $type === 'ble'
                ? 'beacon'
                : 'wifi_client';
            $device = Device::query()->firstOrNew(['identifier' => $clientMac]);
            $metadata = $device->metadata ?? [];
            $metadata['meraki'] = array_filter([
                'network_id' => $record['network_id'] ?? null,
                'api_version' => $record['version'] ?? null,
                'last_type' => $record['type'] ?? null,
                'details' => $record['metadata'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '');
            $device->fill([
                'name' => $device->exists ? $device->name : (string) ($record['client_name'] ?: $clientMac),
                'type' => $device->exists ? $device->type : $deviceType,
                'status' => 'active',
                'metadata' => $metadata,
                'last_seen_at' => $event->observed_at ?? $event->received_at,
            ])->save();

            $event->forceFill([
                'device_id' => $device->id,
                'normalized_payload' => Arr::except($record, ['raw']),
                'raw_payload' => [
                    'version' => $record['version'] ?? null,
                    'type' => $record['type'] ?? null,
                    'network_id' => $record['network_id'] ?? null,
                    'client_mac' => $clientMac,
                    'observed_at' => $record['observed_at'] ?? null,
                    'source_summary' => $record['source_summary'] ?? [],
                ],
            ])->save();

            $signalRows = [];
            foreach (($record['reporting_aps'] ?? []) as $accessPoint) {
                if (is_array($accessPoint)) {
                    $accessPoints->register(
                        $accessPoint,
                        $event->observed_at ?? $event->received_at,
                        (string) ($record['network_id'] ?? ''),
                    );
                }
            }

            foreach (($record['rssi_records'] ?? []) as $reading) {
                if (! is_array($reading) || ! is_numeric($reading['rssi'] ?? null)) {
                    continue;
                }
                $receiver = BleObservationExtractor::normalizeMac((string) ($reading['apMac'] ?? ''));
                $accessPoints->register(
                    $reading,
                    $event->observed_at ?? $event->received_at,
                    (string) ($record['network_id'] ?? ''),
                );
                if ($receiver === '') {
                    continue;
                }

                $key = $clientMac.'|'.$receiver;
                $signalRows[$key] = [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $event->organization_id,
                    'telemetry_event_id' => $event->id,
                    'transmitter_mac' => $clientMac,
                    'receiver_identifier' => $receiver,
                    'rssi' => (int) $reading['rssi'],
                    'observed_at' => $event->observed_at ?? $event->received_at,
                    'metadata' => json_encode(array_filter([
                        'source' => 'meraki',
                        'version' => $record['version'] ?? null,
                    ], fn (mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($signalRows !== []) {
                SignalObservation::query()->upsert(
                    array_values($signalRows),
                    ['telemetry_event_id', 'transmitter_mac', 'receiver_identifier'],
                    ['rssi', 'observed_at', 'metadata', 'updated_at'],
                );
            }

            $assignment = $this->assignmentAt($device->id, $event->observed_at ?? $event->received_at);
            if ($assignment) {
                $this->storePosition($event, $assignment, $record, $zones);
            }

            $event->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'processing_error' => null,
            ])->saveQuietly();
            $event->connector()
                ->where(function (Builder $query): void {
                    $query->whereNull('last_success_at')
                        ->orWhere('last_success_at', '<', now()->subMinute());
                })
                ->update(['last_success_at' => now(), 'last_error' => null]);
            $retention->prune($event);
        } catch (Throwable $exception) {
            $event->forceFill([
                'processing_status' => 'failed',
                'processing_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->saveQuietly();
            $event->connector()->update(['last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
            Log::error('Falló el procesamiento de una observación Meraki.', [
                'telemetry_event_id' => $event->id,
                'connector_id' => $event->connector_id,
                'exception' => $exception::class,
            ]);
            throw $exception;
        } finally {
            $context->set(null);
        }
    }

    private function assignmentAt(string $deviceId, Carbon $seenAt): ?AssetDeviceAssignment
    {
        return AssetDeviceAssignment::query()
            ->with('asset')
            ->where('device_id', $deviceId)
            ->where('started_at', '<=', $seenAt)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('ended_at')
                ->orWhere('ended_at', '>=', $seenAt))
            ->latest('started_at')
            ->first();
    }

    /** @param array<string, mixed> $record */
    private function storePosition(
        TelemetryEvent $event,
        AssetDeviceAssignment $assignment,
        array $record,
        ZoneClassifier $zones,
    ): void {
        $mapping = null;
        $externalFloorId = trim((string) ($record['external_floor_plan_id'] ?? ''));
        if ($externalFloorId !== '') {
            $mapping = MerakiFloorPlanMapping::query()
                ->with('floorPlan.zones')
                ->where('connector_id', $event->connector_id)
                ->where('external_floor_plan_id', $externalFloorId)
                ->first();
        }

        $x = is_numeric($record['x'] ?? null) ? (float) $record['x'] : null;
        $sourceY = is_numeric($record['y'] ?? null) ? (float) $record['y'] : null;
        $y = $sourceY;
        if ($mapping && $sourceY !== null && $mapping->invert_y) {
            $y = (float) $mapping->floorPlan->height_meters - $sourceY;
        }

        $latitude = is_numeric($record['latitude'] ?? null) ? (float) $record['latitude'] : null;
        $longitude = is_numeric($record['longitude'] ?? null) ? (float) $record['longitude'] : null;
        if (! $mapping) {
            $x = null;
            $y = null;
        }
        if ($x === null && $y === null && $latitude === null && $longitude === null) {
            return;
        }

        $accuracy = is_numeric($record['accuracy_meters'] ?? null)
            ? max(0.0, (float) $record['accuracy_meters'])
            : null;
        $diagonal = $mapping
            ? hypot((float) $mapping->floorPlan->width_meters, (float) $mapping->floorPlan->height_meters)
            : 0.0;
        $confidence = $accuracy === null
            ? null
            : max(0.0, min(1.0, $diagonal > 0 ? 1 - ($accuracy / $diagonal) : 1 / (1 + $accuracy)));
        $zone = $mapping && $x !== null && $y !== null
            ? $zones->classify($mapping->floorPlan, $x, $y)
            : null;

        PositionEstimate::query()->updateOrCreate(
            ['asset_id' => $assignment->asset_id, 'telemetry_event_id' => $event->id],
            [
                'location_id' => $mapping?->floorPlan->location_id,
                'floor_plan_id' => $mapping?->floor_plan_id,
                'zone_id' => $zone?->id,
                'algorithm' => 'meraki_location',
                'algorithm_version' => (string) ($record['version'] ?? 'unknown'),
                'x' => $x,
                'y' => $y,
                'raw_x' => is_numeric($record['x'] ?? null) ? (float) $record['x'] : null,
                'raw_y' => $sourceY,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'confidence' => $confidence,
                'accuracy_meters' => $accuracy,
                'calculated_at' => now(),
                'evidence' => [],
                'filter_state' => null,
            ],
        );

        $seenAt = $event->observed_at ?? $event->received_at;
        $assignment->asset->forceFill([
            'location_id' => $mapping?->floorPlan->location_id ?? $assignment->asset->location_id,
            'last_seen_at' => ! $assignment->asset->last_seen_at || $assignment->asset->last_seen_at->lt($seenAt)
                ? $seenAt
                : $assignment->asset->last_seen_at,
        ])->save();
    }
}
