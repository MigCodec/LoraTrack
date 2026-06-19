<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDeviceAssignment;
use App\Models\Device;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetDeviceAssignmentController extends Controller
{
    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate(['device_id' => ['required', TenantRule::exists('devices')], 'tracking_strategy' => ['required', 'in:fixed_beacons_mobile_tracker,mobile_beacon_fixed_scanners,assigned_static']]);
        $device = Device::query()->findOrFail($validated['device_id']);
        $valid = match ($validated['tracking_strategy']) {
            'fixed_beacons_mobile_tracker' => $asset->mobility === 'mobile' && $device->type === 'lorawan_tracker', 'mobile_beacon_fixed_scanners' => $asset->mobility === 'mobile' && $device->type === 'beacon', 'assigned_static' => $asset->mobility === 'static' && $device->type === 'beacon', default => false
        };
        if (! $valid) {
            throw ValidationException::withMessages(['device_id' => 'El dispositivo o estrategia no corresponde al tipo de activo.']);
        }
        DB::transaction(function () use ($asset, $validated): void {
            Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            Device::query()->whereKey($validated['device_id'])->lockForUpdate()->firstOrFail();
            AssetDeviceAssignment::query()->where(fn ($q) => $q->where('asset_id', $asset->id)->orWhere('device_id', $validated['device_id']))->whereNull('ended_at')->update(['ended_at' => now()]);
            AssetDeviceAssignment::query()->create($validated + ['asset_id' => $asset->id, 'started_at' => now()]);
        });

        return back()->with('status', 'Dispositivo asignado.');
    }

    public function destroy(AssetDeviceAssignment $assignment): RedirectResponse
    {
        $assignment->update(['ended_at' => now()]);

        return back()->with('status', 'Asignación finalizada.');
    }
}
