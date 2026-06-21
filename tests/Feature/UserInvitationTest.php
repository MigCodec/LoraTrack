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
                'access_type' => 'until',
                'membership_expires_at' => '2030-12-31',
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
        $this->assertSame('2030-12-31', $invitedUser->memberships()->firstOrFail()->expires_at?->toDateString());

        Mail::assertQueued(OrganizationInvitationMail::class, function (OrganizationInvitationMail $mail) use ($invitation): bool {
            $this->assertTrue($mail->hasTo('invitado@example.test'));
            $this->assertSame('Empresa Búsqueda', $mail->organizationName);
            $this->assertSame('Administradora Principal', $mail->administratorName);
            $this->assertSame('Operador', $mail->roleLabel);
            $this->assertSame('#112233', $mail->primaryColor);
            $this->assertSame('#AABBCC', $mail->accentColor);
            $this->assertStringContainsString('31 de diciembre de 2030', $mail->accessDuration);
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
            ->post(route('user-invitations.store'), ['email' => 'persona@example.test', 'role' => UserRole::Viewer->value, 'access_type' => 'permanent']);
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
            ->post(route('user-invitations.store'), ['email' => 'nuevo@example.test', 'role' => UserRole::Viewer->value, 'access_type' => 'permanent'])
            ->assertForbidden();

        $admin = User::factory()->create();
        $first->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $this->actingAs($admin)->withSession(['organization_id' => $first->id])
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('oculto@example.test');
    }

    public function test_admin_can_resend_an_expired_invitation_and_the_previous_token_is_invalidated(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['name' => 'Administradora']);
        $invited = User::factory()->create(['email' => 'pendiente@example.test', 'email_verified_at' => null]);
        $organization = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'empresa-uno']);
        $organization->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $organization->memberships()->create(['user_id' => $invited->id, 'role' => UserRole::Viewer]);
        $oldToken = 'token-anterior';
        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'email' => $invited->email,
            'role' => UserRole::Viewer,
            'token_hash' => hash('sha256', $oldToken),
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->get(route('users.index'))->assertOk()->assertSee('enlace vencido')->assertSee('Reenviar invitación');
        $this->post(route('user-invitations.resend', $invitation))->assertRedirect()->assertSessionHas('status');

        $invitation->refresh();
        $this->assertNotSame(hash('sha256', $oldToken), $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->get(route('invitations.accept', $oldToken))->assertNotFound();
        Mail::assertQueued(OrganizationInvitationMail::class, function (OrganizationInvitationMail $mail) use ($invitation): bool {
            $token = basename((string) parse_url($mail->invitationUrl, PHP_URL_PATH));

            return $mail->hasTo('pendiente@example.test') && hash('sha256', $token) === $invitation->token_hash;
        });
    }
}
