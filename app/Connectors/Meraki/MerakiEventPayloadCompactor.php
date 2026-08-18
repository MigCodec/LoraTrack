<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use Illuminate\Support\Arr;

class MerakiEventPayloadCompactor
{
    public const STORAGE_VERSION = 2;

    /**
     * @param array<string, mixed> $record
     * @return array{raw_payload: array<string, mixed>, normalized_payload: array<string, mixed>}
     */
    public function compact(array $record): array
    {
        $sourceSummary = is_array($record['source_summary'] ?? null)
            ? $record['source_summary']
            : [];

        return [
            'raw_payload' => array_filter([
                'version' => $record['version'] ?? null,
                'type' => $record['type'] ?? null,
                'network_id' => $record['network_id'] ?? null,
                'source_summary' => $sourceSummary,
            ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
            'normalized_payload' => array_filter(Arr::only($record, [
                'client_name',
                'external_floor_plan_id',
                'external_floor_plan_name',
                'x',
                'y',
                'latitude',
                'longitude',
                'accuracy_meters',
                'metadata',
            ]), fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
        ];
    }
}
