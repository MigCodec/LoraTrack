<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_engineering_has_technical_view_without_administration(): void
    {
        $engineer = User::factory()->create(['role' => UserRole::Engineer]);

        $this->actingAs($engineer)->get(route('dashboard'))
            ->assertOk()->assertSee('Ingeniería')->assertSee('Decoders de payload')->assertDontSee('Usuarios y grupos');
        $this->actingAs($engineer)->get(route('payload-profiles.index'))->assertOk();
        $this->actingAs($engineer)->get(route('operations.health'))->assertOk();
        $this->actingAs($engineer)->get(route('connectors.index'))->assertForbidden();
        $this->actingAs($engineer)->get(route('assets.create'))->assertForbidden();
    }

    public function test_supervisor_controls_operation_and_alerts_but_not_engineering(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $this->actingAs($supervisor)->get(route('dashboard'))
            ->assertOk()->assertSee('Supervisión')->assertSee('Alertas')->assertDontSee('Decoders de payload');
        $this->actingAs($supervisor)->get(route('assets.create'))->assertOk();
        $this->actingAs($supervisor)->get(route('alerts.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('payload-profiles.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('connectors.index'))->assertForbidden();
    }

    public function test_operator_and_viewer_have_distinct_mutation_access(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($operator)->get(route('assets.create'))->assertOk();
        $this->actingAs($operator)->get(route('alerts.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('assets.index'))->assertOk();
        $this->actingAs($viewer)->get(route('map.index'))->assertOk();
        $this->actingAs($viewer)->get(route('assets.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('operations.health'))->assertForbidden();
    }

    public function test_only_administrator_sees_connector_and_user_administration(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Administración')
            ->assertSee('Conectores')
            ->assertSee('Usuarios y grupos')
            ->assertSee('data-responsive-nav', false)
            ->assertSee('data-nav-toggle', false)
            ->assertSee('aria-controls="sidebar-navigation"', false)
            ->assertSee('aria-expanded="false"', false);
        $this->actingAs($admin)->get(route('connectors.index'))->assertOk();
        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee('Usuarios, grupos y permisos');
    }
}
