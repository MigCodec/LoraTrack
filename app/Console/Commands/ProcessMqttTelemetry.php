<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesTenantProcessingLimits;
use App\Enums\ConnectorProvider;
use App\Jobs\ProcessTtiUplink;
use App\Models\TelemetryEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMqttTelemetry extends Command
{
    use ResolvesTenantProcessingLimits;

    protected $signature = 'loratrack:process-mqtt-telemetry {--limit= : Sobrescribe el limite por organizacion (1 a 1000)}';

    protected $description = 'Procesa mensajes MQTT pendientes usando el limite de cada organizacion.';

    public function handle(): int
    {
        $override = $this->optionalIntegerOption('limit', 1, 1000);
        if ($override === -1) {
            return self::INVALID;
        }

        $eventIds = collect();
        foreach ($this->tenantLimits('mqtt_message_limit', 10, $override) as $organizationId => $limit) {
            $eventIds->push(...TelemetryEvent::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('event_type', 'mqtt')
                ->where('processing_status', 'pending')
                ->whereHas('connector', fn ($query) => $query->withoutGlobalScopes()->where('provider', ConnectorProvider::Mqtt->value))
                ->orderBy('received_at')
                ->limit($limit)
                ->pluck('id'));
        }

        $processed = 0;
        $failed = 0;
        foreach ($eventIds as $eventId) {
            try {
                app()->call([new ProcessTtiUplink((string) $eventId), 'handle']);
                $processed++;
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('El scheduler no pudo procesar un mensaje MQTT.', [
                    'telemetry_event_id' => (string) $eventId,
                    'exception' => $exception::class,
                ]);
            }
        }

        $this->info("Mensajes MQTT procesados: {$processed}; fallidos: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
