<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $organization = app(OrganizationContext::class)->organization();

        return view('users.index', ['users' => $organization->users()->orderBy('name')->get(), 'roles' => UserRole::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'], 'role' => ['required', Rule::enum(UserRole::class)], 'password' => ['required', 'string', Password::min(12)]]);
        $organization = app(OrganizationContext::class)->organization();
        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first();
        abort_if($user && $organization->memberships()->where('user_id', $user->id)->exists(), 422, 'El usuario ya pertenece a esta organización.');
        $user ??= User::query()->create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'role' => $data['role'], 'password' => $data['password']]);
        $organization->memberships()->create(['user_id' => $user->id, 'role' => $data['role']]);

        return back()->with('status', 'Usuario agregado a la organización.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $membership = $this->membership($user);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user)], 'role' => ['required', Rule::enum(UserRole::class)], 'password' => ['nullable', 'string', Password::min(12)]]);
        abort_if($membership->role === UserRole::Admin && $data['role'] !== UserRole::Admin->value && $this->adminCount() <= 1, 422, 'Debe existir al menos un administrador.');
        $userData = ['name' => $data['name'], 'email' => $data['email']];
        if (filled($data['password'] ?? null)) {
            $userData['password'] = $data['password'];
        }
        $user->update($userData);
        $membership->update(['role' => $data['role']]);

        return back()->with('status', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'No puedes quitar tu propia membresía.');
        $membership = $this->membership($user);
        abort_if($membership->role === UserRole::Admin && $this->adminCount() <= 1, 422, 'Debe existir al menos un administrador.');
        $membership->delete();
        if ($user->memberships()->doesntExist()) {
            $user->delete();
        }

        return back()->with('status', 'Usuario retirado de la organización.');
    }

    private function membership(User $user): OrganizationMembership
    {
        return OrganizationMembership::query()->where('organization_id', app(OrganizationContext::class)->id())->where('user_id', $user->id)->firstOrFail();
    }

    private function adminCount(): int
    {
        return OrganizationMembership::query()->where('organization_id', app(OrganizationContext::class)->id())->where('role', UserRole::Admin)->count();
    }
}
