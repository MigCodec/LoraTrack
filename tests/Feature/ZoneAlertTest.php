<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\AlertSetting;
use App\Models\Asset;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\PositionEstimate;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ZoneAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_creation_no_longer_embeds_notification_rules(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$location, $plan] = $this->plan();

        $this->actingAs($admin)->post(route('zones.store', $plan), [
            'name' => 'Área segura', 'code' => 'SAFE', 'color' => '#78A22F',
            'x_min' => .1, 'y_min' => .1, 'x_max' => .8, 'y_max' => .8,
        ])->assertRedirect();

        $zone = Zone::query()->firstOrFail();
        $this->assertSame(0, $zone->alertRules()->count());
        $this->actingAs($admin)->get(route('floor-plans.index', ['plan' => $plan]))
            ->assertOk()->assertSee('Área segura')->assertDontSee('Notificaciones opcionales');
    }

    public function test_entry_and_exit_generate_alerts_for_rule_recipients(): void
    {
        Mail::fake();
        AlertSetting::query()->create(['enabled' => true, 'recipients' => [], 'offline_minutes' => 20, 'minimum_confidence' => .25, 'enabled_types' => []]);
        [$location, $plan] = $this->plan();
        $zone = Zone::query()->create(['floor_plan_id' => $plan->id, 'name' => 'Bodega', 'color' => '#78A22F', 'shape' => 'rectangle', 'x_min' => .1, 'y_min' => .1, 'x_max' => .8, 'y_max' => .8]);
        foreach (['entry', 'exit'] as $type) {
            $zone->alertRules()->create(['event_type' => $type, 'recipients' => ['zone@example.com']]);
        }
        $asset = Asset::query()->create(['asset_tag' => 'A-1', 'name' => 'Activo 1']);
        $this->position($asset, $location, $plan, $zone, now()->subMinute());

        $this->artisan('loratrack:evaluate-alerts')->assertSuccessful();
        $this->assertDatabaseHas('alerts', ['type' => 'zone_entry']);

        $this->position($asset, $location, $plan, null, now());
        $this->artisan('loratrack:evaluate-alerts')->assertSuccessful();
        $this->assertDatabaseHas('alerts', ['type' => 'zone_exit']);
        $this->assertSame(2, Alert::query()->whereNotNull('notified_at')->count());
    }

    private function plan(): array
    {
        $location = Location::query()->create(['name' => 'Piso', 'type' => 'floor']);
        $plan = FloorPlan::query()->create(['location_id' => $location->id, 'name' => 'Plano', 'disk' => 'local', 'file_path' => 'missing.png', 'original_name' => 'plano.png', 'mime_type' => 'image/png', 'width_meters' => 10, 'height_meters' => 10]);

        return [$location, $plan];
    }

    private function position(Asset $asset, Location $location, FloorPlan $plan, ?Zone $zone, mixed $at): void
    {
        PositionEstimate::query()->create(['asset_id' => $asset->id, 'location_id' => $location->id, 'floor_plan_id' => $plan->id, 'zone_id' => $zone?->id, 'algorithm' => 'test', 'algorithm_version' => '1', 'x' => $zone ? 5 : 9, 'y' => $zone ? 5 : 9, 'confidence' => .9, 'calculated_at' => $at]);
    }
}
