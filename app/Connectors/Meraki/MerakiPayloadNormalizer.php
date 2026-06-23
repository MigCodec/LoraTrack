<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class MerakiPayloadNormalizer
{
    /** @return list<array<string, mixed>> */
    public function records(array $payload, int $majorVersion): array
    {
        return $majorVersion === 3
            ? $this->versionThree($payload)
            : $this->versionTwo($payload);
    }

    /** @return list<array<string, mixed>> */
    private function versionThree(array $payload): array
    {
        $data = Arr::get($payload, 'data');
        $observations = is_array($data) ? ($data['observations'] ?? null) : null;
        if (! is_array($observations)) {
            throw ValidationException::withMessages(['data.observations' => 'Meraki v3 requiere data.observations.']);
        }

        $records = [];
        foreach ($observations as $observation) {
            if (! is_array($observation) || ! is_string($observation['clientMac'] ?? null)) {
                continue;
            }

            $locations = is_array($observation['locations'] ?? null) ? $observation['locations'] : [];
            if ($locations === []) {
                $records[] = $this->recordThree($payload, $observation, null);

                continue;
            }

            foreach ($locations as $location) {
                if (is_array($location)) {
                    $records[] = $this->recordThree($payload, $observation, $location);
                }
            }
        }

        return $records;
    }

    /** @return array<string, mixed> */
    private function recordThree(array $payload, array $observation, ?array $location): array
    {
        $floorPlan = is_array($location['floorPlan'] ?? null) ? $location['floorPlan'] : [];
        $latest = is_array($observation['latestRecord'] ?? null) ? $observation['latestRecord'] : [];

        return [
            'version' => (string) $payload['version'],
            'type' => (string) $payload['type'],
            'network_id' => (string) Arr::get($payload, 'data.networkId', ''),
            'client_mac' => (string) $observation['clientMac'],
            'client_name' => (string) ($observation['name'] ?? $observation['userId'] ?? $observation['clientMac']),
            'observed_at' => $location['time'] ?? $latest['time'] ?? Arr::get($payload, 'data.endTime'),
            'external_floor_plan_id' => (string) ($location['floorPlanId'] ?? $floorPlan['id'] ?? ''),
            'external_floor_plan_name' => (string) ($location['floorPlanName'] ?? $floorPlan['name'] ?? ''),
            'x' => $location['x'] ?? $floorPlan['x'] ?? null,
            'y' => $location['y'] ?? $floorPlan['y'] ?? null,
            'latitude' => $location['lat'] ?? null,
            'longitude' => $location['lng'] ?? null,
            'accuracy_meters' => $location['variance'] ?? null,
            'rssi_records' => is_array($location['rssiRecords'] ?? null)
                ? $location['rssiRecords']
                : [[
                    'apMac' => $location['nearestApMac'] ?? $latest['nearestApMac'] ?? null,
                    'rssi' => $latest['nearestApRssi'] ?? null,
                ]],
            'metadata' => Arr::except($observation, ['locations']),
            'raw' => [
                'version' => $payload['version'],
                'type' => $payload['type'],
                'data' => [
                    'networkId' => Arr::get($payload, 'data.networkId'),
                    'reportingAps' => Arr::get($payload, 'data.reportingAps'),
                    'observation' => $observation,
                    'location' => $location,
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function versionTwo(array $payload): array
    {
        $data = Arr::get($payload, 'data');
        $observations = is_array($data) ? ($data['observations'] ?? null) : null;
        if (! is_array($observations)) {
            throw ValidationException::withMessages(['data.observations' => 'Meraki v2 requiere data.observations.']);
        }

        $records = [];
        foreach ($observations as $observation) {
            if (! is_array($observation) || ! is_string($observation['clientMac'] ?? null)) {
                continue;
            }
            $location = is_array($observation['location'] ?? null) ? $observation['location'] : [];
            $floorNames = is_array($data['apFloors'] ?? null) ? $data['apFloors'] : [];
            $xValues = is_array($location['x'] ?? null) ? $location['x'] : [];
            $yValues = is_array($location['y'] ?? null) ? $location['y'] : [];
            $floorIndex = array_key_first($xValues);

            $records[] = [
                'version' => (string) $payload['version'],
                'type' => (string) $payload['type'],
                'network_id' => '',
                'client_mac' => (string) $observation['clientMac'],
                'client_name' => (string) ($observation['name'] ?? $observation['clientMac']),
                'observed_at' => $observation['seenTime'] ?? null,
                'external_floor_plan_id' => $floorIndex === null ? '' : (string) ($floorNames[$floorIndex] ?? ''),
                'external_floor_plan_name' => $floorIndex === null ? '' : (string) ($floorNames[$floorIndex] ?? ''),
                'x' => $floorIndex === null ? null : ($xValues[$floorIndex] ?? null),
                'y' => $floorIndex === null ? null : ($yValues[$floorIndex] ?? null),
                'latitude' => $location['lat'] ?? null,
                'longitude' => $location['lng'] ?? null,
                'accuracy_meters' => $location['unc'] ?? null,
                'rssi_records' => [[
                    'apMac' => $data['apMac'] ?? null,
                    'rssi' => $observation['rssi'] ?? null,
                ]],
                'metadata' => Arr::except($observation, ['location']),
                'raw' => [
                    'version' => $payload['version'],
                    'type' => $payload['type'],
                    'data' => [
                        'apMac' => $data['apMac'] ?? null,
                        'apTags' => $data['apTags'] ?? [],
                        'apFloors' => $floorNames,
                        'observation' => $observation,
                    ],
                ],
            ];
        }

        return $records;
    }
}
