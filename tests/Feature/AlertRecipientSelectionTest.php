<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AlertSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertRecipientSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_recipients_are_selected_from_active_company_members(): void
    {
        $supervisor = User::factory()->create(['name' => 'Supervisora', 'email' => 'supervisor@example.test']);
        $member = User::factory()->create(['name' => 'Operador Activo', 'email' => 'activo@example.test']);
        $expired = User::factory()->create(['name' => 'Cuenta Vencida', 'email' => 'vencido@example.test']);
        $outsider = User::factory()->create(['name' => 'Usuario Externo', 'email' => 'externo@example.test']);
        $organization = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'alertas-empresa-uno']);
        $otherOrganization = Organization::query()->create(['name' => 'Empresa Dos', 'slug' => 'alertas-empresa-dos']);
        $organization->memberships()->create(['user_id' => $supervisor->id, 'role' => UserRole::Supervisor]);
        $organization->memberships()->create(['user_id' => $member->id, 'role' => UserRole::Operator]);
        $organization->memberships()->create(['user_id' => $expired->id, 'role' => UserRole::Viewer, 'expires_at' => now()->subMinute()]);
        $otherOrganization->memberships()->create(['user_id' => $outsider->id, 'role' => UserRole::Viewer]);

        $this->actingAs($supervisor)->withSession(['organization_id' => $organization->id])
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Operador Activo')
            ->assertSee('activo@example.test')
            ->assertDontSee('Cuenta Vencida')
            ->assertDontSee('Usuario Externo')
            ->assertSee('data-recipient-picker', false)
            ->assertSee('css/alerts.css', false)
            ->assertSee('Buscar por nombre, correo o rol');

        $this->put(route('alerts.update'), [
            'enabled' => '1',
            'recipient_user_ids' => [$member->id],
            'offline_minutes' => 20,
            'minimum_confidence' => 0.25,
            'enabled_types' => ['device_offline'],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(['activo@example.test'], AlertSetting::query()->firstOrFail()->recipients);
    }

    public function test_alert_recipient_from_another_company_is_rejected(): void
    {
        $supervisor = User::factory()->create();
        $outsider = User::factory()->create(['email' => 'externo@example.test']);
        $organization = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'alertas-validacion-uno']);
        $otherOrganization = Organization::query()->create(['name' => 'Empresa Dos', 'slug' => 'alertas-validacion-dos']);
        $organization->memberships()->create(['user_id' => $supervisor->id, 'role' => UserRole::Supervisor]);
        $otherOrganization->memberships()->create(['user_id' => $outsider->id, 'role' => UserRole::Viewer]);
        AlertSetting::query()->create([
            'organization_id' => $organization->id,
            'enabled' => false,
            'recipients' => [],
            'offline_minutes' => 20,
            'minimum_confidence' => 0.25,
            'enabled_types' => [],
        ]);

        $this->actingAs($supervisor)->withSession(['organization_id' => $organization->id])
            ->put(route('alerts.update'), [
                'recipient_user_ids' => [$outsider->id],
                'offline_minutes' => 20,
                'minimum_confidence' => 0.25,
                'enabled_types' => [],
            ])->assertSessionHasErrors('recipient_user_ids');

        $this->assertSame([], AlertSetting::query()->firstOrFail()->recipients);
    }
}
