<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\FloorPlan;
use App\Models\Location;
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
    }
}
