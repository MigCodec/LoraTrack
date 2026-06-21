<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $organization = app(OrganizationContext::class)->organization();
        $invitations = OrganizationInvitation::query()
            ->where('organization_id', $organization->id)
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query->whereNull('membership_expires_at')->orWhere('membership_expires_at', '>', now()))
            ->latest()
            ->get();
        $pendingEmails = $invitations->pluck('email')->map(static fn (string $email): string => mb_strtolower($email));
        $pendingUserIds = User::query()->whereIn('email', $pendingEmails)->pluck('id');
        $users = $organization->users()->orderBy('name')->get()
            ->reject(static fn (User $user): bool => $pendingEmails->contains(mb_strtolower($user->email)));
        $memberships = $organization->memberships()->whereNotIn('user_id', $pendingUserIds)->get();

        return view('users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'invitations' => $invitations,
            'groupStats' => collect(UserRole::cases())->mapWithKeys(fn (UserRole $role): array => [
                $role->value => [
                    'total' => $memberships->where('role', $role)->count(),
                    'active' => $memberships->where('role', $role)->reject->isExpired()->count(),
                ],
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->membershipRules() + ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'], 'password' => ['required', 'string', Password::min(12)]]);
        $organization = app(OrganizationContext::class)->organization();
        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first();
        abort_if($user && $organization->memberships()->where('user_id', $user->id)->exists(), 422, 'El usuario ya pertenece a esta organización.');
        $user ??= User::query()->create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'role' => $data['role'], 'password' => $data['password']]);
        $organization->memberships()->create(['user_id' => $user->id, 'role' => $data['role'], 'expires_at' => $this->expiration($data)]);

        return back()->with('status', 'Usuario agregado a la organización.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $membership = $this->membership($user);
        $data = $request->validate($this->membershipRules());
        $expiresAt = $this->expiration($data);
        $this->ensurePermanentAdministratorRemains([$membership->id], UserRole::from($data['role']), $expiresAt);
        $membership->update(['role' => $data['role'], 'expires_at' => $expiresAt]);

        return back()->with('status', 'Usuario actualizado.');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate($this->membershipRules() + [
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $memberships = OrganizationMembership::query()
            ->where('organization_id', app(OrganizationContext::class)->id())
            ->whereIn('user_id', $data['user_ids'])
            ->get();
        abort_unless($memberships->count() === count($data['user_ids']), 422, 'Uno o más usuarios no pertenecen a la empresa activa.');

        $role = UserRole::from($data['role']);
        $expiresAt = $this->expiration($data);
        $this->ensurePermanentAdministratorRemains($memberships->pluck('id')->all(), $role, $expiresAt);
        DB::transaction(fn () => OrganizationMembership::query()->whereIn('id', $memberships->pluck('id')->all())->update([
            'role' => $role->value,
            'expires_at' => $expiresAt,
            'updated_at' => now(),
        ]));

        return back()->with('status', $memberships->count().' membresía(s) actualizadas.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'No puedes quitar tu propia membresía.');
        $membership = $this->membership($user);
        $this->ensurePermanentAdministratorRemains([$membership->id], UserRole::Viewer, now());
        $membership->delete();

        return back()->with('status', 'Usuario retirado de la organización.');
    }

    private function membership(User $user): OrganizationMembership
    {
        return OrganizationMembership::query()->where('organization_id', app(OrganizationContext::class)->id())->where('user_id', $user->id)->firstOrFail();
    }

    /** @return array<string, list<mixed>> */
    private function membershipRules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)],
            'access_type' => ['required', Rule::in(['permanent', 'until'])],
            'expires_at' => ['nullable', 'required_if:access_type,until', 'date', 'after_or_equal:today'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function expiration(array $data): ?Carbon
    {
        return $data['access_type'] === 'until' ? Carbon::parse($data['expires_at'])->endOfDay() : null;
    }

    /** @param list<int> $replacedMembershipIds */
    private function ensurePermanentAdministratorRemains(array $replacedMembershipIds, UserRole $replacementRole, ?Carbon $replacementExpiration): void
    {
        $remaining = OrganizationMembership::query()
            ->where('organization_id', app(OrganizationContext::class)->id())
            ->where('role', UserRole::Admin)
            ->whereNull('expires_at')
            ->whereNotIn('id', $replacedMembershipIds)
            ->count();
        $replacements = $replacementRole === UserRole::Admin && $replacementExpiration === null ? count($replacedMembershipIds) : 0;
        abort_if($remaining + $replacements < 1, 422, 'Debe existir al menos un administrador con acceso permanente.');
    }
}
