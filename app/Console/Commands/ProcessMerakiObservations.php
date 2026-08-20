<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiAccessPointRegistrar;
use App\Connectors\Meraki\MerakiClientDeviceRegistrar;
use App\Enums\ConnectorProvider;
use App\Jobs\ProcessMerakiLocationObservation;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Positioning\ZoneClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMerakiObservations extends Command
{
    protected $signature = 'loratrack:process-meraki-observations
        {--limit=1000 : Cantidad máxima de observaciones}
        {--connector= : Limita el procesamiento a un conector Meraki}';

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
        $eventIds = TelemetryEvent::query()
            ->where('event_type', 'meraki_location')
            ->where(function ($query): void {
                $query->where('processing_status', 'pending')
                    ->orWhere(function ($failed): void {
                        $failed->where('processing_status', 'failed')->where('processing_attempts', '<', 3);
                    });
            })
            ->whereHas('connector', fn ($query) => $query->where('provider', ConnectorProvider::MerakiLocation->value))
            ->when($connectorId !== '', fn ($query) => $query->where('connector_id', $connectorId))
            ->orderBy('received_at')
            ->limit($limit)
            ->pluck('id');

        $eventsById = TelemetryEvent::query()
            ->select([
                'id', 'organization_id', 'connector_id', 'device_id', 'event_type',
                'observed_at', 'received_at', 'processed_at', 'normalized_payload', 'raw_payload',
                'processing_status', 'processing_attempts', 'processing_error',
            ])
            ->with(['organization', 'connector'])
            ->whereKey($eventIds)
            ->get()
            ->keyBy('id');
        $events = $eventIds
            ->map(fn (string $eventId): ?TelemetryEvent => $eventsById->get($eventId))
            ->filter();

        $processed = 0;
        $failed = 0;
        $successfulConnectorIds = [];
        $failedConnectorIds = [];
        $processor = new ProcessMerakiLocationObservation('batch');
        foreach ($events as $event) {
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

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
