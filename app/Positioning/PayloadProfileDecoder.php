<?php

declare(strict_types=1);

namespace App\Positioning;

use App\Models\Connector;
use App\Models\PayloadDecoderProfile;
use Illuminate\Support\Arr;

class PayloadProfileDecoder
{
    /** @return array{decoded: mixed, receiver_identifier: ?string, profile: ?PayloadDecoderProfile} */
    public function decode(array $rawPayload, Connector $connector): array
    {
        $decoded = Arr::get($rawPayload, 'uplink_message.decoded_payload', []);
        $port = Arr::get($rawPayload, 'uplink_message.f_port');

        $profiles = PayloadDecoderProfile::query()
            ->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('connector_id')->orWhere('connector_id', $connector->id))
            ->orderBy('priority')
            ->get();

        foreach ($profiles as $profile) {
            if ($profile->match_f_port !== null && (int) $port !== (int) $profile->match_f_port) {
                continue;
            }
            if ($profile->match_path && (string) data_get($decoded, $profile->match_path) !== (string) $profile->match_value) {
                continue;
            }

            $normalized = $this->transform($decoded, $profile);

            if ($normalized !== []) {
                $receiver = $profile->receiver_path ? data_get($decoded, $profile->receiver_path) : null;

                return ['decoded' => ['observations' => $normalized], 'receiver_identifier' => is_scalar($receiver) ? (string) $receiver : null, 'profile' => $profile];
            }
        }

        return ['decoded' => $decoded, 'receiver_identifier' => null, 'profile' => null];
    }

    /** @return list<array{mac: string, rssi: int, source: array<string, mixed>}> */
    public function transform(mixed $decoded, PayloadDecoderProfile $profile): array
    {
        $observations = data_get($decoded, $profile->observations_path);
        if (! is_array($observations)) {
            return [];
        }

        $normalized = [];
        foreach ($observations as $observation) {
            if (! is_array($observation)) {
                continue;
            }
            $mac = data_get($observation, $profile->mac_path);
            $rssi = data_get($observation, $profile->rssi_path);
            if (is_string($mac) && is_numeric($rssi)) {
                $normalized[] = ['mac' => $mac, 'rssi' => (int) $rssi, 'source' => $observation];
            }
        }

        return $normalized;
    }
}
