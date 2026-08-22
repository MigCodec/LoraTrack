<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiAccessPointRegistrar;
use App\Connectors\Meraki\MerakiClientDeviceRegistrar;
use App\Jobs\ProcessMerakiLocationObservation;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Positioning\ZoneClassifier;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMerakiObservations extends Command
{
    protected $signature = 'loratrack:process-meraki-observations
        {--limit=1000 : Cantidad máxima de observaciones}
        {--connector= : Limita el procesamiento a un conector Meraki}
        {--profile : Muestra tiempos y consultas para diagnosticar rendimiento}';

    protected $description = 'Procesa observaciones Meraki pendientes desde el scheduler.';

    public function handle(
        MerakiAccessPointRegistrar $accessPoints,
        MerakiClientDeviceRegistrar $clients,
        ZoneClassifier $zones,
    ): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 10000],
        ]);
        if ($limit === false) {
            $this->error('--limit debe ser un entero entre 1 y 10000.');

            return self::FAILURE;
        }

        $connectorId = trim((string) $this->option('connector'));
        $profile = (bool) $this->option('profile');
        $queryCount = 0;
        $queryTimeMs = 0.0;
        $queryGroups = [];
        if ($profile) {
            DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs, &$queryGroups): void {
                $queryCount++;
                $queryTimeMs += $query->time;
                $group = $this->queryGroup($query->sql);
                $queryGroups[$group] ??= ['count' => 0, 'time_ms' => 0.0];
                $queryGroups[$group]['count']++;
                $queryGroups[$group]['time_ms'] += $query->time;
            });
        }

        $commandStartedAt = hrtime(true);
        $idSelectionStartedAt = hrtime(true);
        if ($profile) {
            $this->line('Perfil: seleccionando eventos pending...');
        }
        $processingCandidates = $this->queueCandidates('processing', $connectorId, $limit);
        $pendingCandidates = $this->queueCandidates('pending', $connectorId, $limit);
        if ($profile) {
            $this->line('Perfil: seleccionando eventos failed reintentables...');
        }
        $failedCandidates = $this->queueCandidates('failed', $connectorId, $limit);
        $eventIds = $processingCandidates
            ->concat($pendingCandidates)
            ->concat($failedCandidates)
            ->take($limit)
            ->pluck('id')
            ->values();
        $idSelectionDurationMs = $this->elapsedMs($idSelectionStartedAt);
        if ($profile) {
            $this->line(sprintf(
                'Perfil: %d IDs seleccionados en %.1f ms; cargando payloads...',
                $eventIds->count(),
                $idSelectionDurationMs,
            ));
        }

        $payloadLoadStartedAt = hrtime(true);
        $eventsById = TelemetryEvent::query()
            ->select([
                'id', 'organization_id', 'connector_id', 'device_id', 'event_type',
                'observed_at', 'received_at', 'processed_at', 'normalized_payload', 'raw_payload',
                'processing_status', 'processing_attempts', 'processing_error', 'payload_storage_version',
            ])
            ->with(['organization', 'connector'])
            ->whereKey($eventIds)
            ->get()
            ->keyBy('id');
        $payloadLoadDurationMs = $this->elapsedMs($payloadLoadStartedAt);
        if ($profile) {
            $this->line(sprintf('Perfil: payloads cargados en %.1f ms; procesando eventos...', $payloadLoadDurationMs));
        }
        $events = $eventIds
            ->map(fn (string $eventId): ?TelemetryEvent => $eventsById->get($eventId))
            ->filter();

        $processed = 0;
        $failed = 0;
        $successfulConnectorIds = [];
        $failedConnectorIds = [];
        $processor = new ProcessMerakiLocationObservation('batch');
        $eventProfiles = [];
        foreach ($events as $event) {
            $eventStartedAt = hrtime(true);
            $queriesBefore = $queryCount;
            $queryTimeBefore = $queryTimeMs;
            if ($profile) {
                $this->line('Perfil: procesando evento '.(string) $event->id.'...');
            }
            try {
                $processor->process($event, $zones, $accessPoints, $clients, false);
                $processed++;
                if ($event->processing_status === 'processed') {
                    $successfulConnectorIds[$event->connector_id] = true;
                }
            } catch (Throwable $exception) {
                $failed++;
                $failedConnectorIds[$event->connector_id] = true;
                Log::warning('El scheduler no pudo procesar una observacion Meraki.', [
                    'telemetry_event_id' => (string) $event->id,
                    'exception' => $exception::class,
                ]);
            } finally {
                if ($profile) {
                    $eventProfiles[] = [
                        'event' => (string) $event->id,
                        'duration_ms' => $this->elapsedMs($eventStartedAt),
                        'queries' => $queryCount - $queriesBefore,
                        'query_ms' => $queryTimeMs - $queryTimeBefore,
                    ];
                }
            }
        }

        $connectorsWithoutFailures = array_diff(
            array_keys($successfulConnectorIds),
            array_keys($failedConnectorIds),
        );
        if ($connectorsWithoutFailures !== []) {
            Connector::query()
                ->whereIn('id', $connectorsWithoutFailures)
                ->update(['last_success_at' => now(), 'last_error' => null]);
        }

        $this->info("Observaciones Meraki procesadas: {$processed}; fallidas: {$failed}.");

        if ($profile) {
            $this->renderProfile(
                $eventProfiles,
                $queryGroups,
                $idSelectionDurationMs,
                $payloadLoadDurationMs,
                $this->elapsedMs($commandStartedAt),
                $queryCount,
                $queryTimeMs,
            );
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function elapsedMs(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    /** @return Collection<int, TelemetryEvent> */
    private function queueCandidates(string $status, string $connectorId, int $limit): Collection
    {
        return TelemetryEvent::query()
            ->select(['id', 'received_at'])
            ->where('event_type', 'meraki_location')
            ->where('processing_status', $status)
            ->when($status === 'failed', fn (Builder $query) => $query->where('processing_attempts', '<', 3))
            ->when($connectorId !== '', fn (Builder $query) => $query->where('connector_id', $connectorId))
            ->orderBy('received_at')
            ->limit($limit)
            ->get();
    }

    private function queryGroup(string $sql): string
    {
        $normalized = mb_strtolower($sql);
        foreach (['telemetry_events', 'devices', 'signal_observations', 'asset_device_assignments', 'meraki_floor_plan_mappings', 'position_estimates', 'connectors'] as $table) {
            if (str_contains($normalized, $table)) {
                return $table;
            }
        }

        return 'otras';
    }

    /**
     * @param list<array{event: string, duration_ms: float, queries: int, query_ms: float}> $events
     * @param array<string, array{count: int, time_ms: float}> $groups
     */
    private function renderProfile(
        array $events,
        array $groups,
        float $idSelectionDurationMs,
        float $payloadLoadDurationMs,
        float $totalDurationMs,
        int $queryCount,
        float $queryTimeMs,
    ): void {
        $this->newLine();
        $this->components->info('Perfil de rendimiento');
        $this->table(['Metrica', 'Valor'], [
            ['Carga inicial', number_format($idSelectionDurationMs + $payloadLoadDurationMs, 1).' ms'],
            ['Seleccion de IDs', number_format($idSelectionDurationMs, 1).' ms'],
            ['Carga de payloads', number_format($payloadLoadDurationMs, 1).' ms'],
            ['Tiempo total', number_format($totalDurationMs, 1).' ms'],
            ['Consultas SQL', number_format($queryCount)],
            ['Tiempo informado por SQL', number_format($queryTimeMs, 1).' ms'],
            ['Tiempo fuera de SQL', number_format(max(0, $totalDurationMs - $queryTimeMs), 1).' ms'],
        ]);

        uasort($groups, fn (array $left, array $right): int => $right['time_ms'] <=> $left['time_ms']);
        $this->table(
            ['Grupo SQL', 'Consultas', 'Tiempo'],
            collect($groups)->map(fn (array $data, string $group): array => [
                $group,
                number_format($data['count']),
                number_format($data['time_ms'], 1).' ms',
            ])->values()->all(),
        );

        usort($events, fn (array $left, array $right): int => $right['duration_ms'] <=> $left['duration_ms']);
        $this->table(
            ['Evento lento', 'Duracion', 'Consultas', 'Tiempo SQL'],
            collect($events)->take(10)->map(fn (array $event): array => [
                $event['event'],
                number_format($event['duration_ms'], 1).' ms',
                number_format($event['queries']),
                number_format($event['query_ms'], 1).' ms',
            ])->all(),
        );
    }
}
