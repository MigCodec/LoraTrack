<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Models\Device;
use App\Positioning\BleObservationExtractor;
use Illuminate\Support\Carbon;

class MerakiAccessPointRegistrar
{
    /** @param array<string, mixed> $reading */
    public function register(array $reading, Carbon $seenAt, string $networkId): ?Device
    {
        $identifier = BleObservationExtractor::normalizeMac((string) ($reading['apMac'] ?? ''));
        if (strlen($identifier) !== 12) {
            return null;
        }

        $device = Device::query()->firstOrNew(['identifier' => $identifier]);
        if ($device->exists && $device->type !== 'scanner') {
            return $device;
        }

        $metadata = $device->metadata ?? [];
        $metadata['meraki'] = array_filter([
            'role' => 'access_point_scanner',
            'network_id' => $networkId !== '' ? $networkId : null,
            'serial' => $reading['apSerial'] ?? null,
            'reported_latitude' => $reading['lat'] ?? null,
            'reported_longitude' => $reading['lng'] ?? null,
            'installation_status' => $device->exists && $device->installations()->whereNull('ended_at')->exists()
                ? 'installed'
                : 'pending_floor_plan',
        ], fn (mixed $value): bool => $value !== null);
        $currentLastSeen = $device->last_seen_at;
        $name = trim((string) ($reading['apName'] ?? ''));

        $device->fill([
            'name' => $device->exists
                ? ($device->name ?: ($name ?: 'Meraki AP '.$this->formattedMac($identifier)))
                : ($name ?: 'Meraki AP '.$this->formattedMac($identifier)),
            'type' => 'scanner',
            'model' => $device->model ?: 'Cisco Meraki AP',
            'status' => 'active',
            'metadata' => $metadata,
            'last_seen_at' => ! $currentLastSeen || $currentLastSeen->lt($seenAt)
                ? $seenAt
                : $currentLastSeen,
        ])->save();

        return $device;
    }

    private function formattedMac(string $identifier): string
    {
        return implode(':', str_split($identifier, 2));
    }
}
