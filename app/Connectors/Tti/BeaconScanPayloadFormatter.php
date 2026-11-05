<?php

declare(strict_types=1);

namespace App\Connectors\Tti;

use App\Positioning\BleObservationExtractor;
use App\Support\TelemetryTimestamp;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Throwable;

final class BeaconScanPayloadFormatter
{
    public const NAME = 'tti_beacon_scan';

    public const VERSION = '1.0';

    /**
     * @return array{
     *     formatter: string,
     *     formatter_version: string,
     *     receiver_identifier: string,
     *     receiver_type: string,
     *     observed_at: ?CarbonInterface,
     *     decoded: array{type: string, observations: list<array{mac: string, rssi: int, observed_at: ?string, source: array<string, mixed>}>}
     * }|null
     */
    public function format(array $payload): ?array
    {
        $decoded = Arr::get($payload, 'uplink_message.decoded_payload');
        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'beacon_scan' || ! is_array($decoded['beacons'] ?? null)) {
            return null;
        }

        $receiver = (string) (Arr::get($payload, 'end_device_ids.dev_eui') ?: Arr::get($payload, 'end_device_ids.device_id'));
        if ($receiver === '') {
            return null;
        }

        $observations = [];
        foreach ($decoded['beacons'] as $beacon) {
            if (! is_array($beacon) || ! is_string($beacon['mac'] ?? null) || ! is_numeric($beacon['rssi'] ?? null)) {
                continue;
            }

            $mac = BleObservationExtractor::normalizeMac($beacon['mac']);
            $rssi = (int) $beacon['rssi'];
            if (strlen($mac) !== 12 || $rssi > 0 || $rssi < -127) {
                continue;
            }

            $observedAt = $this->parseTime($beacon['time_utc'] ?? null, $beacon['timestamp'] ?? null);
            $observations[$mac] = [
                'mac' => $mac,
                'rssi' => $rssi,
                'observed_at' => $observedAt?->toIso8601String(),
                'source' => $beacon,
            ];
        }

        $scanTime = $this->parseTime($decoded['time_utc'] ?? null, $decoded['timestamp'] ?? null);

        return [
            'formatter' => self::NAME,
            'formatter_version' => self::VERSION,
            'receiver_identifier' => BleObservationExtractor::normalizeMac($receiver),
            'receiver_type' => 'scanner',
            'observed_at' => $scanTime,
            'decoded' => [
                'type' => 'beacon_scan',
                'observations' => array_values($observations),
            ],
        ];
    }

    private function parseTime(mixed $isoTime, mixed $unixTime): ?CarbonInterface
    {
        try {
            if (is_string($isoTime) && $isoTime !== '') {
                return TelemetryTimestamp::parseProviderTime($isoTime);
            }

            if (is_numeric($unixTime)) {
                return TelemetryTimestamp::parseProviderTime('@'.(int) $unixTime);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
