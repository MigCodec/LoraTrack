<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Device;
use App\Models\Location;
use App\Models\Sku;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $asset = Asset::query()->create($this->validated($request));

        return redirect()->route('assets.edit', $asset)->with('status', 'Activo creado. Ahora puedes asignar su dispositivo.');
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $asset->update($this->validated($request, $asset));

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
                ->whereNotNull('last_seen_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?Asset $asset = null): array
    {
        return $request->validate(['sku_id' => ['nullable', TenantRule::exists('skus')], 'location_id' => ['nullable', TenantRule::exists('locations')], 'asset_tag' => ['required', 'string', 'max:255', TenantRule::unique('assets', 'asset_tag')->ignore($asset)], 'serial_number' => ['nullable', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'mobility' => ['required', 'in:mobile,static'], 'status' => ['required', 'in:active,inactive,maintenance']]);
    }
}
