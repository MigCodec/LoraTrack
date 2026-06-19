<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredOrganizationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'website' => ['prohibited'],
        ], [
            'organization_name.required' => 'Indica el nombre de tu empresa o proyecto.',
            'email.unique' => 'Este correo ya tiene una cuenta. Inicia sesión para continuar.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'website.prohibited' => 'No fue posible completar el registro.',
        ]);

        [$user, $organization] = DB::transaction(function () use ($data): array {
            $email = mb_strtolower(trim($data['email']));
            $organization = Organization::query()->create([
                'name' => trim($data['organization_name']),
                'slug' => Str::slug($data['organization_name']).'-'.Str::lower(Str::random(8)),
            ]);
            $user = User::query()->create([
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                'password' => $data['password'],
                'role' => UserRole::Admin,
            ]);
            $organization->memberships()->create([
                'user_id' => $user->id,
                'role' => UserRole::Admin,
            ]);

            return [$user, $organization];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('organization_id', $organization->id);

        return redirect()->route('dashboard')->with('status', 'Empresa creada. Ya puedes comenzar a configurar LoraTrack.');
    }
}
