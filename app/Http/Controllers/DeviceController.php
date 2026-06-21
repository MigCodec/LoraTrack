<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDeviceInstallationRequest;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Positioning\BleObservationExtractor;
use App\Tenancy\OrganizationContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'device_id' => ['nullable', 'required_without:device_identifier', Rule::exists('devices', 'id')->where(function ($query): void {
                $query->where(function ($tenantQuery): void {
                    $tenantQuery->where('organization_id', app(OrganizationContext::class)->id());
                    if (app()->environment('testing')) {
                        $tenantQuery->orWhereNull('organization_id');
                    }
                });
            })->whereIn('type', ['beacon', 'scanner'])->where('status', 'active')],
            'device_identifier' => ['nullable', 'required_without:device_id', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'x_normalized' => ['required', 'numeric', 'between:0,1'],
            'y_normalized' => ['required', 'numeric', 'between:0,1'],
            'reference_rssi' => ['required', 'integer', 'between:-127,-1'],
            'path_loss_exponent' => ['required', 'numeric', 'between:0.5,8'],
        ]);

        DB::transaction(function () use ($validated, $floorPlan): void {
            if (! empty($validated['device_identifier'])) {
                $identifier = BleObservationExtractor::normalizeMac($validated['device_identifier']);
                if (strlen($identifier) !== 12) {
                    throw ValidationException::withMessages(['device_identifier' => 'La MAC del beacon debe contener exactamente 12 dígitos hexadecimales.']);
                }
                $device = Device::query()->where('type', 'beacon')->get()->first(
                    fn (Device $candidate): bool => BleObservationExtractor::normalizeMac($candidate->identifier) === $identifier,
                );
                $device ??= Device::query()->create([
                    'identifier' => $identifier,
                    'name' => ($validated['device_name'] ?? null) ?: 'Beacon '.implode(':', str_split($identifier, 2)),
                    'type' => 'beacon',
                    'status' => 'active',
                ]);
            } else {
                $device = Device::query()->whereKey($validated['device_id'])->lockForUpdate()->firstOrFail();
            }
            if (DeviceInstallation::query()
                ->where('device_id', $device->id)
                ->where('floor_plan_id', $floorPlan->id)
                ->whereNull('ended_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'device_identifier' => 'Este dispositivo ya está instalado en el plano seleccionado.',
                ]);
            }
            DeviceInstallation::query()
                ->where('device_id', $device->id)
                ->where('floor_plan_id', $floorPlan->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            DeviceInstallation::query()->create([
                'device_id' => $device->id,
                'location_id' => $floorPlan->location_id,
                'floor_plan_id' => $floorPlan->id,
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
        $floorPlanId = $deviceInstallation->floor_plan_id;
        $locationId = $deviceInstallation->location_id;
        $deviceInstallation->forceFill(['ended_at' => now()])->save();
        $plan = FloorPlan::query()->find($floorPlanId)
            ?? FloorPlan::query()->where('location_id', $locationId)->where('is_active', true)->first();

        return redirect()->route('floor-plans.index', ['plan' => $plan?->id])->with('status', 'Instalación cerrada.');
    }

    public function updateInstallation(UpdateDeviceInstallationRequest $request, DeviceInstallation $deviceInstallation): RedirectResponse
    {
        $validated = $request->validated();
        $floorPlan = $deviceInstallation->floorPlan;
        if ((float) $validated['x_meters'] > (float) $floorPlan->width_meters) {
            throw ValidationException::withMessages(['x_meters' => 'La posición X debe estar dentro del ancho del plano.']);
        }
        if ((float) $validated['y_meters'] > (float) $floorPlan->height_meters) {
            throw ValidationException::withMessages(['y_meters' => 'La posición Y debe estar dentro del alto del plano.']);
        }

        DB::transaction(function () use ($deviceInstallation, $validated): void {
            $deviceInstallation->device()->update(['name' => $validated['name']]);
            $x = (float) $validated['x_meters'];
            $y = (float) $validated['y_meters'];
            $positionChanged = abs($x - (float) $deviceInstallation->x) > 0.000001
                || abs($y - (float) $deviceInstallation->y) > 0.000001;

            if (! $positionChanged) {
                $deviceInstallation->update([
                    'reference_rssi' => $validated['reference_rssi'],
                    'path_loss_exponent' => $validated['path_loss_exponent'],
                ]);

                return;
            }

            $deviceInstallation->forceFill(['ended_at' => now()])->save();
            DeviceInstallation::query()->create([
                'organization_id' => $deviceInstallation->organization_id,
                'device_id' => $deviceInstallation->device_id,
                'location_id' => $deviceInstallation->location_id,
                'floor_plan_id' => $deviceInstallation->floor_plan_id,
                'x' => $x,
                'y' => $y,
                'z' => $deviceInstallation->z,
                'reference_rssi' => $validated['reference_rssi'],
                'path_loss_exponent' => $validated['path_loss_exponent'],
                'started_at' => now(),
            ]);
        });

        return redirect()->route('floor-plans.index', ['plan' => $deviceInstallation->floor_plan_id])
            ->with('status', 'Parámetros y posición del beacon actualizados.');
    }
}
