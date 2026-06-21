<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_a_user_with_the_active_organization_branding(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['name' => 'Administradora Principal']);
        $organization = Organization::query()->create([
            'name' => 'Empresa Búsqueda',
            'slug' => 'empresa-busqueda',
            'primary_color' => '#112233',
            'secondary_color' => '#223344',
            'accent_color' => '#AABBCC',
        ]);
        $organization->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->post(route('user-invitations.store'), [
                'email' => 'Invitado@Example.test',
                'role' => UserRole::Operator->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $invitation = OrganizationInvitation::query()->where('email', 'invitado@example.test')->firstOrFail();
        $invitedUser = User::query()->where('email', 'invitado@example.test')->firstOrFail();
        $this->assertSame($organization->id, $invitation->organization_id);
        $this->assertSame(UserRole::Operator, $invitation->role);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $invitedUser->id,
            'role' => UserRole::Operator->value,
        ]);

        Mail::assertQueued(OrganizationInvitationMail::class, function (OrganizationInvitationMail $mail) use ($invitation): bool {
            $this->assertTrue($mail->hasTo('invitado@example.test'));
            $this->assertSame('Empresa Búsqueda', $mail->organizationName);
            $this->assertSame('Administradora Principal', $mail->administratorName);
            $this->assertSame('Operador', $mail->roleLabel);
            $this->assertSame('#112233', $mail->primaryColor);
            $this->assertSame('#AABBCC', $mail->accentColor);
            $html = $mail->render();
            $this->assertStringContainsString('Empresa Búsqueda', $html);
            $this->assertStringContainsString('Administradora Principal', $html);
            $this->assertStringContainsString('background:#112233', $html);
            $this->assertStringContainsString('background:#AABBCC', $html);
            $token = basename((string) parse_url($mail->invitationUrl, PHP_URL_PATH));
            $this->assertSame($invitation->token_hash, hash('sha256', $token));

            return true;
        });
    }

    public function test_invitation_can_be_accepted_only_once_and_activates_the_invited_account(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $organization = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'empresa-uno']);
        $organization->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->post(route('user-invitations.store'), ['email' => 'persona@example.test', 'role' => UserRole::Viewer->value]);
        $mail = Mail::queued(OrganizationInvitationMail::class)->first();
        $token = basename((string) parse_url($mail->invitationUrl, PHP_URL_PATH));
        $this->post(route('logout'));

        $this->get(route('invitations.accept', $token))->assertOk()->assertSee('Empresa Uno');
        $this->post(route('invitations.store', $token), [
            'name' => 'Persona Invitada',
            'password' => 'password-segura-123',
            'password_confirmation' => 'password-segura-123',
        ])->assertRedirect(route('dashboard'));

        $invitation = OrganizationInvitation::query()->where('email', 'persona@example.test')->firstOrFail();
        $this->assertNotNull($invitation->accepted_at);
        $this->assertAuthenticatedAs(User::query()->where('email', 'persona@example.test')->firstOrFail());
        $this->get(route('invitations.accept', $token))->assertNotFound();
    }

    public function test_non_admin_cannot_invite_and_other_tenant_invitations_are_not_listed(): void
    {
        $viewer = User::factory()->create();
        $first = Organization::query()->create(['name' => 'Empresa Visible', 'slug' => 'empresa-visible']);
        $second = Organization::query()->create(['name' => 'Empresa Oculta', 'slug' => 'empresa-oculta']);
        $first->memberships()->create(['user_id' => $viewer->id, 'role' => UserRole::Viewer]);
        OrganizationInvitation::query()->create([
            'organization_id' => $second->id,
            'email' => 'oculto@example.test',
            'role' => UserRole::Viewer,
            'token_hash' => hash('sha256', 'token-oculto'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($viewer)->withSession(['organization_id' => $first->id])
            ->post(route('user-invitations.store'), ['email' => 'nuevo@example.test', 'role' => UserRole::Viewer->value])
            ->assertForbidden();

        $admin = User::factory()->create();
        $first->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $this->actingAs($admin)->withSession(['organization_id' => $first->id])
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('oculto@example.test');
    }
}
