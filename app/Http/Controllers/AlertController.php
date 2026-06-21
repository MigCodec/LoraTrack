<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertSetting;
use App\Models\OrganizationMembership;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(): View
    {
        $settings = AlertSetting::current();
        $memberships = $this->activeMemberships()->with('user')->get()
            ->sortBy(fn (OrganizationMembership $membership): string => $membership->user->name);
        $selectedEmails = collect($settings->recipients ?? [])
            ->map(fn (string $email): string => mb_strtolower($email));

        return view('alerts.index', [
            'settings' => $settings,
            'alerts' => Alert::query()->latest('detected_at')->limit(100)->get(),
            'recipientMemberships' => $memberships,
            'selectedRecipientIds' => $memberships
                ->filter(fn (OrganizationMembership $membership): bool => $selectedEmails->contains(mb_strtolower($membership->user->email)))
                ->pluck('user_id')
                ->map(fn (int $id): string => (string) $id)
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'recipient_user_ids' => ['nullable', 'array', 'max:100'],
            'recipient_user_ids.*' => ['required', 'integer', 'distinct'],
            'offline_minutes' => ['required', 'integer', 'between:5,10080'],
            'minimum_confidence' => ['required', 'numeric', 'between:0,1'],
            'enabled_types' => ['array'],
            'enabled_types.*' => ['in:device_offline,connector_error,low_confidence'],
        ]);
        $requestedIds = collect($data['recipient_user_ids'] ?? [])->map(fn (mixed $id): int => (int) $id)->unique()->values();
        $memberships = $this->activeMemberships()->with('user')->whereIn('user_id', $requestedIds)->get();

        if ($memberships->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages([
                'recipient_user_ids' => 'Uno o más destinatarios no pertenecen a la empresa activa o tienen el acceso vencido.',
            ]);
        }

        $emails = $memberships->pluck('user.email')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique()
            ->values();
        AlertSetting::current()->update([
            'enabled' => $request->boolean('enabled'),
            'recipients' => $emails->all(),
            'offline_minutes' => $data['offline_minutes'],
            'minimum_confidence' => $data['minimum_confidence'],
            'enabled_types' => $data['enabled_types'] ?? [],
        ]);

        return back()->with('status', 'Configuración actualizada.');
    }

    private function activeMemberships(): Builder
    {
        return OrganizationMembership::query()
            ->where('organization_id', app(OrganizationContext::class)->id())
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
