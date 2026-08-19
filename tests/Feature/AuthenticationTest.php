<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    public function test_user_can_request_a_temporary_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Olvidé mi contraseña');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Enviar enlace temporal');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_request_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'unknown@example.com']);

        $response->assertRedirect()->assertSessionHas(
            'status',
            'Si existe una cuenta asociada a ese correo, recibirás un enlace temporal para restablecer tu contraseña.'
        );
        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_a_valid_single_use_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'old-secure-password']);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $this->get(route('password.reset', [
                    'token' => $notification->token,
                    'email' => $user->email,
                ]))
                    ->assertOk()
                    ->assertSee('Restablecer contraseña');

                $response = $this->post(route('password.store'), [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'new-secure-password',
                    'password_confirmation' => 'new-secure-password',
                ]);

                $response->assertRedirect(route('login'))->assertSessionHas('status');
                $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));

                $this->post(route('password.store'), [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'another-secure-password',
                    'password_confirmation' => 'another-secure-password',
                ])->assertSessionHasErrors('email');

                return true;
            }
        );
    }

    public function test_invalid_login_shows_a_safe_credentials_message(): void
    {
        User::factory()->create([
            'email' => 'usuario@example.test',
            'password' => 'correct-password',
        ]);

        $this->from(route('login'))->post('/login', [
            'email' => 'usuario@example.test',
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'La combinación de correo y contraseña no es válida.',
            ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('La combinación de correo y contraseña no es válida.');

        $this->assertGuest();
    }

    public function test_viewer_cannot_manage_connectors(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($user)->get(route('connectors.index'))->assertForbidden();
    }
}
