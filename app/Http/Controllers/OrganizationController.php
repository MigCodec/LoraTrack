<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        return view('organizations.index', [
            'current' => app(OrganizationContext::class)->organization(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = app(OrganizationContext::class)->organization();
        abort_unless($organization, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            '*.regex' => 'Usa un color hexadecimal válido, por ejemplo #2563EB.',
            'logo.max' => 'El logo no puede superar 4 MB.',
        ]);

        if ($request->boolean('remove_logo') && $organization->logo_path) {
            Storage::disk('local')->delete($organization->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            Storage::disk('local')->delete(array_filter([$organization->logo_path]));
            $data['logo_path'] = $request->file('logo')->store("organizations/{$organization->id}/branding", 'local');
        }

        unset($data['logo'], $data['remove_logo']);
        $organization->update($data);

        return back()->with('status', 'Identidad visual actualizada.');
    }

    public function logo(Request $request): StreamedResponse
    {
        $organization = app(OrganizationContext::class)->organization();
        abort_unless($organization?->logo_path && Storage::disk('local')->exists($organization->logo_path), 404);

        return Storage::disk('local')->response($organization->logo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email:rfc', 'max:255'],
        ], ['name.required' => 'Indica el nombre de la empresa o proyecto.', 'admin_email.required' => 'Indica el correo del administrador.', 'admin_email.email' => 'El correo del administrador no es válido.']);

        $email = mb_strtolower(trim($data['admin_email']));
        $organization = Organization::query()->create(['name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $invitationUrl = null;

        if (! $user) {
            $user = User::query()->create(['name' => Str::before($email, '@'), 'email' => $email, 'role' => UserRole::Admin, 'password' => Str::random(64)]);
            $token = Str::random(64);
            OrganizationInvitation::query()->create(['organization_id' => $organization->id, 'email' => $email, 'role' => UserRole::Admin, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
            $invitationUrl = route('invitations.accept', $token);
            try {
                Mail::raw("Has sido invitado a administrar {$organization->name} en LoraTrack.\n\nConfigura tu contraseña aquí:\n{$invitationUrl}\n\nEl enlace vence en 7 días.", fn ($message) => $message->to($email)->subject('Invitación a LoraTrack'));
            } catch (Throwable $exception) {
                Log::warning('Organization invitation email could not be sent.', ['exception' => $exception::class]);
            }
        }

        $organization->memberships()->updateOrCreate(['user_id' => $user->id], ['role' => UserRole::Admin]);
        $request->session()->put('organization_id', $organization->id);

        $response = redirect()->route('organizations.index')->with('status', 'Empresa o proyecto creado.');

        if ($invitationUrl) {
            $response->with('invitation_url', $invitationUrl);
        }

        return $response;
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->memberships()->where('organization_id', $organization->id)->exists(), 403);
        $request->session()->put('organization_id', $organization->id);

        return redirect()->route('dashboard')->with('status', "Organización activa: {$organization->name}.");
    }
}
