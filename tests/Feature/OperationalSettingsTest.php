<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connector;
use App\Models\Organization;
use App\Models\TelemetryEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_updates_only_the_active_organization_settings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $first = Organization::query()->create(['name' => 'Primera', 'slug' => 'primera']);
        $second = Organization::query()->create(['name' => 'Segunda', 'slug' => 'segunda']);
        $first->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $second->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);

        $this->actingAs($admin)->withSession(['organization_id' => $first->id])
            ->put(route('settings.update'), [
                'meraki_retention_days' => 2,
                'storage_cleanup_enabled' => 1,
                'telemetry_retention_days' => 21,
                'storage_cleanup_threshold_percent' => 65.5,
                'storage_cleanup_max_events' => 2500,
            ])->assertRedirect();

        $first->refresh();
        $this->assertSame(2, $first->meraki_retention_days);
        $this->assertTrue($first->storage_cleanup_enabled);
        $this->assertSame(21, $first->telemetry_retention_days);
        $this->assertSame(65.5, $first->storage_cleanup_threshold_percent);
        $this->assertSame(2500, $first->storage_cleanup_max_events);
        $this->assertSame(2, $second->fresh()->meraki_retention_days);
    }

    public function test_meraki_pruning_removes_expired_pending_events_using_tenant_setting(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Retencion dos dias',
            'slug' => 'retencion-dos-dias',
            'meraki_retention_days' => 2,
        ]);
        $connector = Connector::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Meraki',
            'kind' => 'telemetry',
            'provider' => 'meraki_location',
            'status' => 'active',
        ]);
        $expired = $this->event($organization, $connector, 'expired', now()->subDays(3), 'pending');
        $recent = $this->event($organization, $connector, 'recent', now()->subDay(), 'pending');

        $this->artisan('loratrack:prune-meraki-history-incremental', ['--limit' => 10])->assertSuccessful();

        $this->assertDatabaseMissing('telemetry_events', ['id' => $expired->id]);
        $this->assertDatabaseHas('telemetry_events', ['id' => $recent->id]);
    }

    private function event(Organization $organization, Connector $connector, string $id, mixed $at, string $status): TelemetryEvent
    {
        return TelemetryEvent::query()->create([
            'organization_id' => $organization->id,
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', $id),
            'event_type' => 'meraki_location',
            'observed_at' => $at,
            'received_at' => $at,
            'raw_payload' => [],
            'processing_status' => $status,
        ]);
    }
}
