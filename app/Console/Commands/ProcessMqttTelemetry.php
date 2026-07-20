<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ConnectorProvider;
use App\Jobs\ProcessTtiUplink;
use App\Models\TelemetryEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMqttTelemetry extends Command
{
    protected $signature = 'loratrack:process-mqtt-telemetry {--limit=3 : Cantidad maxima de mensajes, entre 1 y 3}';

    protected $description = 'Procesa hasta tres mensajes MQTT pendientes desde el scheduler.';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 3],
        ]);
        if ($limit === false) {
            $this->error('--limit debe ser un entero entre 1 y 3.');

            return self::FAILURE;
        }

        $eventIds = TelemetryEvent::query()
            ->where('event_type', 'mqtt')
            ->where('processing_status', 'pending')
            ->whereHas('connector', fn ($query) => $query->where('provider', ConnectorProvider::Mqtt->value))
            ->orderBy('received_at')
            ->limit($limit)
            ->pluck('id');

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
