<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AlertRule;
use App\Models\Asset;
use App\Models\OrganizationMembership;
use App\Models\Zone;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AlertRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        AlertRule::query()->create($this->validated($request));

        return back()->with('status', 'Regla creada.');
    }

    public function update(Request $request, AlertRule $alertRule): RedirectResponse
    {
        $alertRule->update($this->validated($request));

        return back()->with('status', 'Regla actualizada.');
    }

    public function destroy(AlertRule $alertRule): RedirectResponse
    {
        $alertRule->delete();

        return back()->with('status', 'Regla eliminada.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
            'subject_type' => ['required', Rule::in(['all_assets', 'asset'])],
            'subject_id' => ['nullable', 'string'],
            'trigger_type' => ['required', Rule::in(['zone_entry', 'zone_exit', 'zone_inside', 'zone_outside', 'speed_above', 'speed_below'])],
            'zone_id' => ['nullable', 'string'],
            'threshold' => ['nullable', 'numeric', 'between:0,1000'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,10080'],
            'cooldown_minutes' => ['required', 'integer', 'between:1,10080'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*' => [Rule::in(['create_alert', 'send_email'])],
            'recipient_roles' => ['nullable', 'array'],
            'recipient_roles.*' => [Rule::enum(UserRole::class)],
            'recipient_user_ids' => ['nullable', 'array', 'max:100'],
            'recipient_user_ids.*' => ['integer', 'distinct'],
        ]);

        if ($data['subject_type'] === 'asset' && (! $data['subject_id'] || ! Asset::query()->whereKey($data['subject_id'])->exists())) {
            throw ValidationException::withMessages(['subject_id' => 'Selecciona un activo de la empresa.']);
        }
        $usesZone = str_starts_with($data['trigger_type'], 'zone_');
        if ($usesZone && (! $data['zone_id'] || ! Zone::query()->whereKey($data['zone_id'])->exists())) {
            throw ValidationException::withMessages(['zone_id' => 'Selecciona una zona de la empresa.']);
        }
        if (str_starts_with($data['trigger_type'], 'speed_') && $data['threshold'] === null) {
            throw ValidationException::withMessages(['threshold' => 'Indica el umbral de velocidad.']);
        }
        $requestedUsers = collect($data['recipient_user_ids'] ?? [])->map(fn (mixed $id): int => (int) $id)->unique();
        $validUsers = OrganizationMembership::query()
            ->where('organization_id', app(OrganizationContext::class)->id())
            ->whereIn('user_id', $requestedUsers)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        if ($validUsers !== $requestedUsers->count()) {
            throw ValidationException::withMessages(['recipient_user_ids' => 'Hay usuarios ajenos a la empresa o con acceso vencido.']);
        }
        if (in_array('send_email', $data['actions'], true) && empty($data['recipient_roles']) && $requestedUsers->isEmpty()) {
            throw ValidationException::withMessages(['recipient_user_ids' => 'Elige al menos un grupo o usuario para enviar avisos.']);
        }

        return [
            ...$data,
            'enabled' => $request->boolean('enabled'),
            'subject_id' => $data['subject_type'] === 'asset' ? $data['subject_id'] : null,
            'zone_id' => $usesZone ? $data['zone_id'] : null,
            'threshold' => str_starts_with($data['trigger_type'], 'speed_') ? $data['threshold'] : null,
            'duration_minutes' => in_array($data['trigger_type'], ['zone_inside', 'zone_outside'], true) ? ($data['duration_minutes'] ?? 1) : null,
            'recipient_user_ids' => $requestedUsers->values()->all(),
        ];
    }
}
