<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Models\Device;
use App\Positioning\BleObservationExtractor;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;

class MerakiClientDeviceRegistrar
{
    /** @var array<string, Device> Cache scoped by organization and normalized MAC. */
    private array $registered = [];

    /** @param array<string, mixed> $record */
    public function register(array $record, Carbon $seenAt): Device
    {
        $identifier = BleObservationExtractor::normalizeMac((string) ($record['client_mac'] ?? ''));
        if ($identifier === '') {
            throw new \InvalidArgumentException('La observacion Meraki no contiene una MAC valida.');
        }

        $organizationId = app(OrganizationContext::class)->id();
        if ($organizationId === null) {
            throw new \LogicException('No hay una organizacion activa para registrar el cliente Meraki.');
        }

        $cacheKey = $organizationId.'|'.$identifier;
        $device = $this->registered[$cacheKey]
            ?? Device::query()->firstOrNew(['identifier' => $identifier]);
        $type = mb_strtolower((string) ($record['type'] ?? ''));
        $deviceType = str_contains($type, 'bluetooth') || $type === 'ble' ? 'beacon' : 'wifi_client';
        $metadata = $device->metadata ?? [];
        $metadata['meraki'] = array_filter([
            'network_id' => $record['network_id'] ?? null,
            'api_version' => $record['version'] ?? null,
            'last_type' => $record['type'] ?? null,
            'details' => $record['metadata'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $device->fill([
            'name' => $device->exists ? $device->name : (string) ($record['client_name'] ?: $identifier),
            'type' => $device->exists ? $device->type : $deviceType,
            'status' => 'active',
            'metadata' => $metadata,
            'last_seen_at' => ! $device->last_seen_at || $device->last_seen_at->lt($seenAt)
                ? $seenAt
                : $device->last_seen_at,
        ])->save();

        return $this->registered[$cacheKey] = $device;
    }
}
