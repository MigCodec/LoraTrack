<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AlertSetting;
use App\Models\Asset;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\PositionEstimate;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_create_and_evaluate_a_zone_rule(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $location = Location::query()->create(['name' => 'Piso', 'type' => 'floor']);
        $plan = FloorPlan::query()->create(['location_id' => $location->id, 'name' => 'Plano', 'file_path' => 'plan.png', 'original_name' => 'plan.png', 'mime_type' => 'image/png', 'width_meters' => 10, 'height_meters' => 10]);
        $zone = Zone::query()->create(['floor_plan_id' => $plan->id, 'name' => 'Restringida', 'color' => '#14B8A6', 'x_min' => .1, 'y_min' => .1, 'x_max' => .8, 'y_max' => .8]);
        $asset = Asset::query()->create(['asset_tag' => 'T-1', 'name' => 'Tracker 1', 'status' => 'active']);

        $this->actingAs($supervisor)->post(route('alert-rules.store'), [
            'name' => 'Ingreso restringido', 'enabled' => 1, 'subject_type' => 'asset', 'subject_id' => $asset->id,
            'trigger_type' => 'zone_entry', 'zone_id' => $zone->id, 'cooldown_minutes' => 5,
            'actions' => ['create_alert'], 'recipient_roles' => ['supervisor'],
        ])->assertRedirect();

        AlertSetting::current()->update(['enabled' => true, 'enabled_types' => []]);
        PositionEstimate::query()->create(['asset_id' => $asset->id, 'location_id' => $location->id, 'floor_plan_id' => $plan->id, 'zone_id' => $zone->id, 'algorithm' => 'test', 'algorithm_version' => '1', 'x' => 5, 'y' => 5, 'confidence' => .9, 'calculated_at' => now()]);
        $this->artisan('loratrack:evaluate-alerts')->assertSuccessful();

        $this->assertDatabaseHas('alert_rules', ['name' => 'Ingreso restringido', 'zone_id' => $zone->id]);
        $this->assertDatabaseHas('alerts', ['type' => 'custom_rule', 'title' => 'Ingreso restringido']);
    }
}
