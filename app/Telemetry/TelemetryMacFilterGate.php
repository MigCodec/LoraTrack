<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Models\TelemetryMacFilter;
use App\Positioning\BleObservationExtractor;
use Illuminate\Support\Collection;

class TelemetryMacFilterGate
{
    /** @param iterable<string|null> $macs */
    public function allows(?string $organizationId, iterable $macs): bool
    {
        if (! $organizationId) {
            return true;
        }

        $normalized = collect($macs)
            ->map(fn (?string $mac): string => BleObservationExtractor::normalizeMac((string) $mac))
            ->filter(fn (string $mac): bool => strlen($mac) === 12)
            ->unique()
            ->values();

        if (! $this->hasActiveFilters($organizationId)) {
            return true;
        }

        if ($normalized->isEmpty()) {
            return false;
        }

        return TelemetryMacFilter::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('enabled', true)
            ->whereIn('normalized_mac', $normalized->all())
            ->exists();
    }

    /** @return Collection<int, string> */
    public function merakiPayloadMacs(array $payload): Collection
    {
        if (is_string($payload['client_mac'] ?? null)) {
            return collect([$payload['client_mac']]);
        }

        $observations = data_get($payload, 'data.observations');
        if (! is_array($observations)) {
            return collect();
        }

        return collect($observations)
            ->filter(fn (mixed $observation): bool => is_array($observation))
            ->map(fn (array $observation): ?string => is_string($observation['clientMac'] ?? null) ? $observation['clientMac'] : null)
            ->filter()
            ->values();
    }

    public function filterMerakiPayload(?string $organizationId, array $payload): ?array
    {
        if (! $organizationId || ! $this->hasActiveFilters($organizationId)) {
            return $payload;
        }

        $allowed = $this->allowedMacs($organizationId, $this->merakiPayloadMacs($payload));
        if ($allowed->isEmpty()) {
            return null;
        }

        if (is_string($payload['client_mac'] ?? null)) {
            return $allowed->contains(BleObservationExtractor::normalizeMac($payload['client_mac']))
                ? $payload
                : null;
        }

        $observations = data_get($payload, 'data.observations');
        if (! is_array($observations)) {
            return null;
        }

        $payload['data']['observations'] = collect($observations)
            ->filter(fn (mixed $observation): bool => is_array($observation)
                && is_string($observation['clientMac'] ?? null)
                && $allowed->contains(BleObservationExtractor::normalizeMac($observation['clientMac'])))
            ->values()
            ->all();

        return $payload['data']['observations'] === [] ? null : $payload;
    }

    /** @return Collection<int, string> */
    public function ttiPayloadMacs(array $payload): Collection
    {
        $decoded = data_get($payload, 'uplink_message.decoded_payload', []);

        return collect($this->walkForMacs(is_array($decoded) ? $decoded : []));
    }

    private function hasActiveFilters(string $organizationId): bool
    {
        return TelemetryMacFilter::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('enabled', true)
            ->exists();
    }

    /** @param iterable<string|null> $macs */
    private function allowedMacs(string $organizationId, iterable $macs): Collection
    {
        $normalized = collect($macs)
            ->map(fn (?string $mac): string => BleObservationExtractor::normalizeMac((string) $mac))
            ->filter(fn (string $mac): bool => strlen($mac) === 12)
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return collect();
        }

        return TelemetryMacFilter::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('enabled', true)
            ->whereIn('normalized_mac', $normalized->all())
            ->pluck('normalized_mac');
    }

    /** @return list<string> */
    private function walkForMacs(array $payload): array
    {
        $matches = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                array_push($matches, ...$this->walkForMacs($value));

                continue;
            }

            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $key = mb_strtolower((string) $key);
            if (in_array($key, ['mac', 'mac_address', 'address', 'transmitter_mac', 'client_mac', 'clientmac'], true)) {
                $matches[] = (string) $value;
            }
        }

        return $matches;
    }
}
