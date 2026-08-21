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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class TelemetryStorageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_deletes_expired_data_only_from_opted_in_tenants(): void
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
        $latestPosition = PositionEstimate::query()->create([
            'organization_id' => $enabled->id,
            'asset_id' => $asset->id,
            'algorithm' => 'meraki_location',
            'algorithm_version' => '3.0',
            'x' => 3,
            'y' => 4,
            'calculated_at' => now()->subDays(5),
        ]);
        $inactiveAsset = Asset::query()->create([
            'organization_id' => $enabled->id,
            'asset_tag' => 'EXPIRED-LAST-POSITION',
            'name' => 'Activo sin posiciones recientes',
        ]);
        $expiredLastPosition = PositionEstimate::query()->create([
            'organization_id' => $enabled->id,
            'asset_id' => $inactiveAsset->id,
            'algorithm' => 'meraki_location',
            'algorithm_version' => '3.0',
            'x' => 5,
            'y' => 6,
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
        $this->assertDatabaseMissing('position_estimates', ['id' => $position->id]);
        $this->assertDatabaseHas('position_estimates', ['id' => $latestPosition->id]);
        $this->assertDatabaseMissing('position_estimates', ['id' => $expiredLastPosition->id]);
        $enabled->refresh();
        $this->assertSame(3, $enabled->storage_cleanup_deleted_events);
        $this->assertSame(60.0, $enabled->last_storage_utilization_percent);
        $this->assertNotNull($enabled->storage_cleanup_at);
        $this->assertNull($disabled->fresh()->storage_checked_at);
    }

    public function test_retention_runs_even_when_storage_is_at_fifty_percent(): void
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

        $this->assertDatabaseMissing('telemetry_events', ['id' => $event->id]);
        $this->assertNotNull($organization->fresh()->storage_cleanup_at);
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

    public function test_cleanup_removes_every_expired_status_and_preserves_only_work_inside_retention(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Recuperación',
            'slug' => 'recuperacion',
            'storage_cleanup_enabled' => true,
            'telemetry_retention_days' => 1,
            'terminal_inbox_retention_days' => 1,
        ]);
        $connector = $this->connector($organization);
        $pending = $this->event($organization, $connector, 'pending-old', now()->subDays(5));
        $pending->update(['processing_status' => 'pending']);
        $recentPending = $this->event($organization, $connector, 'pending-recent', now()->subHours(6));
        $recentPending->update(['processing_status' => 'pending']);
        $retryable = $this->event($organization, $connector, 'failed-retryable', now()->subDays(5));
        $retryable->update(['processing_status' => 'failed', 'processing_attempts' => 2]);
        $terminal = $this->event($organization, $connector, 'failed-terminal', now()->subDays(5));
        $terminal->update(['processing_status' => 'failed', 'processing_attempts' => 3]);
        $stuckId = (string) \Illuminate\Support\Str::ulid();
        DB::table('meraki_webhook_batches')->insert([
            'id' => $stuckId,
            'organization_id' => $organization->id,
            'connector_id' => $connector->id,
            'request_hash' => hash('sha256', 'stuck'),
            'payload' => '{}',
            'processing_status' => 'processing',
            'attempts' => 1,
            'received_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);
        $this->mock(DatabaseStorageInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->once()->andThrow(new \RuntimeException('Volumen remoto'));
        });

        $this->artisan('loratrack:manage-telemetry-storage')->assertSuccessful();

        $this->assertDatabaseMissing('telemetry_events', ['id' => $pending->id]);
        $this->assertDatabaseHas('telemetry_events', ['id' => $recentPending->id, 'processing_status' => 'pending']);
        $this->assertDatabaseMissing('telemetry_events', ['id' => $retryable->id]);
        $this->assertDatabaseMissing('telemetry_events', ['id' => $terminal->id]);
        $this->assertDatabaseMissing('meraki_webhook_batches', ['id' => $stuckId]);
    }

    public function test_command_fails_its_retention_audit_when_a_manual_limit_leaves_expired_rows(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Auditoría estricta',
            'slug' => 'auditoria-estricta',
            'storage_cleanup_enabled' => true,
            'telemetry_retention_days' => 1,
        ]);
        $connector = $this->connector($organization);
        $first = $this->event($organization, $connector, 'audit-old-1', now()->subDays(3));
        $second = $this->event($organization, $connector, 'audit-old-2', now()->subDays(2));
        $this->mock(DatabaseStorageInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->once()->andThrow(new \RuntimeException('Volumen remoto'));
        });

        $this->artisan('loratrack:manage-telemetry-storage', ['--max-delete' => 1])
            ->expectsOutputToContain('queda')
            ->assertFailed();

        $this->assertDatabaseMissing('telemetry_events', ['id' => $first->id]);
        $this->assertDatabaseHas('telemetry_events', ['id' => $second->id]);
    }

    public function test_profile_displays_effective_retention_cutoffs_and_expired_counts(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00 UTC');
        $organization = Organization::query()->create([
            'name' => 'Perfil retención',
            'slug' => 'perfil-retencion',
            'storage_cleanup_enabled' => true,
            'use_system_recommended_retention' => false,
            'telemetry_retention_days' => 6,
            'position_history_retention_days' => 30,
            'operational_log_retention_days' => 14,
            'terminal_inbox_retention_days' => 2,
        ]);
        $this->event($organization, $this->connector($organization), 'profile-old', now()->subDays(7));
        $this->mock(DatabaseStorageInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->once()->andReturn(new DatabaseStorageUsage(600, 400, 60.0, 'test'));
        });

        $this->artisan('loratrack:manage-telemetry-storage', ['--profile' => true, '--dry-run' => true])
            ->expectsOutputToContain('Políticas de retención efectivas')
            ->expectsOutputToContain('Perfil retención')
            ->expectsOutputToContain('6 días')
            ->expectsOutputToContain('2026-08-15 12:00:00')
            ->expectsOutputToContain('Antes de ejecutar')
            ->assertSuccessful();
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
