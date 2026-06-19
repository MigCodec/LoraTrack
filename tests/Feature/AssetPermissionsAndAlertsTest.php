<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\AlertSetting;
use App\Models\Asset;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AssetPermissionsAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_and_assign_a_valid_tracker_to_mobile_asset(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $tracker = Device::query()->create(['identifier' => 'B1000-01', 'name' => 'Tracker B1000', 'type' => 'lorawan_tracker']);

        $this->actingAs($operator)->post(route('assets.store'), [
            'asset_tag' => 'MOV-001', 'name' => 'Montacargas 1', 'mobility' => 'mobile', 'status' => 'active',
        ])->assertRedirect();

        $asset = Asset::query()->firstOrFail();
        $this->actingAs($operator)->post(route('asset-assignments.store', $asset), [
            'device_id' => $tracker->id, 'tracking_strategy' => 'fixed_beacons_mobile_tracker',
        ])->assertRedirect();

        $this->assertDatabaseHas('asset_device_assignments', ['asset_id' => $asset->id, 'device_id' => $tracker->id, 'ended_at' => null]);
    }

    public function test_viewer_cannot_mutate_assets(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)->post(route('assets.store'), [
            'asset_tag' => 'NO-001', 'name' => 'Sin permiso', 'mobility' => 'static', 'status' => 'active',
        ])->assertForbidden();
    }

    public function test_alert_evaluation_ignores_passive_beacons_and_does_not_repeat_email(): void
    {
        Mail::fake();
        AlertSetting::query()->create([
            'enabled' => true, 'recipients' => ['ops@example.com'], 'offline_minutes' => 20,
            'minimum_confidence' => .25, 'enabled_types' => ['device_offline'],
        ]);
        Device::query()->create(['identifier' => 'BEACON-01', 'name' => 'Beacon pasivo', 'type' => 'beacon']);
        Device::query()->create(['identifier' => 'TRACKER-01', 'name' => 'Tracker', 'type' => 'lorawan_tracker']);

        $this->artisan('loratrack:evaluate-alerts')->assertSuccessful();
        $firstNotification = Alert::query()->firstOrFail()->notified_at;
        $this->artisan('loratrack:evaluate-alerts')->assertSuccessful();

        $this->assertSame(1, Alert::query()->count());
        $this->assertSame('device_offline', Alert::query()->firstOrFail()->type);
        $this->assertNotNull($firstNotification);
        $this->assertTrue($firstNotification->equalTo(Alert::query()->firstOrFail()->notified_at));
    }
}
