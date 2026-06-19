<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connector;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\TelemetryEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FloorPlanZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_plan_and_draw_normalized_rectangle(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $location = Location::query()->create(['name' => 'Piso 1', 'type' => 'floor']);

        $response = $this->actingAs($admin)->post(route('floor-plans.store'), [
            'location_id' => $location->id,
            'name' => 'Planta principal',
            'plan' => UploadedFile::fake()->image('plano.png', 1200, 800),
            'width_meters' => 60,
            'height_meters' => 40,
        ]);

        $plan = FloorPlan::query()->firstOrFail();
        $response->assertRedirect(route('floor-plans.index', ['plan' => $plan]));
        Storage::disk('local')->assertExists($plan->file_path);
        $this->get(route('floor-plans.file', $plan))->assertOk();
        $this->get(route('floor-plans.index', ['plan' => $plan]))
            ->assertOk()
            ->assertSee('id="zone-mode"', false)
            ->assertSee('id="ribbon-anchor-mode"', false)
            ->assertSee('id="zone-form"', false);

        $this->actingAs($admin)->post(route('zones.store', $plan), [
            'name' => 'Bodega Z',
            'code' => 'ZONE-Z',
            'color' => '#78A22F',
            'x_min' => 0.1,
            'y_min' => 0.2,
            'x_max' => 0.5,
            'y_max' => 0.7,
        ])->assertRedirect();

        $this->assertDatabaseHas('zones', ['floor_plan_id' => $plan->id, 'name' => 'Bodega Z']);
        $this->get(route('floor-plans.index', ['plan' => $plan]))
            ->assertOk()
            ->assertSee('id="saved-zone-overlay"', false)
            ->assertSee('class="saved-zone"', false)
            ->assertSee('Bodega Z');
        $this->get(route('map.index', ['plan' => $plan]))
            ->assertOk()
            ->assertSee('1 áreas definidas')
            ->assertSee('class="map-zone saved-zone"', false)
            ->assertSee('Bodega Z');

        $zone = $plan->zones()->firstOrFail();
        $this->actingAs($admin)->put(route('zones.update', $zone), [
            'name' => 'Bodega Norte', 'code' => 'NORTH', 'color' => '#005B82',
        ])->assertRedirect();
        $this->assertDatabaseHas('zones', ['id' => $zone->id, 'name' => 'Bodega Norte', 'code' => 'NORTH', 'color' => '#005B82']);
        $this->assertEquals(.1, $zone->fresh()->x_min);

        $device = Device::query()->create([
            'identifier' => 'AABBCCDDEEFF',
            'name' => 'Beacon fijo 1',
            'type' => 'beacon',
        ]);
        $this->actingAs($admin)->post(route('installations.store', $plan), [
            'device_id' => $device->id,
            'x_normalized' => 0.5,
            'y_normalized' => 0.25,
            'reference_rssi' => -59,
            'path_loss_exponent' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('device_installations', [
            'device_id' => $device->id,
            'x' => 30,
            'y' => 10,
        ]);
        $this->get(route('floor-plans.index', ['plan' => $plan]))
            ->assertOk()
            ->assertSee('id="saved-anchor-overlay"', false)
            ->assertSee('class="plan-anchor"', false)
            ->assertSee('data-editor-layer="beacons"', false)
            ->assertSee('Beacon fijo 1');
        $this->get(route('map.index', ['plan' => $plan]))
            ->assertOk()
            ->assertSee('data-map-layer="beacons"', false);
        $this->getJson(route('map.data', $plan))
            ->assertOk()
            ->assertJsonPath('anchors.0.name', 'Beacon fijo 1')
            ->assertJsonPath('anchors.0.x', 0.5)
            ->assertJsonPath('anchors.0.y', 0.25);
    }

    public function test_reported_sensecap_macs_can_be_selected_or_entered_when_placing_beacon(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $location = Location::query()->create(['name' => 'Piso BLE', 'type' => 'floor']);
        $plan = FloorPlan::query()->create([
            'location_id' => $location->id,
            'name' => 'Plano BLE',
            'file_path' => 'floor-plans/ble.png',
            'original_name' => 'ble.png',
            'mime_type' => 'image/png',
            'width_meters' => 20,
            'height_meters' => 10,
        ]);
        $tracker = Device::query()->create([
            'identifier' => '2CF7F1C073100560', 'name' => 'sensecap-001', 'type' => 'lorawan_tracker', 'last_seen_at' => now(),
        ]);
        $connector = Connector::query()->create([
            'name' => 'TTI Bodega', 'kind' => 'telemetry', 'provider' => 'tti_webhook', 'status' => 'active',
        ]);
        TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'device_id' => $tracker->id,
            'external_event_id' => hash('sha256', 'sensecap-floor-plan'),
            'event_type' => 'uplink',
            'received_at' => now(),
            'processing_status' => 'processed',
            'normalized_payload' => ['decoded' => ['messages' => [[
                ['measurementId' => '5002', 'measurementValue' => [
                    ['mac' => '58:BE:6F:65:9D:9D', 'rssi' => '-90'],
                ], 'type' => 'BLE Scan'],
            ]]]],
            'raw_payload' => ['end_device_ids' => ['device_id' => 'sensecap-001']],
        ]);

        $this->actingAs($admin)->get(route('floor-plans.index', ['plan' => $plan]))
            ->assertOk()
            ->assertSee('58:BE:6F:65:9D:9D')
            ->assertSee('sensecap-001')
            ->assertSee('TTI Bodega');

        $this->actingAs($admin)->post(route('installations.store', $plan), [
            'device_identifier' => '58:BE:6F:65:9D:9D',
            'device_name' => 'Beacon acceso norte',
            'x_normalized' => 0.25,
            'y_normalized' => 0.5,
            'reference_rssi' => -59,
            'path_loss_exponent' => 2,
        ])->assertRedirect();

        $beacon = Device::query()->where('identifier', '58BE6F659D9D')->firstOrFail();
        $this->assertSame('Beacon acceso norte', $beacon->name);
        $this->assertDatabaseHas('device_installations', ['device_id' => $beacon->id, 'location_id' => $location->id, 'floor_plan_id' => $plan->id, 'x' => 5, 'y' => 5]);

        $this->actingAs($admin)->get(route('floor-plans.index', ['plan' => $plan]))
            ->assertOk()
            ->assertViewHas('reportedBeaconMacs', fn ($macs): bool => $macs->isEmpty())
            ->assertViewHas('devices', fn ($devices): bool => ! $devices->contains('id', $beacon->id));

        $this->actingAs($admin)->post(route('installations.store', $plan), [
            'device_identifier' => '58:BE:6F:65:9D:9D',
            'x_normalized' => 0.5,
            'y_normalized' => 0.5,
            'reference_rssi' => -59,
            'path_loss_exponent' => 2,
        ])->assertSessionHasErrors('device_identifier');

        $secondPlan = FloorPlan::query()->create([
            'location_id' => $location->id,
            'name' => 'Plano BLE alternativo',
            'file_path' => 'floor-plans/ble-2.png',
            'original_name' => 'ble-2.png',
            'mime_type' => 'image/png',
            'width_meters' => 20,
            'height_meters' => 10,
        ]);
        $this->actingAs($admin)->get(route('floor-plans.index', ['plan' => $secondPlan]))
            ->assertOk()
            ->assertViewHas('reportedBeaconMacs', fn ($macs): bool => $macs->contains('identifier', '58:BE:6F:65:9D:9D'))
            ->assertViewHas('devices', fn ($devices): bool => $devices->contains('id', $beacon->id));

        $this->actingAs($admin)->post(route('installations.store', $secondPlan), [
            'device_id' => $beacon->id,
            'x_normalized' => 0.75,
            'y_normalized' => 0.5,
            'reference_rssi' => -59,
            'path_loss_exponent' => 2,
        ])->assertRedirect();
        $this->assertSame(2, DeviceInstallation::query()->where('device_id', $beacon->id)->whereNull('ended_at')->count());
    }
}
