<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\Organization;
use App\Models\TelemetryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneMerakiHistoryIncrementallyTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deletes_only_the_requested_quantity_and_profiles_cascades(): void
    {
        $connector = $this->connector();
        foreach (range(1, 3) as $index) {
            $event = $this->oldEvent($connector, $index);
            $event->signalObservations()->create([
                'organization_id' => $connector->organization_id,
                'transmitter_mac' => 'AABBCCDDEE'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'receiver_identifier' => '001122334455',
                'rssi' => -60,
                'observed_at' => $event->observed_at,
            ]);
        }

        $this->artisan('loratrack:prune-meraki-history-incremental', [
            '--limit' => 1,
            '--profile' => true,
        ])
            ->expectsOutputToContain('Eventos Meraki eliminados: 1')
            ->expectsOutputToContain('Observaciones eliminadas por cascada')
            ->expectsOutputToContain('se alcanzo el limite solicitado')
            ->assertSuccessful();

        $this->assertDatabaseCount('telemetry_events', 2);
        $this->assertDatabaseCount('signal_observations', 2);
    }

    public function test_incremental_dry_run_never_deletes_records(): void
    {
        $connector = $this->connector();
        $this->oldEvent($connector, 1);

        $this->artisan('loratrack:prune-meraki-history-incremental', [
            '--limit' => 1,
            '--dry-run' => true,
            '--profile' => true,
        ])
            ->expectsOutputToContain('no elimina registros')
            ->expectsOutputToContain('Eventos que seleccionaria este ciclo: 1')
            ->expectsOutputToContain('Tiempo total')
            ->assertSuccessful();

        $this->assertDatabaseCount('telemetry_events', 1);
    }

    public function test_command_prunes_old_received_event_without_observed_timestamp(): void
    {
        $connector = $this->connector();
        $event = $this->oldEvent($connector, 1);
        $event->forceFill(['observed_at' => null])->save();

        $this->artisan('loratrack:prune-meraki-history-incremental', ['--limit' => 1])
            ->expectsOutputToContain('Eventos Meraki eliminados: 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('telemetry_events', ['id' => $event->id]);
    }

    private function connector(): Connector
    {
        $organization = Organization::query()->create([
            'name' => 'Retencion incremental',
            'slug' => 'retencion-incremental-'.uniqid(),
        ]);

        return Connector::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Meraki incremental',
            'kind' => 'telemetry',
            'provider' => 'meraki_location',
            'status' => 'active',
        ]);
    }

    private function oldEvent(Connector $connector, int $index): TelemetryEvent
    {
        return TelemetryEvent::query()->create([
            'organization_id' => $connector->organization_id,
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', 'incremental-'.$index),
            'event_type' => 'meraki_location',
            'observed_at' => now()->subDays(7)->subMinutes($index),
            'received_at' => now()->subDays(7)->subMinutes($index),
            'raw_payload' => [],
            'processing_status' => 'processed',
        ]);
    }
}
