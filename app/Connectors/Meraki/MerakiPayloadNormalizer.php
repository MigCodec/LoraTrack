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
        if ($majorVersion === 3 && $this->isExpandedRecord($payload)) {
            return [$this->compactExpandedRecord($payload)];
        }

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
        $rssiRecords = $this->compactRssiRecords(
            is_array($location['rssiRecords'] ?? null)
                ? $location['rssiRecords']
                : [[
                    'apMac' => $location['nearestApMac'] ?? $latest['nearestApMac'] ?? null,
                    'rssi' => $latest['nearestApRssi'] ?? null,
                ]],
            is_array(Arr::get($payload, 'data.reportingAps'))
                ? Arr::get($payload, 'data.reportingAps')
                : [],
        );

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
            'rssi_records' => $rssiRecords,
            'metadata' => $this->compactMetadata($observation, $latest),
            'source_summary' => [
                'payload_checksum' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES) ?: ''),
                'reporting_ap_count' => count(Arr::get($payload, 'data.reportingAps', [])),
                'location_count' => count($observation['locations'] ?? []),
                'rssi_record_count' => count($rssiRecords),
                'nearest_ap_mac' => $location['nearestApMac'] ?? $latest['nearestApMac'] ?? null,
            ],
        ];
    }

    private function isExpandedRecord(array $payload): bool
    {
        return isset($payload['client_mac'], $payload['observed_at'])
            && array_key_exists('rssi_records', $payload);
    }

    /** @return array<string, mixed> */
    private function compactExpandedRecord(array $payload): array
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $sourceSummary = is_array($payload['source_summary'] ?? null) ? $payload['source_summary'] : [];
        $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
        $rawData = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $observation = is_array($rawData['observation'] ?? null) ? $rawData['observation'] : [];
        $latest = is_array($metadata['latestRecord'] ?? null)
            ? $metadata['latestRecord']
            : (is_array($metadata['latest_record'] ?? null) ? $metadata['latest_record'] : []);
        $rssiRecords = $this->compactRssiRecords(
            is_array($payload['rssi_records'] ?? null) ? $payload['rssi_records'] : [],
            is_array($rawData['reportingAps'] ?? null) ? $rawData['reportingAps'] : [],
        );

        return [
            'version' => (string) ($payload['version'] ?? '3.0'),
            'type' => (string) ($payload['type'] ?? 'Bluetooth'),
            'network_id' => (string) ($payload['network_id'] ?? $rawData['networkId'] ?? ''),
            'client_mac' => (string) $payload['client_mac'],
            'client_name' => (string) ($payload['client_name'] ?? $metadata['name'] ?? $payload['client_mac']),
            'observed_at' => $payload['observed_at'],
            'external_floor_plan_id' => (string) ($payload['external_floor_plan_id'] ?? ''),
            'external_floor_plan_name' => (string) ($payload['external_floor_plan_name'] ?? ''),
            'x' => $payload['x'] ?? null,
            'y' => $payload['y'] ?? null,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'accuracy_meters' => $payload['accuracy_meters'] ?? null,
            'rssi_records' => $rssiRecords,
            'metadata' => $this->compactMetadata($metadata + $observation, $latest),
            'source_summary' => [
                'payload_checksum' => hash('sha256', json_encode($raw ?: $payload, JSON_UNESCAPED_SLASHES) ?: ''),
                'reporting_ap_count' => $sourceSummary['reporting_ap_count'] ?? count($rawData['reportingAps'] ?? []),
                'location_count' => $sourceSummary['location_count'] ?? count($observation['locations'] ?? []),
                'rssi_record_count' => count($rssiRecords),
                'nearest_ap_mac' => $sourceSummary['nearest_ap_mac'] ?? $latest['nearestApMac'] ?? $latest['nearest_ap_mac'] ?? null,
                'compacted_from_expanded_record' => true,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function compactRssiRecords(array $records, array $reportingAps = []): array
    {
        $accessPoints = collect($reportingAps)
            ->filter(fn (mixed $accessPoint): bool => is_array($accessPoint) && is_string($accessPoint['mac'] ?? null))
            ->keyBy(fn (array $accessPoint): string => mb_strtoupper(str_replace(':', '', $accessPoint['mac'])));

        return collect($records)
            ->filter(fn (mixed $record): bool => is_array($record)
                && is_string($record['apMac'] ?? null)
                && is_numeric($record['rssi'] ?? null))
            ->map(function (array $record) use ($accessPoints): array {
                $accessPoint = $accessPoints->get(mb_strtoupper(str_replace(':', '', (string) $record['apMac'])), []);

                return array_filter([
                    'apMac' => (string) $record['apMac'],
                    'rssi' => (int) $record['rssi'],
                    'apName' => $record['apName'] ?? $accessPoint['name'] ?? null,
                    'apSerial' => $record['apSerial'] ?? $accessPoint['serial'] ?? null,
                    'lat' => $record['lat'] ?? $accessPoint['lat'] ?? null,
                    'lng' => $record['lng'] ?? $accessPoint['lng'] ?? null,
                ], fn (mixed $value): bool => $value !== null);
            })
            ->unique('apMac')
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function compactMetadata(array $observation, array $latest): array
    {
        $sourceBeacons = is_array($observation['bleBeacons'] ?? null)
            ? $observation['bleBeacons']
            : (is_array($observation['ble_beacons'] ?? null) ? $observation['ble_beacons'] : []);

        $beacons = collect($sourceBeacons)
            ->filter(fn (mixed $beacon): bool => is_array($beacon))
            ->map(fn (array $beacon): array => Arr::only($beacon, [
                'uuid', 'major', 'minor', 'txPower', 'bleType',
            ]))
            ->values()
            ->all();

        return array_filter([
            'name' => $observation['name'] ?? null,
            'user_id' => $observation['userId'] ?? null,
            'ble_beacons' => $beacons,
            'latest_record' => array_filter([
                'time' => $latest['time'] ?? null,
                'nearest_ap_mac' => $latest['nearestApMac'] ?? $latest['nearest_ap_mac'] ?? null,
                'nearest_ap_rssi' => $latest['nearestApRssi'] ?? $latest['nearest_ap_rssi'] ?? null,
            ], fn (mixed $value): bool => $value !== null),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
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
