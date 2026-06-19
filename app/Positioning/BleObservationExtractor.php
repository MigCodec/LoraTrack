<?php

declare(strict_types=1);

namespace App\Positioning;

class BleObservationExtractor
{
    /** @return list<array{mac: string, rssi: int, metadata: array<string, mixed>}> */
    public function extract(mixed $decodedPayload): array
    {
        if (! is_array($decodedPayload)) {
            return [];
        }

        $candidates = [];
        foreach (['observations', 'beacons', 'ble', 'scan', 'devices'] as $key) {
            if (isset($decodedPayload[$key]) && is_array($decodedPayload[$key])) {
                $candidates = array_merge($candidates, $decodedPayload[$key]);
            }
        }

        if ($this->looksLikeObservation($decodedPayload)) {
            $candidates[] = $decodedPayload;
        }

        $observations = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $mac = $candidate['mac'] ?? $candidate['mac_address'] ?? $candidate['address'] ?? $candidate['beacon_mac'] ?? null;
            $rssi = $candidate['rssi'] ?? $candidate['signal'] ?? $candidate['signal_strength'] ?? null;
            if (! is_string($mac) || ! is_numeric($rssi)) {
                continue;
            }

            $normalizedMac = self::normalizeMac($mac);
            $rssiValue = (int) $rssi;
            if ($normalizedMac === '' || $rssiValue > 0 || $rssiValue < -127) {
                continue;
            }

            $observations[$normalizedMac] = [
                'mac' => $normalizedMac,
                'rssi' => $rssiValue,
                'metadata' => $candidate,
            ];
        }

        return array_values($observations);
    }

    public static function normalizeMac(string $mac): string
    {
        return mb_strtoupper((string) preg_replace('/[^A-Fa-f0-9]/', '', $mac));
    }

    /** @param array<string, mixed> $payload */
    private function looksLikeObservation(array $payload): bool
    {
        return isset($payload['rssi'])
            && (isset($payload['mac']) || isset($payload['mac_address']) || isset($payload['address']));
    }
}
