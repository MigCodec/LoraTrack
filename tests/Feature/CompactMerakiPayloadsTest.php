<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\Organization;
use App\Models\TelemetryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactMerakiPayloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_compacts_only_processed_meraki_events(): void
    {
        $connector = $this->connector();
        $processed = $this->event($connector, 'processed-event', 'processed');
        $pending = $this->event($connector, 'pending-event', 'pending');

        $this->artisan('loratrack:compact-meraki-payloads', [
            '--limit' => 10,
            '--batch-size' => 1,
            '--profile' => true,
        ])
            ->expectsOutputToContain('Eventos Meraki compactados: 1')
            ->expectsOutputToContain('Ahorro estimado')
            ->assertSuccessful();

        $processed->refresh();
        $pending->refresh();

        $this->assertSame(2, $processed->payload_storage_version);
        $this->assertArrayNotHasKey('rssi_records', $processed->normalized_payload);
        $this->assertArrayNotHasKey('reporting_aps', $processed->normalized_payload);
        $this->assertSame('checksum', $processed->raw_payload['source_summary']['payload_checksum']);
        $this->assertSame(1, $pending->payload_storage_version);
        $this->assertArrayHasKey('rssi_records', $pending->raw_payload);
    }

    public function test_dry_run_reports_savings_without_writing(): void
    {
        $event = $this->event($this->connector(), 'dry-run-event', 'processed');

        $this->artisan('loratrack:compact-meraki-payloads', [
            '--limit' => 1,
            '--dry-run' => true,
            '--profile' => true,
        ])
            ->expectsOutputToContain('Eventos Meraki analizados: 1')
            ->expectsOutputToContain('no modifica registros')
            ->assertSuccessful();

        $this->assertSame(1, $event->fresh()->payload_storage_version);
    }

    private function connector(): Connector
    {
        $organization = Organization::query()->create([
            'name' => 'Compactacion Meraki',
            'slug' => 'compactacion-meraki-'.uniqid(),
        ]);

        return Connector::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Meraki',
            'kind' => 'telemetry',
            'provider' => 'meraki_location',
            'status' => 'active',
        ]);
    }

    private function event(Connector $connector, string $externalId, string $status): TelemetryEvent
    {
        return TelemetryEvent::query()->create([
            'organization_id' => $connector->organization_id,
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', $externalId),
            'event_type' => 'meraki_location',
            'observed_at' => now()->subHour(),
            'received_at' => now()->subHour(),
            'processed_at' => $status === 'processed' ? now() : null,
            'processing_status' => $status,
            'raw_payload' => [
                'version' => '3.0',
                'type' => 'Bluetooth',
                'network_id' => 'network-1',
                'client_mac' => 'AABBCCDDEEFF',
                'observed_at' => now()->subHour()->toIso8601String(),
                'rssi_records' => [['apMac' => '001122334455', 'rssi' => -60]],
                'reporting_aps' => [['apMac' => '001122334455', 'apName' => 'AP 1']],
                'source_summary' => ['payload_checksum' => 'checksum', 'rssi_record_count' => 1],
            ],
            'normalized_payload' => [
                'client_name' => 'Beacon',
                'latitude' => -33.45,
                'longitude' => -70.66,
                'rssi_records' => [['apMac' => '001122334455', 'rssi' => -60]],
                'reporting_aps' => [['apMac' => '001122334455', 'apName' => 'AP 1']],
                'source_summary' => ['payload_checksum' => 'checksum', 'rssi_record_count' => 1],
            ],
        ]);
    }
}
