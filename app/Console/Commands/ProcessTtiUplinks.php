<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ConnectorProvider;
use App\Jobs\ProcessTtiUplink;
use App\Models\TelemetryEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTtiUplinks extends Command
{
    protected $signature = 'loratrack:process-tti-uplinks {--limit=500 : Cantidad máxima de uplinks}';

    protected $description = 'Procesa uplinks TTI pendientes y reintenta fallos recuperables desde el scheduler.';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 10000],
        ]);
        if ($limit === false) {
            $this->error('--limit debe ser un entero entre 1 y 10000.');

            return self::FAILURE;
        }

        $eventIds = TelemetryEvent::query()
            ->where('event_type', 'uplink')
            ->where(function ($query): void {
                $query->where('processing_status', 'pending')
                    ->orWhere(function ($failed): void {
                        $failed->where('processing_status', 'failed')->where('processing_attempts', '<', 3);
                    });
            })
            ->whereHas('connector', fn ($query) => $query->where('provider', ConnectorProvider::TtiWebhook->value))
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
                Log::warning('El scheduler no pudo procesar un uplink TTI.', [
                    'telemetry_event_id' => (string) $eventId,
                    'exception' => $exception::class,
                ]);
            }
        }

        $this->info("Uplinks TTI procesados: {$processed}; fallidos: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
