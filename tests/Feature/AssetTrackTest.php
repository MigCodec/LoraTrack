<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\PositionEstimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_track_view_and_data_show_historical_positions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        $location = Location::query()->create(['name' => 'Piso 1', 'type' => 'floor']);
        $plan = FloorPlan::query()->create([
            'location_id' => $location->id,
            'name' => 'Planta norte',
            'file_path' => 'floor-plans/test.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'width_meters' => 20,
            'height_meters' => 10,
        ]);
        $asset = Asset::query()->create(['asset_tag' => 'A-TRACK-1', 'name' => 'Montacargas 1', 'mobility' => 'mobile']);

        $old = $this->position($asset, $location, $plan, 2, 2, now()->subHours(2));
        $latest = $this->position($asset, $location, $plan, 8, 5, now()->subMinutes(5), 0.91);

        $this->actingAs($user)
            ->get(route('assets.track', $asset))
            ->assertOk()
            ->assertSee('Montacargas 1')
            ->assertSee('Ver en mapa operativo')
            ->assertSee('Actualizar en vivo');

        $this->actingAs($user)
            ->getJson(route('assets.track.data', ['asset' => $asset, 'floor_plan_id' => $plan->id, 'range' => '24h']))
            ->assertOk()
            ->assertJsonPath('asset.id', $asset->id)
            ->assertJsonPath('floor_plan.id', $plan->id)
            ->assertJsonPath('positions.0.id', $old->id)
            ->assertJsonPath('positions.1.id', $latest->id)
            ->assertJsonPath('positions.1.x_meters', 8)
            ->assertJsonPath('positions.1.confidence', 0.91);
    }

    public function test_live_track_data_returns_only_positions_after_timestamp(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        $location = Location::query()->create(['name' => 'Piso 1', 'type' => 'floor']);
        $plan = FloorPlan::query()->create([
            'location_id' => $location->id,
            'name' => 'Planta norte',
            'file_path' => 'floor-plans/test.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'width_meters' => 20,
            'height_meters' => 10,
        ]);
        $asset = Asset::query()->create(['asset_tag' => 'A-TRACK-2', 'name' => 'Carro estático', 'mobility' => 'static']);
        $first = $this->position($asset, $location, $plan, 1, 1, now()->subMinutes(10));
        $second = $this->position($asset, $location, $plan, 3, 4, now()->subMinutes(2));

        $this->actingAs($user)
            ->getJson(route('assets.track.data', [
                'asset' => $asset,
                'floor_plan_id' => $plan->id,
                'range' => '24h',
                'after' => $first->calculated_at->toIso8601String(),
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'positions')
            ->assertJsonPath('positions.0.id', $second->id);
    }

    public function test_asset_list_and_map_payload_include_track_link(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        $location = Location::query()->create(['name' => 'Piso 1', 'type' => 'floor']);
        $plan = FloorPlan::query()->create([
            'location_id' => $location->id,
            'name' => 'Planta norte',
            'file_path' => 'floor-plans/test.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'width_meters' => 20,
            'height_meters' => 10,
        ]);
        $asset = Asset::query()->create(['asset_tag' => 'A-TRACK-3', 'name' => 'Transpaleta', 'mobility' => 'mobile']);
        $this->position($asset, $location, $plan, 6, 6, now()->subMinute());

        $this->actingAs($user)
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Ver recorrido');

        $this->actingAs($user)
            ->getJson(route('map.data', $plan))
            ->assertOk()
            ->assertJsonPath('positions.0.track_url', route('assets.track', ['asset' => $asset, 'plan' => $plan]));
    }

    private function position(Asset $asset, Location $location, FloorPlan $plan, float $x, float $y, mixed $calculatedAt, float $confidence = 0.75): PositionEstimate
    {
        return PositionEstimate::query()->create([
            'asset_id' => $asset->id,
            'location_id' => $location->id,
            'floor_plan_id' => $plan->id,
            'algorithm' => 'assigned_position',
            'algorithm_version' => '1.0',
            'x' => $x,
            'y' => $y,
            'confidence' => $confidence,
            'accuracy_meters' => 0.75,
            'calculated_at' => $calculatedAt,
            'evidence' => [],
        ]);
    }
}
