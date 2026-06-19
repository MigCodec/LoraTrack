<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDeviceAssignment;
use App\Models\Device;
use App\Models\Location;
use App\Models\Sku;
use App\Tenancy\OrganizationContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $mobility = in_array($request->query('mobility'), ['mobile', 'static'], true) ? $request->query('mobility') : 'mobile';

        return view('assets.index', [
            'assets' => Asset::query()
                ->with(['sku.product', 'location', 'latestPosition.zone', 'deviceAssignments' => fn ($query) => $query->whereNull('ended_at')->with('device')])
                ->where('mobility', $mobility)
                ->where('status', '!=', 'archived')
                ->latest()
                ->paginate(25),
            'mobility' => $mobility,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new Asset(['mobility' => $request->query('mobility', 'mobile')]));
    }

    public function edit(Asset $asset): View
    {
        return $this->form($asset->load(['deviceAssignments' => fn ($q) => $q->whereNull('ended_at')->with('device')]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $asset = DB::transaction(function () use ($request, $validated): Asset {
            $asset = Asset::query()->create($this->assetData($validated));
            $this->assignInitialTracker($asset, $validated['tracker_device_id'] ?? null);
            $this->storePhoto($request, $asset);

            return $asset;
        });

        return redirect()->route('assets.edit', $asset)->with('status', 'Activo creado.');
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $this->validated($request, $asset);
        $asset->update($this->assetData($validated));
        $this->storePhoto($request, $asset);

        return back()->with('status', 'Activo actualizado.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->deviceAssignments()->whereNull('ended_at')->update(['ended_at' => now()]);
        $asset->update(['status' => 'archived']);

        return redirect()->route('assets.index', ['mobility' => $asset->mobility])->with('status', 'Activo archivado.');
    }

    private function form(Asset $asset): View
    {
        return view('assets.form', [
            'asset' => $asset,
            'skus' => Sku::query()->with('product')->orderBy('code')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'devices' => Device::query()->where('status', 'active')->orderBy('name')->get(),
            'reportedTrackers' => Device::query()
                ->where('status', 'active')
                ->where('type', 'lorawan_tracker')
                ->whereDoesntHave('assignments', fn ($query) => $query->whereNull('ended_at'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?Asset $asset = null): array
    {
        return $request->validate([
            'sku_id' => ['nullable', TenantRule::exists('skus')],
            'location_id' => ['nullable', TenantRule::exists('locations')],
            'asset_tag' => ['required', 'string', 'max:255', TenantRule::unique('assets', 'asset_tag')->ignore($asset)],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'mobility' => ['required', 'in:mobile,static'],
            'status' => ['required', 'in:active,inactive,maintenance'],
            'tracker_device_id' => ['nullable', TenantRule::exists('devices')],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function assetData(array $validated): array
    {
        unset($validated['tracker_device_id'], $validated['photo'], $validated['remove_photo']);

        return $validated;
    }

    private function storePhoto(Request $request, Asset $asset): void
    {
        if ($request->boolean('remove_photo') && $asset->photo_path) {
            Storage::disk('local')->delete($asset->photo_path);
            $asset->forceFill(['photo_path' => null])->save();
        }
        if (! $request->hasFile('photo')) {
            return;
        }

        Storage::disk('local')->delete(array_filter([$asset->photo_path]));
        $path = $request->file('photo')->store(
            'organizations/'.app(OrganizationContext::class)->id().'/assets/'.$asset->id,
            'local',
        );
        $asset->forceFill(['photo_path' => $path])->save();
    }

    private function assignInitialTracker(Asset $asset, ?string $deviceId): void
    {
        if (! $deviceId) {
            return;
        }
        $device = Device::query()->whereKey($deviceId)->lockForUpdate()->firstOrFail();
        if ($asset->mobility !== 'mobile' || $device->type !== 'lorawan_tracker' || $device->status !== 'active') {
            throw ValidationException::withMessages(['tracker_device_id' => 'Selecciona un tracker LoRaWAN activo para un activo móvil.']);
        }
        if ($device->assignments()->whereNull('ended_at')->exists()) {
            throw ValidationException::withMessages(['tracker_device_id' => 'El tracker ya está asignado a otro activo.']);
        }
        AssetDeviceAssignment::query()->create([
            'asset_id' => $asset->id,
            'device_id' => $device->id,
            'tracking_strategy' => 'fixed_beacons_mobile_tracker',
            'started_at' => now(),
        ]);
    }
}
