<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\InviteOrganizationUserRequest;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserInvitationController extends Controller
{
    public function store(InviteOrganizationUserRequest $request): RedirectResponse
    {
        $organization = app(OrganizationContext::class)->organization();
        abort_unless($organization, 403);

        $data = $request->validated();
        $email = mb_strtolower(trim($data['email']));
        $role = UserRole::from($data['role']);
        $membershipExpiresAt = $data['access_type'] === 'until'
            ? Carbon::parse($data['membership_expires_at'])->endOfDay()
            : null;

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'Este correo ya tiene una cuenta. Agrégalo mediante el flujo para usuarios existentes.']);
        }
        if (OrganizationInvitation::query()->where('organization_id', $organization->id)->whereRaw('LOWER(email) = ?', [$email])->whereNull('accepted_at')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['email' => 'Ya existe una invitación vigente para este correo.']);
        }

        $token = Str::random(64);
        $expiresAt = now()->addDays(7);
        DB::transaction(function () use ($organization, $email, $role, $token, $expiresAt, $membershipExpiresAt): void {
            $user = User::query()->create([
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                'role' => $role,
                'password' => Str::random(64),
            ]);
            $organization->memberships()->create(['user_id' => $user->id, 'role' => $role, 'expires_at' => $membershipExpiresAt]);
            OrganizationInvitation::query()->create([
                'organization_id' => $organization->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'membership_expires_at' => $membershipExpiresAt,
            ]);
        });

        $this->queueMail($organization, $request->user()->name, $email, $role, $token, $expiresAt, $membershipExpiresAt);

        return back()->with('status', "Invitación enviada a {$email}.");
    }

    public function resend(Request $request, OrganizationInvitation $organizationInvitation): RedirectResponse
    {
        $organization = app(OrganizationContext::class)->organization();
        abort_unless($organization && $organizationInvitation->organization_id === $organization->id, 404);
        abort_if($organizationInvitation->accepted_at, 404);
        if ($organizationInvitation->membership_expires_at?->isPast()) {
            throw ValidationException::withMessages(['invitation' => 'La vigencia asignada al usuario ya venció. Actualízala antes de reenviar la invitación.']);
        }

        $token = Str::random(64);
        $expiresAt = now()->addDays(7);
        $organizationInvitation->update([
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);
        $this->queueMail(
            $organization,
            $request->user()->name,
            $organizationInvitation->email,
            $organizationInvitation->role,
            $token,
            $expiresAt,
            $organizationInvitation->membership_expires_at,
        );

        return back()->with('status', "Invitación reenviada a {$organizationInvitation->email}. El enlace anterior dejó de ser válido.");
    }

    public function destroy(OrganizationInvitation $organizationInvitation): RedirectResponse
    {
        $organization = app(OrganizationContext::class)->organization();
        abort_unless($organization && $organizationInvitation->organization_id === $organization->id, 404);
        abort_if($organizationInvitation->accepted_at, 422, 'La invitación ya fue aceptada. Quita al usuario desde la lista de miembros.');

        DB::transaction(function () use ($organization, $organizationInvitation): void {
            $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($organizationInvitation->email)])->lockForUpdate()->first();
            $organizationInvitation->delete();
            if (! $user) {
                return;
            }

            $organization->memberships()->where('user_id', $user->id)->delete();
            if ($user->memberships()->doesntExist() && ! $user->email_verified_at && blank($user->microsoft_id)) {
                $user->delete();
            }
        });

        return back()->with('status', 'Invitación eliminada. El enlace dejó de ser válido.');
    }

    private function queueMail(Organization $organization, string $administratorName, string $email, UserRole $role, string $token, Carbon $expiresAt, ?Carbon $membershipExpiresAt): void
    {
        Mail::to($email)->queue(new OrganizationInvitationMail(
            organizationName: $organization->name,
            administratorName: $administratorName,
            roleLabel: $role->label(),
            invitationUrl: route('invitations.accept', $token),
            expiresAt: $expiresAt->translatedFormat('d \d\e F \d\e Y, H:i'),
            accessDuration: $membershipExpiresAt?->translatedFormat('Hasta el d \d\e F \d\e Y') ?? 'Permanente',
            primaryColor: $organization->primary_color,
            secondaryColor: $organization->secondary_color,
            accentColor: $organization->accent_color,
        ));
    }
}
