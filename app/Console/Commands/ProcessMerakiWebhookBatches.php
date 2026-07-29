<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiPayloadNormalizer;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Models\MerakiWebhookBatch;
use App\Support\TelemetryTimestamp;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessMerakiWebhookBatches extends Command
{
    protected $signature = 'loratrack:process-meraki-webhooks {--limit=3 : Cantidad maxima de lotes a procesar}';

    protected $description = 'Normaliza los lotes Meraki recibidos y crea eventos de telemetria idempotentes.';

    public function handle(MerakiPayloadNormalizer $normalizer): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        if ($limit === false) {
            $this->error('--limit debe ser un entero entre 1 y 100.');

            return self::FAILURE;
        }

        $batchIds = MerakiWebhookBatch::query()
            ->withoutGlobalScope('organization')
            ->where(function ($query): void {
                $query->where('processing_status', 'pending')
                    ->orWhere(function ($failed): void {
                        $failed->where('processing_status', 'failed')
                            ->where('attempts', '<', 3);
                    });
            })
            ->orderByRaw("CASE WHEN processing_status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('received_at')
            ->limit($limit)
            ->pluck('id');

        $processed = 0;
        foreach ($batchIds as $batchId) {
            if ($this->processBatch((string) $batchId, $normalizer)) {
                $processed++;
            }
        }

        $this->info("Lotes Meraki procesados: {$processed}.");

        return self::SUCCESS;
    }

    private function processBatch(
        string $batchId,
        MerakiPayloadNormalizer $normalizer,
    ): bool
    {
        $batch = DB::transaction(function () use ($batchId): ?MerakiWebhookBatch {
            $candidate = MerakiWebhookBatch::query()
                ->withoutGlobalScope('organization')
                ->lockForUpdate()
                ->find($batchId);
            if (! $candidate
                || ! in_array($candidate->processing_status, ['pending', 'failed'], true)
                || $candidate->attempts >= 3
            ) {
                return null;
            }
            $candidate->forceFill([
                'processing_status' => 'processing',
                'attempts' => $candidate->attempts + 1,
            ])->save();

            return $candidate;
        });
        if (! $batch) {
            return false;
        }

        $connector = $batch->connector()->withoutGlobalScope('organization')->with('organization')->first();
        $context = app(OrganizationContext::class);

        try {
            if (! $connector || $connector->provider !== ConnectorProvider::MerakiLocation) {
                throw new \RuntimeException('El conector Meraki del lote no existe.');
            }
            $context->set($connector->organization);
            if ($connector->status !== ConnectorStatus::Active || ! $connector->organization?->active) {
                $batch->forceFill([
                    'processing_status' => 'ignored',
                    'processed_at' => now(),
                    'processing_error' => 'Conector u organizacion inactiva.',
                ])->save();

                return true;
            }

            $payload = $this->payload($batch);
            $majorVersion = (int) explode('.', (string) ($payload['version'] ?? ''))[0];
            $records = $normalizer->records($payload, $majorVersion);
            if ($records === []) {
                throw ValidationException::withMessages(['data.observations' => 'El lote no contiene observaciones validas.']);
            }
            $records = $this->avoidRepeatedAccessPoints($records);

            $configuredNetwork = trim((string) ($connector->configuration['network_id'] ?? ''));
            if ($configuredNetwork !== '' && ! collect($records)->every(
                fn (array $record): bool => ($record['network_id'] ?? '') === $configuredNetwork,
            )) {
                throw ValidationException::withMessages(['network_id' => 'El networkId no corresponde al configurado.']);
            }

            [$accepted, $duplicates] = $this->storeEvents(
                $batch,
                $connector->id,
                $connector->organization_id,
                $records,
            );

            $connector->forceFill(['last_activity_at' => now(), 'last_error' => null])->save();
            $connector->logActivity('meraki_payload_received', 'Observaciones recibidas desde Cisco Meraki.', 'info', [
                'accepted' => $accepted,
                'duplicates' => $duplicates,
                'version' => $payload['version'] ?? null,
                'batch_id' => $batch->id,
            ]);
            // This table is only a durable HTTP inbox. Once every normalized event
            // has been persisted idempotently, retaining the original batch adds
            // database growth without providing further processing value.
            $batch->delete();

            return true;
        } catch (Throwable $exception) {
            $batch->forceFill([
                'processing_status' => 'failed',
                'processed_at' => now(),
                'processing_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            try {
                $connector?->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
            } catch (Throwable $connectorException) {
                Log::warning('No fue posible actualizar el ultimo error del conector Meraki.', [
                    'batch_id' => $batch->id,
                    'connector_id' => $batch->connector_id,
                    'exception' => $connectorException::class,
                ]);
            }
            Log::error('Fallo el procesamiento de un lote webhook Meraki.', [
                'batch_id' => $batch->id,
                'connector_id' => $batch->connector_id,
                'exception' => $exception::class,
            ]);

            return true;
        } finally {
            $context->set(null);
        }
    }

    /** @return array<string, mixed> */
    private function payload(MerakiWebhookBatch $batch): array
    {
        $payload = $batch->payload;
        if (is_array($payload)) {
            return $payload;
        }

        $payload = $batch->getRawOriginal('payload');
        for ($level = 0; $level < 2 && is_string($payload); $level++) {
            $payload = json_decode($payload, true);
        }

        if (! is_array($payload)) {
            throw new \UnexpectedValueException('El lote Meraki no contiene un payload JSON valido.');
        }

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return array{int, int}
     */
    private function storeEvents(
        MerakiWebhookBatch $batch,
        string $connectorId,
        string $organizationId,
        array $records,
    ): array
    {
        $uniqueRecords = [];
        foreach ($records as $record) {
            $uniqueRecords[$this->externalEventId($record)] = $record;
        }

        $existingEventIds = collect(array_keys($uniqueRecords))
            ->chunk(500)
            ->flatMap(fn ($ids) => DB::table('telemetry_events')
                ->where('connector_id', $connectorId)
                ->whereIn('external_event_id', $ids)
                ->pluck('external_event_id'))
            ->mapWithKeys(fn ($id): array => [(string) $id => true])
            ->all();

        $now = now();
        $candidateRows = [];
        foreach ($uniqueRecords as $externalEventId => $record) {
            if (isset($existingEventIds[$externalEventId])) {
                continue;
            }
            $candidateRows[] = [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'connector_id' => $connectorId,
                'device_id' => null,
                'external_event_id' => $externalEventId,
                'event_type' => 'meraki_location',
                'observed_at' => filled($record['observed_at'] ?? null)
                    ? TelemetryTimestamp::parseProviderTime($record['observed_at'])
                    : null,
                'received_at' => $batch->received_at,
                'processed_at' => null,
                'normalized_payload' => null,
                'raw_payload' => json_encode($record, JSON_THROW_ON_ERROR),
                'processing_status' => 'pending',
                'processing_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Each chunk commits independently. A transaction around the entire payload kept
        // foreign-key locks on the connector while new Meraki webhooks were arriving.
        foreach (array_chunk($candidateRows, 100) as $rows) {
            DB::table('telemetry_events')->insertOrIgnore($rows);
        }

        $candidateIds = array_column($candidateRows, 'id');
        $insertedIds = collect($candidateIds)
            ->chunk(500)
            ->flatMap(fn ($ids) => DB::table('telemetry_events')->whereIn('id', $ids)->pluck('id'))
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
        $accepted = count($insertedIds);
        return [$accepted, count($records) - $accepted];
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
     * Meraki includes the same global AP inventory in every normalized observation.
     * Keeping it once per batch avoids multiplying a megabyte-sized payload hundreds of times.
     *
     * @param list<array<string, mixed>> $records
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
