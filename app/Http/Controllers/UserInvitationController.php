<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\InviteOrganizationUserRequest;
use App\Mail\OrganizationInvitationMail;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
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

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'Este correo ya tiene una cuenta. Agrégalo mediante el flujo para usuarios existentes.']);
        }
        if (OrganizationInvitation::query()->where('organization_id', $organization->id)->whereRaw('LOWER(email) = ?', [$email])->whereNull('accepted_at')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['email' => 'Ya existe una invitación vigente para este correo.']);
        }

        $token = Str::random(64);
        $expiresAt = now()->addDays(7);
        DB::transaction(function () use ($organization, $email, $role, $token, $expiresAt): void {
            $user = User::query()->create([
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                'role' => $role,
                'password' => Str::random(64),
            ]);
            $organization->memberships()->create(['user_id' => $user->id, 'role' => $role]);
            OrganizationInvitation::query()->create([
                'organization_id' => $organization->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
            ]);
        });

        Mail::to($email)->queue(new OrganizationInvitationMail(
            organizationName: $organization->name,
            administratorName: $request->user()->name,
            roleLabel: $role->label(),
            invitationUrl: route('invitations.accept', $token),
            expiresAt: $expiresAt->translatedFormat('d \d\e F \d\e Y, H:i'),
            primaryColor: $organization->primary_color,
            secondaryColor: $organization->secondary_color,
            accentColor: $organization->accent_color,
        ));

        return back()->with('status', "Invitación enviada a {$email}.");
    }
}
