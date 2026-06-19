<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MicrosoftController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(filled(config('services.microsoft.client_id')), 404);

        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (Throwable $exception) {
            Log::warning('Microsoft authentication failed.', ['exception' => $exception::class]);

            return redirect()->route('login')->withErrors([
                'email' => 'No fue posible completar el acceso con Microsoft.',
            ]);
        }

        $email = mb_strtolower(trim((string) $microsoftUser->getEmail()));
        $microsoftId = (string) $microsoftUser->getId();

        $user = User::query()
            ->where('microsoft_id', $microsoftId)
            ->orWhere(function ($query) use ($email): void {
                $query->whereNull('microsoft_id')->whereRaw('LOWER(email) = ?', [$email]);
            })
            ->first();

        if (! $user || $email === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta Microsoft no está autorizada en LoraTrack.',
            ]);
        }

        if ($user->microsoft_id === null) {
            $user->forceFill(['microsoft_id' => $microsoftId, 'email_verified_at' => now()])->save();
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
