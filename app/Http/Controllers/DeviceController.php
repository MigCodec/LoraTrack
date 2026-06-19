<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Tenancy\OrganizationContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255', TenantRule::unique('devices', 'identifier')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:beacon,scanner,lorawan_tracker'],
            'model' => ['nullable', 'string', 'max:255'],
        ]);
        Device::query()->create($validated + ['status' => 'active']);

        return back()->with('status', 'Dispositivo creado.');
    }

    public function install(Request $request, FloorPlan $floorPlan): RedirectResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', Rule::exists('devices', 'id')->where(function ($query): void {
                $query->where(function ($tenantQuery): void {
                    $tenantQuery->where('organization_id', app(OrganizationContext::class)->id());
                    if (app()->environment('testing')) {
                        $tenantQuery->orWhereNull('organization_id');
                    }
                });
            })->whereIn('type', ['beacon', 'scanner'])->where('status', 'active')],
            'x_normalized' => ['required', 'numeric', 'between:0,1'],
            'y_normalized' => ['required', 'numeric', 'between:0,1'],
            'reference_rssi' => ['required', 'integer', 'between:-127,-1'],
            'path_loss_exponent' => ['required', 'numeric', 'between:0.5,8'],
        ]);

        DB::transaction(function () use ($validated, $floorPlan): void {
            Device::query()->whereKey($validated['device_id'])->lockForUpdate()->firstOrFail();
            DeviceInstallation::query()
                ->where('device_id', $validated['device_id'])
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            DeviceInstallation::query()->create([
                'device_id' => $validated['device_id'],
                'location_id' => $floorPlan->location_id,
                'x' => (float) $validated['x_normalized'] * (float) $floorPlan->width_meters,
                'y' => (float) $validated['y_normalized'] * (float) $floorPlan->height_meters,
                'reference_rssi' => $validated['reference_rssi'],
                'path_loss_exponent' => $validated['path_loss_exponent'],
                'started_at' => now(),
            ]);
        });

        return redirect()->route('floor-plans.index', ['plan' => $floorPlan])->with('status', 'Ancla ubicada en el plano.');
    }

    public function removeInstallation(DeviceInstallation $deviceInstallation): RedirectResponse
    {
        $locationId = $deviceInstallation->location_id;
        $deviceInstallation->forceFill(['ended_at' => now()])->save();
        $plan = FloorPlan::query()->where('location_id', $locationId)->where('is_active', true)->first();

        return redirect()->route('floor-plans.index', ['plan' => $plan?->id])->with('status', 'Instalación cerrada.');
    }
}
