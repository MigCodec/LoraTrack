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
        $validated = $request->validate([
            'device_id' => ['nullable', 'required_without:device_identifier', TenantRule::exists('devices')],
            'device_identifier' => ['nullable', 'required_without:device_id', 'string', 'max:255'],
            'tracking_strategy' => ['required', 'in:fixed_beacons_mobile_tracker,mobile_beacon_fixed_scanners,assigned_static'],
        ]);

        DB::transaction(function () use ($asset, $validated): void {
            Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if (! empty($validated['device_identifier'])) {
                if ($asset->mobility !== 'mobile' || $validated['tracking_strategy'] !== 'fixed_beacons_mobile_tracker') {
                    throw ValidationException::withMessages(['device_identifier' => 'Un tracker SenseCAP solo puede asociarse a un activo móvil.']);
                }
                $identifier = trim($validated['device_identifier']);
                if (preg_match('/^[A-Fa-f0-9]{16}$/', $identifier)) {
                    $identifier = mb_strtoupper($identifier);
                }
                $device = Device::query()->firstOrCreate(
                    ['identifier' => $identifier],
                    ['name' => 'SenseCAP '.$identifier, 'type' => 'lorawan_tracker', 'model' => 'SenseCAP T1000-B', 'status' => 'active'],
                );
            } else {
                $device = Device::query()->whereKey($validated['device_id'])->lockForUpdate()->firstOrFail();
            }

            $valid = $device->status === 'active' && match ($validated['tracking_strategy']) {
                'fixed_beacons_mobile_tracker' => $asset->mobility === 'mobile' && $device->type === 'lorawan_tracker',
                'mobile_beacon_fixed_scanners' => $asset->mobility === 'mobile' && $device->type === 'beacon',
                'assigned_static' => $asset->mobility === 'static' && $device->type === 'beacon',
                default => false,
            };
            if (! $valid) {
                throw ValidationException::withMessages(['device_id' => 'El dispositivo o estrategia no corresponde al tipo de activo.']);
            }

            AssetDeviceAssignment::query()->where(fn ($q) => $q->where('asset_id', $asset->id)->orWhere('device_id', $device->id))->whereNull('ended_at')->update(['ended_at' => now()]);
            AssetDeviceAssignment::query()->create([
                'asset_id' => $asset->id,
                'device_id' => $device->id,
                'tracking_strategy' => $validated['tracking_strategy'],
                'started_at' => now(),
            ]);
        });

        return back()->with('status', 'Dispositivo asignado.');
    }

    public function destroy(AssetDeviceAssignment $assignment): RedirectResponse
    {
        $assignment->update(['ended_at' => now()]);

        return back()->with('status', 'Asignación finalizada.');
    }
}
