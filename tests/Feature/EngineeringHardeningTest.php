<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_include_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'none'; object-src 'none'");
    }

    public function test_mutations_are_audited_with_request_id_without_sensitive_values(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->withHeader('X-Request-ID', 'engineering-test-01')->post(route('locations.store'), [
            'name' => 'Planta auditada', 'type' => 'floor', '_token' => 'secret-csrf-value',
        ]);

        $response->assertRedirect()->assertHeader('X-Request-ID', 'engineering-test-01');
        $log = AuditLog::query()->firstOrFail();
        $this->assertSame('locations.store', $log->route_name);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertNotContains('_token', $log->context['input_fields']);
        $this->assertStringNotContainsString('secret-csrf-value', json_encode($log->context));
    }

    public function test_operational_health_is_available_to_technical_roles_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $engineer = User::factory()->create(['role' => UserRole::Engineer]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($admin)->get(route('operations.health'))->assertOk()->assertSee('Salud operacional');
        $this->actingAs($engineer)->get(route('operations.health'))->assertOk()->assertDontSee('Auditoría reciente');
        $this->actingAs($supervisor)->get(route('operations.health'))->assertOk()->assertDontSee('Auditoría reciente');
        $this->actingAs($viewer)->get(route('operations.health'))->assertForbidden();
    }

    public function test_tracker_cannot_be_installed_as_a_fixed_anchor(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $location = Location::query()->create(['name' => 'Piso', 'type' => 'floor']);
        $plan = FloorPlan::query()->create(['location_id' => $location->id, 'name' => 'Plano', 'disk' => 'local', 'file_path' => 'missing.png', 'original_name' => 'missing.png', 'mime_type' => 'image/png', 'width_meters' => 10, 'height_meters' => 10]);
        $tracker = Device::query()->create(['identifier' => 'TRACKER-X', 'name' => 'Tracker', 'type' => 'lorawan_tracker']);

        $this->actingAs($admin)->post(route('installations.store', $plan), [
            'device_id' => $tracker->id, 'x_normalized' => .5, 'y_normalized' => .5,
            'reference_rssi' => -59, 'path_loss_exponent' => 2,
        ])->assertSessionHasErrors('device_id');
    }

    public function test_last_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => UserRole::Operator->value,
        ])->assertStatus(422);

        $this->assertTrue($admin->fresh()->isAdmin());
    }
}
