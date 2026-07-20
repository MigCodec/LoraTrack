<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiPayloadNormalizer;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Jobs\ProcessMerakiLocationObservation;
use App\Models\MerakiWebhookBatch;
use App\Models\TelemetryEvent;
use App\Support\TelemetryTimestamp;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessMerakiWebhookBatches extends Command
{
    protected $signature = 'loratrack:process-meraki-webhooks {--limit=100 : Cantidad maxima de lotes a procesar}';

    protected $description = 'Normaliza los lotes Meraki recibidos y crea eventos de telemetria idempotentes.';

    public function handle(MerakiPayloadNormalizer $normalizer): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);
        if ($limit === false) {
            $this->error('--limit debe ser un entero entre 1 y 1000.');

            return self::FAILURE;
        }

        $batchIds = MerakiWebhookBatch::query()
            ->withoutGlobalScope('organization')
            ->where('processing_status', 'pending')
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

    private function processBatch(string $batchId, MerakiPayloadNormalizer $normalizer): bool
    {
        $batch = DB::transaction(function () use ($batchId): ?MerakiWebhookBatch {
            $candidate = MerakiWebhookBatch::query()
                ->withoutGlobalScope('organization')
                ->lockForUpdate()
                ->find($batchId);
            if (! $candidate || $candidate->processing_status !== 'pending') {
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

            $payload = $batch->payload;
            $majorVersion = (int) explode('.', (string) ($payload['version'] ?? ''))[0];
            $records = $normalizer->records($payload, $majorVersion);
            if ($records === []) {
                throw ValidationException::withMessages(['data.observations' => 'El lote no contiene observaciones validas.']);
            }

            $configuredNetwork = trim((string) ($connector->configuration['network_id'] ?? ''));
            if ($configuredNetwork !== '' && ! collect($records)->every(
                fn (array $record): bool => ($record['network_id'] ?? '') === $configuredNetwork,
            )) {
                throw ValidationException::withMessages(['network_id' => 'El networkId no corresponde al configurado.']);
            }

            [$accepted, $duplicates] = DB::transaction(function () use ($batch, $connector, $records): array {
                $accepted = 0;
                $duplicates = 0;
                foreach ($records as $record) {
                    $externalEventId = hash('sha256', implode('|', [
                        $record['version'], $record['type'], $record['network_id'], $record['client_mac'],
                        $record['observed_at'], $record['external_floor_plan_id'], $record['x'], $record['y'],
                    ]));
                    $event = TelemetryEvent::query()->firstOrCreate(
                        ['connector_id' => $connector->id, 'external_event_id' => $externalEventId],
                        [
                            'event_type' => 'meraki_location',
                            'observed_at' => filled($record['observed_at'])
                                ? TelemetryTimestamp::parseProviderTime($record['observed_at'])
                                : null,
                            'received_at' => $batch->received_at,
                            'raw_payload' => $record,
                            'processing_status' => 'pending',
                        ],
                    );
                    if ($event->wasRecentlyCreated) {
                        ProcessMerakiLocationObservation::dispatch($event->id)->afterCommit();
                        $accepted++;
                    } else {
                        $duplicates++;
                    }
                }

                return [$accepted, $duplicates];
            });

            $connector->forceFill(['last_activity_at' => now(), 'last_error' => null])->save();
            $connector->logActivity('meraki_payload_received', 'Observaciones recibidas desde Cisco Meraki.', 'info', [
                'accepted' => $accepted,
                'duplicates' => $duplicates,
                'version' => $payload['version'] ?? null,
                'batch_id' => $batch->id,
            ]);
            $batch->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'processing_error' => null,
            ])->save();

            return true;
        } catch (Throwable $exception) {
            $batch->forceFill([
                'processing_status' => 'failed',
                'processed_at' => now(),
                'processing_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            $connector?->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
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
}
