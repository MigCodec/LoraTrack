<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TelemetryMacFilter;
use App\Positioning\BleObservationExtractor;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TelemetryMacFilterController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $term = trim((string) ($validated['q'] ?? ''));
        $normalized = BleObservationExtractor::normalizeMac($term);

        $filters = TelemetryMacFilter::query()
            ->with('device')
            ->when($term !== '', function ($query) use ($term, $normalized): void {
                $like = '%'.addcslashes($term, '\%_').'%';
                $query->where(function ($search) use ($like, $normalized): void {
                    $search->where('label', 'like', $like)
                        ->orWhere('normalized_mac', 'like', '%'.addcslashes($normalized, '\%_').'%')
                        ->orWhereHas('device', fn ($device) => $device
                            ->where('name', 'like', $like)
                            ->orWhere('identifier', 'like', $like));
                });
            })
            ->orderByDesc('enabled')
            ->orderBy('normalized_mac')
            ->paginate(50)
            ->withQueryString();

        return view('telemetry-mac-filters.index', [
            'filters' => $filters,
            'devices' => Device::query()->whereIn('type', ['beacon', 'lorawan_tracker'])->orderBy('name')->limit(200)->get(),
            'search' => $term,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TelemetryMacFilter::query()->create($this->validated($request));

        return back()->with('status', 'Filtro MAC guardado.');
    }

    public function update(Request $request, TelemetryMacFilter $telemetryMacFilter): RedirectResponse
    {
        $telemetryMacFilter->update($this->validated($request, $telemetryMacFilter));

        return back()->with('status', 'Filtro MAC actualizado.');
    }

    public function destroy(TelemetryMacFilter $telemetryMacFilter): RedirectResponse
    {
        $telemetryMacFilter->delete();

        return back()->with('status', 'Filtro MAC eliminado.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?TelemetryMacFilter $filter = null): array
    {
        $validated = $request->validate([
            'device_id' => ['nullable', TenantRule::exists('devices')],
            'mac' => ['nullable', 'required_without:device_id', 'string', 'max:32'],
            'label' => ['nullable', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $device = ! empty($validated['device_id'])
            ? Device::query()->find($validated['device_id'])
            : null;
        $normalized = BleObservationExtractor::normalizeMac($device?->identifier ?? (string) ($validated['mac'] ?? ''));
        if (strlen($normalized) !== 12) {
            throw ValidationException::withMessages(['mac' => 'La MAC debe contener exactamente 12 digitos hexadecimales.']);
        }

        $organizationId = $filter?->organization_id ?? app(\App\Tenancy\OrganizationContext::class)->id();
        $duplicate = TelemetryMacFilter::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('normalized_mac', $normalized)
            ->when($filter, fn ($query) => $query->whereKeyNot($filter->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['mac' => 'Esta MAC ya existe en el filtro.']);
        }

        return [
            'device_id' => $device?->id,
            'normalized_mac' => $normalized,
            'label' => $validated['label'] ?? $device?->name,
            'enabled' => $request->boolean('enabled'),
        ];
    }
}
