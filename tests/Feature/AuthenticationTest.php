<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_create_an_isolated_company_account(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Crear mi empresa');

        $response = $this->post(route('registration.store'), [
            'organization_name' => 'Operación Norte',
            'email' => 'admin@norte.test',
            'password' => 'A-secure-password-2026',
            'password_confirmation' => 'A-secure-password-2026',
        ]);

        $response->assertRedirect(route('dashboard'));
        $user = User::query()->where('email', 'admin@norte.test')->firstOrFail();
        $organization = Organization::query()->where('name', 'Operación Norte')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => UserRole::Admin->value,
        ]);
        $this->assertSame($organization->id, session('organization_id'));
    }

    public function test_registration_does_not_attach_an_existing_email_to_another_company(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->from(route('register'))->post(route('registration.store'), [
            'organization_name' => 'Empresa no creada',
            'email' => 'existing@example.com',
            'password' => 'A-secure-password-2026',
            'password_confirmation' => 'A-secure-password-2026',
        ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('class="toast-region"', false)
            ->assertSee('Revisa la informacion ingresada');

        $this->assertDatabaseMissing('organizations', ['name' => 'Empresa no creada']);
    }

    public function test_user_can_log_in_with_email_and_password(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer, 'password' => 'secret-password']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_viewer_cannot_manage_connectors(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($user)->get(route('connectors.index'))->assertForbidden();
    }
}
