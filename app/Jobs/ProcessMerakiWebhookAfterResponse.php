<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TelemetryEvent;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessMerakiWebhookAfterResponse
{
    use Dispatchable;

    public function __construct(
        public readonly string $batchId,
        public readonly string $connectorId,
    ) {}

    public function handle(): void
    {
        try {
            $batchExitCode = Artisan::call('loratrack:process-meraki-webhooks', [
                '--batch' => $this->batchId,
                '--limit' => 1,
            ]);
            if ($batchExitCode !== 0) {
                throw new \RuntimeException("La normalizacion Meraki termino con codigo {$batchExitCode}.");
            }

            for ($pass = 0; $pass < 100; $pass++) {
                $pending = $this->eligibleObservations();
                if ($pending === 0) {
                    return;
                }

                $exitCode = Artisan::call('loratrack:process-meraki-observations', [
                    '--connector' => $this->connectorId,
                    '--limit' => min(1000, $pending),
                    '--include-processing' => true,
                ]);
                if ($exitCode !== 0 && $this->eligibleObservations() >= $pending) {
                    throw new \RuntimeException("El procesamiento Meraki termino con codigo {$exitCode} sin reducir los pendientes.");
                }
            }

            Log::warning('El procesamiento directo Meraki alcanzo su limite de seguridad.', [
                'batch_id' => $this->batchId,
                'connector_id' => $this->connectorId,
                'remaining_observations' => $this->eligibleObservations(),
            ]);
        } finally {
            $this->synchronizeCounters();
        }
    }

    private function eligibleObservations(): int
    {
        return TelemetryEvent::query()
            ->withoutGlobalScope('organization')
            ->where('connector_id', $this->connectorId)
            ->where('event_type', 'meraki_location')
            ->where(function ($query): void {
                $query->whereIn('processing_status', ['pending', 'processing'])
                    ->orWhere(function ($failed): void {
                        $failed->where('processing_status', 'failed')
                            ->where('processing_attempts', '<', 3);
                    });
            })
            ->count();
    }

    private function synchronizeCounters(): void
    {
        Artisan::call('loratrack:sync-telemetry-counters', [
            '--connector' => $this->connectorId,
        ]);
    }
}
