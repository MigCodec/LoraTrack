<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Models\Device;
use App\Positioning\BleObservationExtractor;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;

class MerakiAccessPointRegistrar
{
    /** @var array<string, Device> Cache scoped by organization and normalized MAC. */
    private array $registered = [];

    /** @param array<string, mixed> $reading */
    public function register(array $reading, Carbon $seenAt, string $networkId): ?Device
    {
        $identifier = BleObservationExtractor::normalizeMac((string) ($reading['apMac'] ?? ''));
        if (strlen($identifier) !== 12) {
            return null;
        }

        $organizationId = app(OrganizationContext::class)->id();
        if ($organizationId === null) {
            throw new \LogicException('No hay una organizacion activa para registrar el AP Meraki.');
        }
        $cacheKey = $organizationId.'|'.$identifier;

        $device = $this->registered[$cacheKey] ?? null;
        if ($device && $device->type !== 'scanner') {
            return $device;
        }
        if (! $device) {
            $device = Device::query()
                ->withExists([
                    'assignments as has_active_assignments' => fn ($query) => $query->whereNull('ended_at'),
                    'installations as has_active_installations' => fn ($query) => $query->whereNull('ended_at'),
                ])
                ->where('identifier', $identifier)
                ->first() ?? Device::query()->make(['identifier' => $identifier]);
            if ($device->exists && $device->type !== 'scanner') {
                $hasActiveUsage = (bool) $device->has_active_assignments
                    || (bool) $device->has_active_installations;
                if ($hasActiveUsage) {
                    return $this->registered[$cacheKey] = $device;
                }
            }
        }

        $metadata = $device->metadata ?? [];
        $metadata['meraki'] = array_filter([
            'role' => 'access_point_scanner',
            'network_id' => $networkId !== '' ? $networkId : null,
            'serial' => $reading['apSerial'] ?? null,
            'reported_latitude' => $reading['lat'] ?? null,
            'reported_longitude' => $reading['lng'] ?? null,
            'installation_status' => $device->exists && (bool) $device->has_active_installations
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

        return $this->registered[$cacheKey] = $device;
    }

    private function formattedMac(string $identifier): string
    {
        return implode(':', str_split($identifier, 2));
    }
}
