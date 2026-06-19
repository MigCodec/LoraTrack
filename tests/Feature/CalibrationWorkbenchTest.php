<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CalibrationRun;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalibrationWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_and_apply_calibration_in_meters(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $location = Location::query()->create(['name' => 'Piso', 'type' => 'floor']);
        $plan = FloorPlan::query()->create(['location_id' => $location->id, 'name' => 'Plano', 'disk' => 'local', 'file_path' => 'plan.png', 'original_name' => 'plan.png', 'mime_type' => 'image/png', 'width_meters' => 10, 'height_meters' => 10]);
        $anchors = [];
        foreach ([[0, 0], [10, 0], [0, 10], [10, 10]] as $index => [$x, $y]) {
            $device = Device::query()->create(['identifier' => 'BEACON-'.($index + 1), 'name' => 'Beacon '.($index + 1), 'type' => 'beacon']);
            $installation = DeviceInstallation::query()->create(['device_id' => $device->id, 'location_id' => $location->id, 'x' => $x, 'y' => $y, 'reference_rssi' => -59, 'path_loss_exponent' => 2, 'started_at' => now()]);
            $anchors[$installation->id] = ['rssi' => -76, 'reference_rssi' => -60, 'path_loss_exponent' => 2.2];
        }

        $this->actingAs($admin)->get(route('calibration.index', $plan))->assertOk()->assertSee('Banco de calibración RSSI');
        $this->actingAs($admin)->post(route('calibration.preview', $plan), [
            'name' => 'Centro', 'anchor_type' => 'beacon', 'expected_x' => 5, 'expected_y' => 5, 'anchors' => $anchors,
        ])->assertRedirect(route('calibration.index', $plan));

        $run = CalibrationRun::query()->firstOrFail();
        $this->assertLessThan(0.01, (float) $run->position_error_meters);
        $this->assertSame('draft', $run->status);

        $this->actingAs($admin)->post(route('calibration.apply', $run))->assertRedirect(route('calibration.index', $plan));

        $this->assertSame('applied', $run->fresh()->status);
        $this->assertSame(-60, DeviceInstallation::query()->firstOrFail()->reference_rssi);
        $this->assertSame(2.2, DeviceInstallation::query()->firstOrFail()->path_loss_exponent);
    }
}
