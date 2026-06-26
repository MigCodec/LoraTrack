<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\AlertSetting;
use App\Models\Asset;
use App\Models\Device;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetPermissionsAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_and_assign_a_valid_tracker_to_mobile_asset(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $tracker = Device::query()->create(['identifier' => 'B1000-01', 'name' => 'Tracker B1000', 'type' => 'lorawan_tracker']);

        $this->actingAs($operator)->get(route('assets.create', ['mobility' => 'mobile']))
            ->assertOk()
            ->assertSee('Tracker LoRaWAN inicial')
            ->assertSee('js-device-select')
            ->assertDontSee('Tracker B1000');
        $this->actingAs($operator)->getJson(route('assets.device-options', [
            'type' => 'lorawan_tracker',
            'q' => 'B1000',
        ]))->assertOk()->assertJsonPath('results.0.id', $tracker->id);
        $this->actingAs($operator)->post(route('assets.store'), [
            'asset_tag' => 'MOV-001', 'name' => 'Montacargas 1', 'mobility' => 'mobile', 'status' => 'active',
            'tracker_device_id' => $tracker->id,
            'photo' => UploadedFile::fake()->image('montacargas.jpg', 800, 600),
        ])->assertRedirect();

        $asset = Asset::query()->firstOrFail();
        $this->assertDatabaseHas('asset_device_assignments', ['asset_id' => $asset->id, 'device_id' => $tracker->id, 'ended_at' => null]);
        $this->assertNotNull($asset->fresh()->photo_path);
        Storage::disk('local')->assertExists($asset->fresh()->photo_path);
        $this->actingAs($operator)->get(route('assets.photo', $asset))->assertOk();
    }

    public function test_operator_can_create_static_asset_with_initial_beacon(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $beacon = Device::query()->create(['identifier' => 'AA:BB:CC:DD:EE:11', 'name' => 'Beacon vitrina', 'type' => 'beacon']);

        $this->actingAs($operator)->get(route('assets.create', ['mobility' => 'static']))
            ->assertOk()
            ->assertSee('Beacon BLE inicial')
            ->assertSee('js-device-select')
            ->assertDontSee('Beacon vitrina');

        $this->actingAs($operator)->getJson(route('assets.device-options', [
            'type' => 'beacon',
            'q' => 'AA:BB',
        ]))->assertOk()->assertJsonPath('results.0.id', $beacon->id);

        $this->actingAs($operator)->post(route('assets.store'), [
            'asset_tag' => 'STA-001',
            'name' => 'Vitrina 1',
            'mobility' => 'static',
            'status' => 'active',
            'static_beacon_device_id' => $beacon->id,
        ])->assertRedirect();

        $asset = Asset::query()->where('asset_tag', 'STA-001')->firstOrFail();
        $this->assertDatabaseHas('asset_device_assignments', [
            'asset_id' => $asset->id,
            'device_id' => $beacon->id,
            'tracking_strategy' => 'mobile_beacon_fixed_scanners',
            'ended_at' => null,
        ]);
    }

    public function test_device_options_do_not_return_the_full_list_without_search_text(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        Device::query()->create(['identifier' => 'AA:BB:CC:DD:EE:10', 'name' => 'Beacon bodega', 'type' => 'beacon']);

        $this->actingAs($operator)->getJson(route('assets.device-options', ['type' => 'beacon']))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_device_options_are_scoped_to_the_active_organization(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $first = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'empresa-uno']);
        $second = Organization::query()->create(['name' => 'Empresa Dos', 'slug' => 'empresa-dos']);
        $first->memberships()->create(['user_id' => $operator->id, 'role' => UserRole::Operator]);
        $second->memberships()->create(['user_id' => $operator->id, 'role' => UserRole::Operator]);

        $visible = Device::query()->create([
            'organization_id' => $first->id,
            'identifier' => 'AA:BB:CC:DD:EE:20',
            'name' => 'Beacon compartido visible',
            'type' => 'beacon',
        ]);
        $hidden = Device::query()->create([
            'organization_id' => $second->id,
            'identifier' => 'AA:BB:CC:DD:EE:21',
            'name' => 'Beacon compartido oculto',
            'type' => 'beacon',
        ]);

        $this->actingAs($operator)
            ->withSession(['organization_id' => $first->id])
            ->getJson(route('assets.device-options', ['type' => 'beacon', 'q' => 'compartido']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $visible->id)
            ->assertJsonMissing(['id' => $hidden->id]);
    }

    public function test_static_initial_beacon_cannot_reuse_assigned_beacon(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $beacon = Device::query()->create(['identifier' => 'AA:BB:CC:DD:EE:12', 'name' => 'Beacon ocupado', 'type' => 'beacon']);
        $assigned = Asset::query()->create(['asset_tag' => 'STA-OLD', 'name' => 'Estático previo', 'mobility' => 'static', 'status' => 'active']);
        $assigned->deviceAssignments()->create([
            'device_id' => $beacon->id,
            'tracking_strategy' => 'mobile_beacon_fixed_scanners',
            'started_at' => now(),
        ]);

        $this->actingAs($operator)->post(route('assets.store'), [
            'asset_tag' => 'STA-002',
            'name' => 'Vitrina 2',
            'mobility' => 'static',
            'status' => 'active',
            'static_beacon_device_id' => $beacon->id,
        ])->assertSessionHasErrors('static_beacon_device_id');
    }

    public function test_mobile_asset_offers_reported_trackers_by_readable_name_and_identifier(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $tracker = Device::query()->create([
            'identifier' => '2CF7F1C073100560',
            'name' => 'sensecap-001',
            'type' => 'lorawan_tracker',
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
        $asset = Asset::query()->create([
            'asset_tag' => 'MOV-SENSECAP', 'name' => 'Activo SenseCAP', 'mobility' => 'mobile', 'status' => 'active',
        ]);

        $this->actingAs($operator)->get(route('assets.edit', $asset))
            ->assertOk()
            ->assertSee('Tracker registrado')
            ->assertSee('js-device-select')
            ->assertDontSee($tracker->identifier);

        $this->actingAs($operator)->getJson(route('assets.device-options', [
            'type' => 'lorawan_tracker',
            'q' => '2CF7',
        ]))->assertOk()->assertJsonPath('results.0.id', $tracker->id);

        $this->actingAs($operator)->post(route('asset-assignments.store', $asset), [
            'device_identifier' => strtolower($tracker->identifier),
            'tracking_strategy' => 'fixed_beacons_mobile_tracker',
        ])->assertRedirect();

        $this->assertDatabaseHas('asset_device_assignments', [
            'asset_id' => $asset->id, 'device_id' => $tracker->id, 'tracking_strategy' => 'fixed_beacons_mobile_tracker', 'ended_at' => null,
        ]);
    }

    public function test_manual_sensecap_identifier_is_created_only_for_mobile_assets(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $mobile = Asset::query()->create([
            'asset_tag' => 'MOV-MANUAL', 'name' => 'Móvil manual', 'mobility' => 'mobile', 'status' => 'active',
        ]);
        $static = Asset::query()->create([
            'asset_tag' => 'STA-MANUAL', 'name' => 'Estático manual', 'mobility' => 'static', 'status' => 'active',
        ]);

        $this->actingAs($operator)->post(route('asset-assignments.store', $mobile), [
            'device_identifier' => '2cf7f1c073100561',
            'tracking_strategy' => 'fixed_beacons_mobile_tracker',
        ])->assertRedirect();
        $tracker = Device::query()->where('identifier', '2CF7F1C073100561')->firstOrFail();
        $this->assertSame('lorawan_tracker', $tracker->type);
        $this->assertSame('SenseCAP T1000-B', $tracker->model);

        $this->actingAs($operator)->from(route('assets.edit', $static))->post(route('asset-assignments.store', $static), [
            'device_identifier' => '2CF7F1C073100562',
            'tracking_strategy' => 'fixed_beacons_mobile_tracker',
        ])->assertRedirect(route('assets.edit', $static))->assertSessionHasErrors('device_identifier');
        $this->assertDatabaseMissing('devices', ['identifier' => '2CF7F1C073100562']);
    }

    public function test_viewer_cannot_mutate_assets(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)->post(route('assets.store'), [
            'asset_tag' => 'NO-001', 'name' => 'Sin permiso', 'mobility' => 'static', 'status' => 'active',
        ])->assertForbidden();
    }

    public function test_mobile_and_static_asset_lists_show_the_last_uplink_time(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $seenAt = now()->subMinutes(5)->startOfSecond();
        Asset::query()->create([
            'asset_tag' => 'MOV-SEEN', 'name' => 'Móvil visto', 'mobility' => 'mobile', 'status' => 'active', 'last_seen_at' => $seenAt,
        ]);
        Asset::query()->create([
            'asset_tag' => 'STA-SEEN', 'name' => 'Estático visto', 'mobility' => 'static', 'status' => 'active', 'last_seen_at' => $seenAt,
        ]);

        $this->actingAs($viewer)->get(route('assets.index', ['mobility' => 'mobile']))
            ->assertOk()->assertSee('Móvil visto')->assertSee('Última señal')->assertSee($seenAt->format('d/m/Y H:i:s'));
        $this->actingAs($viewer)->get(route('assets.index', ['mobility' => 'static']))
            ->assertOk()->assertSee('Estático visto')->assertSee('Última señal')->assertSee($seenAt->format('d/m/Y H:i:s'));
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
