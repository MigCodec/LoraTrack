<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloorPlanRequest;
use App\Http\Requests\UpdateFloorPlanRequest;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\PositionEstimate;
use App\Models\TelemetryEvent;
use App\Positioning\BleObservationExtractor;
use App\Tenancy\OrganizationContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FloorPlanController extends Controller
{
    public function index(Request $request, BleObservationExtractor $extractor): View
    {
        $plans = FloorPlan::query()->with(['location', 'zones.alertRules'])->latest()->get();
        $selectedPlan = $plans->firstWhere('id', $request->query('plan')) ?? $plans->first();
        $installations = $selectedPlan
            ? DeviceInstallation::query()->with('device')->where('floor_plan_id', $selectedPlan->id)->whereNull('ended_at')->get()
            : collect();
        $installedDeviceIds = $installations->pluck('device_id');
        $installedIdentifiers = $installations->map(
            fn (DeviceInstallation $installation): string => BleObservationExtractor::normalizeMac($installation->device->identifier),
        );
        $assetPositions = $selectedPlan
            ? PositionEstimate::query()
                ->with('asset')
                ->where('floor_plan_id', $selectedPlan->id)
                ->whereIn('id', PositionEstimate::query()
                    ->selectRaw('MAX(id)')
                    ->where('floor_plan_id', $selectedPlan->id)
                    ->groupBy('asset_id'))
                ->get()
            : collect();

        return view('floor-plans.index', [
            'locations' => Location::query()->orderBy('name')->get(),
            'devices' => Device::query()->whereNotIn('id', $installedDeviceIds)->orderBy('name')->get(),
            'reportedBeaconMacs' => $this->reportedBeaconMacs($extractor, $installedIdentifiers),
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
            'assetPositions' => $assetPositions,
            'installations' => $installations,
        ]);
    }

    private function reportedBeaconMacs(BleObservationExtractor $extractor, Collection $excludedIdentifiers): Collection
    {
        $reported = collect();
        $events = TelemetryEvent::query()->with(['device', 'connector'])->latest('received_at')->limit(250)->get();

        foreach ($events as $event) {
            $decoded = data_get($event->normalized_payload, 'decoded')
                ?? data_get($event->raw_payload, 'uplink_message.decoded_payload', []);
            foreach ($extractor->extract($decoded) as $observation) {
                $normalized = BleObservationExtractor::normalizeMac($observation['mac']);
                if (strlen($normalized) !== 12 || $reported->has($normalized) || $excludedIdentifiers->contains($normalized)) {
                    continue;
                }
                $reported->put($normalized, [
                    'identifier' => implode(':', str_split($normalized, 2)),
                    'tracker_name' => $event->device?->name
                        ?? data_get($event->raw_payload, 'end_device_ids.device_id', 'Sensor sin nombre'),
                    'connector_name' => $event->connector?->name,
                    'rssi' => $observation['rssi'],
                    'observed_at' => $event->observed_at ?? $event->received_at,
                ]);
            }
        }

        return $reported->values();
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:site,building,floor,zone'],
            'parent_id' => ['nullable', TenantRule::exists('locations')],
        ]);
        Location::query()->create($validated + ['coordinate_system' => 'local_meters']);

        return back()->with('status', 'Ubicación creada.');
    }

    public function store(StoreFloorPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $file = $request->file('plan');
        $root = 'organizations/'.app(OrganizationContext::class)->id().'/floor-plans';
        $path = $file->store($root, 'local');
        $previewPath = $request->file('preview')?->store($root.'/previews', 'local');

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = match ($extension) {
            'glb' => 'model/gltf-binary',
            'gltf' => 'model/gltf+json',
            default => $file->getMimeType() ?: 'application/octet-stream',
        };
        $modelTransform = $validated['view_mode'] === '3d' ? [
            'scale' => isset($validated['model_scale']) ? (float) $validated['model_scale'] : null,
            'rotation_y_degrees' => (float) ($validated['model_rotation_y'] ?? 0),
            'offset_x' => (float) ($validated['model_offset_x'] ?? 0),
            'offset_y' => (float) ($validated['model_offset_y'] ?? 0),
            'offset_z' => (float) ($validated['model_offset_z'] ?? 0),
            'coordinate_mapping' => 'x,z,y',
        ] : null;

        $plan = FloorPlan::query()->create([
            'location_id' => $validated['location_id'],
            'name' => $validated['name'],
            'view_mode' => $validated['view_mode'],
            'width_meters' => $validated['width_meters'],
            'height_meters' => $validated['height_meters'],
            'depth_meters' => $validated['depth_meters'] ?? null,
            'model_transform' => $modelTransform,
            'disk' => 'local',
            'file_path' => $path,
            'preview_path' => $previewPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
        ]);

        $status = $plan->isThreeDimensional()
            ? 'Modelo 3D cargado. Ya puedes recorrerlo con órbita, desplazamiento y zoom.'
            : 'Plano 2D cargado. Ya puedes navegarlo y dibujar zonas.';

        return redirect()->route('floor-plans.index', ['plan' => $plan])->with('status', $status);
    }

    public function destroy(FloorPlan $floorPlan): RedirectResponse
    {
        Storage::disk($floorPlan->disk)->delete(array_filter([$floorPlan->file_path, $floorPlan->preview_path]));
        $floorPlan->delete();

        return redirect()->route('floor-plans.index')->with('status', 'Plano eliminado.');
    }

    public function update(UpdateFloorPlanRequest $request, FloorPlan $floorPlan): RedirectResponse
    {
        $floorPlan->update($request->validated());
        $status = $request->exists('name') ? 'Nombre del plano actualizado.' : 'Color de la pestaña actualizado.';

        return redirect()->route('floor-plans.index', ['plan' => $floorPlan])->with('status', $status);
    }
}
