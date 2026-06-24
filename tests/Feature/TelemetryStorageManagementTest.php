<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConnectorKind;
use App\Models\Asset;
use App\Models\Connector;
use App\Models\Organization;
use App\Models\PositionEstimate;
use App\Models\TelemetryEvent;
use App\Telemetry\DatabaseStorageInspector;
use App\Telemetry\DatabaseStorageUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class TelemetryStorageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_storage_pressure_deletes_only_old_telemetry_from_opted_in_tenants(): void
    {
        $enabled = Organization::query()->create([
            'name' => 'Limpieza habilitada',
            'slug' => 'limpieza-habilitada',
            'storage_cleanup_enabled' => true,
            'telemetry_retention_days' => 30,
        ]);
        $disabled = Organization::query()->create([
            'name' => 'Limpieza deshabilitada',
            'slug' => 'limpieza-deshabilitada',
            'storage_cleanup_enabled' => false,
            'telemetry_retention_days' => 30,
        ]);
        $enabledConnector = $this->connector($enabled);
        $disabledConnector = $this->connector($disabled);
        $oldEnabled = $this->event($enabled, $enabledConnector, 'old-enabled', now()->subDays(45));
        $recentEnabled = $this->event($enabled, $enabledConnector, 'recent-enabled', now()->subDays(5));
        $oldDisabled = $this->event($disabled, $disabledConnector, 'old-disabled', now()->subDays(45));
        $oldEnabled->signalObservations()->create([
            'organization_id' => $enabled->id,
            'transmitter_mac' => 'AABBCCDDEEFF',
            'receiver_identifier' => '001122334455',
            'rssi' => -65,
            'observed_at' => $oldEnabled->observed_at,
        ]);
        $asset = Asset::query()->create([
            'organization_id' => $enabled->id,
            'asset_tag' => 'KEEP-POSITION',
            'name' => 'Posición conservada',
        ]);
        $position = PositionEstimate::query()->create([
            'organization_id' => $enabled->id,
            'asset_id' => $asset->id,
            'telemetry_event_id' => $oldEnabled->id,
            'algorithm' => 'meraki_location',
            'algorithm_version' => '3.0',
            'x' => 1,
            'y' => 2,
            'calculated_at' => now()->subDays(45),
        ]);
        $this->mock(DatabaseStorageInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->once()->andReturn(
                new DatabaseStorageUsage(600, 400, 60.0, 'test'),
            );
        });

        $this->artisan('loratrack:manage-telemetry-storage')->assertSuccessful();

        $this->assertDatabaseMissing('telemetry_events', ['id' => $oldEnabled->id]);
        $this->assertDatabaseMissing('signal_observations', ['telemetry_event_id' => $oldEnabled->id]);
        $this->assertDatabaseHas('telemetry_events', ['id' => $recentEnabled->id]);
        $this->assertDatabaseHas('telemetry_events', ['id' => $oldDisabled->id]);
        $this->assertDatabaseHas('position_estimates', ['id' => $position->id, 'telemetry_event_id' => null]);
        $enabled->refresh();
        $this->assertSame(1, $enabled->storage_cleanup_deleted_events);
        $this->assertSame(60.0, $enabled->last_storage_utilization_percent);
        $this->assertNotNull($enabled->storage_cleanup_at);
        $this->assertNull($disabled->fresh()->storage_checked_at);
    }

    public function test_storage_at_or_below_fifty_percent_never_deletes_data(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Empresa',
            'slug' => 'empresa',
            'storage_cleanup_enabled' => true,
            'telemetry_retention_days' => 7,
        ]);
        $connector = $this->connector($organization);
        $event = $this->event($organization, $connector, 'old', now()->subYear());
        $this->mock(DatabaseStorageInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->once()->andReturn(
                new DatabaseStorageUsage(500, 500, 50.0, 'test'),
            );
        });

        $this->artisan('loratrack:manage-telemetry-storage')->assertSuccessful();

        $this->assertDatabaseHas('telemetry_events', ['id' => $event->id]);
        $this->assertNull($organization->fresh()->storage_cleanup_at);
    }

    public function test_dry_run_measures_but_does_not_delete(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Empresa',
            'slug' => 'empresa-dry',
            'storage_cleanup_enabled' => true,
        ]);
        $event = $this->event($organization, $this->connector($organization), 'old', now()->subYear());
        $this->mock(DatabaseStorageInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->once()->andReturn(
                new DatabaseStorageUsage(900, 100, 90.0, 'test'),
            );
        });

        $this->artisan('loratrack:manage-telemetry-storage', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('telemetry_events', ['id' => $event->id]);
        $this->assertSame(90.0, $organization->fresh()->last_storage_utilization_percent);
    }

    private function connector(Organization $organization): Connector
    {
        return Connector::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Telemetry',
            'kind' => ConnectorKind::Telemetry,
            'provider' => 'tti_webhook',
        ]);
    }

    private function event(
        Organization $organization,
        Connector $connector,
        string $externalId,
        mixed $observedAt,
    ): TelemetryEvent {
        return TelemetryEvent::query()->create([
            'organization_id' => $organization->id,
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', $externalId),
            'event_type' => 'uplink',
            'observed_at' => $observedAt,
            'received_at' => $observedAt,
            'raw_payload' => ['test' => true],
            'processing_status' => 'processed',
        ]);
    }
}
