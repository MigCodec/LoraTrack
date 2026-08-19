<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Jobs\ProcessMerakiLocationObservation;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Positioning\ZoneClassifier;
use App\Support\TelemetryTimestamp;
use App\Telemetry\TelemetryCounterUpdater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SynchronousMerakiWebhookProcessor
{
    public function __construct(
        private readonly MerakiPayloadNormalizer $normalizer,
        private readonly MerakiAccessPointRegistrar $accessPoints,
        private readonly MerakiClientDeviceRegistrar $clients,
        private readonly MerakiEventPayloadCompactor $payloadCompactor,
        private readonly ZoneClassifier $zones,
        private readonly TelemetryCounterUpdater $counters,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted: int, duplicates: int, processed: int, failed: int}
     */
    public function process(Connector $connector, array $payload, int $majorVersion, Carbon $receivedAt): array
    {
        $records = $this->avoidRepeatedAccessPoints($this->normalizer->records($payload, $majorVersion));
        if ($records === []) {
            throw ValidationException::withMessages(['data.observations' => 'El payload no contiene observaciones válidas.']);
        }
        $configuredNetwork = trim((string) ($connector->configuration['network_id'] ?? ''));
        if ($configuredNetwork !== '' && ! collect($records)->every(
            fn (array $record): bool => ($record['network_id'] ?? '') === $configuredNetwork,
        )) {
            throw ValidationException::withMessages(['network_id' => 'El networkId no corresponde al configurado.']);
        }
        $uniqueRecords = [];
        foreach ($records as $record) {
            $uniqueRecords[$this->externalEventId($record)] = $record;
        }

        $existing = collect(array_keys($uniqueRecords))
            ->chunk(500)
            ->flatMap(fn ($ids) => DB::table('telemetry_events')
                ->where('connector_id', $connector->id)
                ->whereIn('external_event_id', $ids)
                ->pluck('external_event_id'))
            ->mapWithKeys(fn ($id): array => [(string) $id => true])
            ->all();

        $now = now();
        $rows = [];
        foreach ($uniqueRecords as $externalEventId => $record) {
            if (isset($existing[$externalEventId])) {
                continue;
            }
            $rows[] = [
                'id' => (string) Str::ulid(),
                'organization_id' => $connector->organization_id,
                'connector_id' => $connector->id,
                'device_id' => null,
                'external_event_id' => $externalEventId,
                'event_type' => 'meraki_location',
                'observed_at' => filled($record['observed_at'] ?? null)
                    ? TelemetryTimestamp::parseProviderTime($record['observed_at'])
                    : null,
                'received_at' => $receivedAt,
                'processed_at' => null,
                'normalized_payload' => null,
                'raw_payload' => json_encode($record, JSON_THROW_ON_ERROR),
                'processing_status' => 'pending',
                'processing_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('telemetry_events')->insertOrIgnore($chunk);
        }

        $events = TelemetryEvent::query()
            ->withoutGlobalScopes()
            ->whereIn('id', array_column($rows, 'id'))
            ->get();
        $this->counters->recordBulkCreated((string) $connector->id, $events->count());

        $processor = new ProcessMerakiLocationObservation('synchronous');
        $processed = 0;
        $failed = 0;
        foreach ($events as $event) {
            $event->setRelation('connector', $connector);
            $event->setRelation('organization', $connector->organization);
            try {
                $processor->process(
                    $event,
                    $this->zones,
                    $this->accessPoints,
                    $this->clients,
                    false,
                    $this->payloadCompactor,
                );
                $processed++;
            } catch (Throwable) {
                $failed++;
            }
        }
        $this->counters->recordBulkStatusChanged((string) $connector->id, 'pending', 'processed', $processed);
        $this->counters->recordBulkStatusChanged((string) $connector->id, 'pending', 'failed', $failed);

        $connectorState = ['last_activity_at' => now()];
        if ($failed === 0) {
            $connectorState['last_success_at'] = now();
            $connectorState['last_error'] = null;
        }
        $connector->forceFill($connectorState)->save();

        return [
            'accepted' => $events->count(),
            'duplicates' => count($records) - $events->count(),
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /** @param array<string, mixed> $record */
    private function externalEventId(array $record): string
    {
        return hash('sha256', implode('|', [
            $record['version'], $record['type'], $record['network_id'], $record['client_mac'],
            $record['observed_at'], $record['external_floor_plan_id'], $record['x'], $record['y'],
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function avoidRepeatedAccessPoints(array $records): array
    {
        $keptInventory = false;
        foreach ($records as &$record) {
            if (($record['reporting_aps'] ?? []) === []) {
                continue;
            }
            if ($keptInventory) {
                $record['reporting_aps'] = [];
            } else {
                $keptInventory = true;
            }
        }
        unset($record);

        return $records;
    }
}
