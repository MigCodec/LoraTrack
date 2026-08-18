<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesTenantProcessingLimits;
use App\Connectors\Meraki\MerakiAccessPointRegistrar;
use App\Connectors\Meraki\MerakiClientDeviceRegistrar;
use App\Connectors\Meraki\MerakiEventPayloadCompactor;
use App\Jobs\ProcessMerakiLocationObservation;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Positioning\ZoneClassifier;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMerakiObservations extends Command
{
    use ResolvesTenantProcessingLimits;

    protected $signature = 'loratrack:process-meraki-observations
        {--limit= : Sobrescribe el limite por organizacion (1 a 100000)}
        {--profile : Muestra tiempos y consultas para diagnosticar rendimiento}';

    protected $description = 'Procesa observaciones Meraki pendientes desde el scheduler.';

    public function handle(
        MerakiAccessPointRegistrar $accessPoints,
        MerakiClientDeviceRegistrar $clients,
        MerakiEventPayloadCompactor $payloadCompactor,
        ZoneClassifier $zones,
    ): int
    {
        $override = $this->optionalIntegerOption('limit', 1, 100000);
        if ($override === -1) {
            return self::INVALID;
        }

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
        // Select only the covering-index columns while ordering. Pulling large JSON
        // payloads through this query makes MariaDB materialize/sort them before LIMIT.
        $eventIds = collect();
        foreach ($this->tenantLimits('meraki_observation_limit', 100, $override) as $organizationId => $limit) {
            $eventIds->push(...TelemetryEvent::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('event_type', 'meraki_location')
                ->where('processing_status', 'pending')
                ->orderBy('received_at')
                ->limit($limit)
                ->pluck('id'));
        }
        $idSelectionDurationMs = $this->elapsedMs($idSelectionStartedAt);

        $payloadLoadStartedAt = hrtime(true);
        $eventsById = TelemetryEvent::query()
            ->select([
                'id', 'organization_id', 'connector_id', 'device_id', 'event_type',
                'observed_at', 'received_at', 'processed_at', 'raw_payload',
                'processing_status', 'processing_error',
            ])
            ->with(['organization', 'connector'])
            ->whereKey($eventIds)
            ->get();
        $payloadLoadDurationMs = $this->elapsedMs($payloadLoadStartedAt);
        $eventsById = $eventsById->keyBy('id');
        $events = $eventIds
            ->map(fn (string $eventId): ?TelemetryEvent => $eventsById->get($eventId))
            ->filter();
        $loadDurationMs = $idSelectionDurationMs + $payloadLoadDurationMs;

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
            try {
                $processor->process(
                    $event,
                    $zones,
                    $accessPoints,
                    $clients,
                    false,
                    $payloadCompactor,
                );
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
                $loadDurationMs,
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
        float $loadDurationMs,
        float $idSelectionDurationMs,
        float $payloadLoadDurationMs,
        float $totalDurationMs,
        int $queryCount,
        float $queryTimeMs,
    ): void {
        $this->newLine();
        $this->components->info('Perfil de rendimiento');
        $this->table(['Metrica', 'Valor'], [
            ['Carga inicial', number_format($loadDurationMs, 1).' ms'],
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
