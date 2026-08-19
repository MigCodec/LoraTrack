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
        Artisan::call('loratrack:process-meraki-webhooks', [
            '--batch' => $this->batchId,
            '--limit' => 1,
        ]);

        for ($pass = 0; $pass < 100; $pass++) {
            $pending = $this->eligibleObservations();
            if ($pending === 0) {
                $this->synchronizeCounters();

                return;
            }

            Artisan::call('loratrack:process-meraki-observations', [
                '--connector' => $this->connectorId,
                '--limit' => min(1000, $pending),
            ]);
        }

        Log::warning('El procesamiento posterior a la respuesta Meraki alcanzó su límite de seguridad.', [
            'batch_id' => $this->batchId,
            'connector_id' => $this->connectorId,
            'remaining_observations' => $this->eligibleObservations(),
        ]);
        $this->synchronizeCounters();
    }

    private function eligibleObservations(): int
    {
        return TelemetryEvent::query()
            ->withoutGlobalScope('organization')
            ->where('connector_id', $this->connectorId)
            ->where('event_type', 'meraki_location')
            ->where(function ($query): void {
                $query->where('processing_status', 'pending')
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
