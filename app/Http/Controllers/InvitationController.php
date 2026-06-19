<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(string $token): View
    {
        return view('auth.invitation', ['invitation' => $this->invitation($token), 'token' => $token]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitation($token);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::min(12)]]);
        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->firstOrFail();
        $user->update(['name' => $data['name'], 'password' => $data['password'], 'email_verified_at' => now()]);
        $invitation->organization->memberships()->updateOrCreate(['user_id' => $user->id], ['role' => $invitation->role]);
        $invitation->update(['accepted_at' => now()]);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('organization_id', $invitation->organization_id);

        return redirect()->route('dashboard')->with('status', 'Invitación aceptada.');
    }

    private function invitation(string $token): OrganizationInvitation
    {
        return OrganizationInvitation::query()->with('organization')->where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();
    }
}
