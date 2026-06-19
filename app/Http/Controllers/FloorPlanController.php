<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\Location;
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

        return view('floor-plans.index', [
            'locations' => Location::query()->orderBy('name')->get(),
            'devices' => Device::query()->orderBy('name')->get(),
            'reportedBeaconMacs' => $this->reportedBeaconMacs($extractor),
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
            'installations' => $selectedPlan
                ? DeviceInstallation::query()->with('device')->where('location_id', $selectedPlan->location_id)->whereNull('ended_at')->get()
                : collect(),
        ]);
    }

    private function reportedBeaconMacs(BleObservationExtractor $extractor): Collection
    {
        $reported = collect();
        $events = TelemetryEvent::query()->with(['device', 'connector'])->latest('received_at')->limit(250)->get();

        foreach ($events as $event) {
            $decoded = data_get($event->normalized_payload, 'decoded')
                ?? data_get($event->raw_payload, 'uplink_message.decoded_payload', []);
            foreach ($extractor->extract($decoded) as $observation) {
                $normalized = BleObservationExtractor::normalizeMac($observation['mac']);
                if (strlen($normalized) !== 12 || $reported->has($normalized)) {
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', TenantRule::exists('locations')],
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,dxf', 'max:20480'],
            'preview' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'width_meters' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'height_meters' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);

        $file = $request->file('plan');
        $root = 'organizations/'.app(OrganizationContext::class)->id().'/floor-plans';
        $path = $file->store($root, 'local');
        $previewPath = $request->file('preview')?->store($root.'/previews', 'local');

        $plan = FloorPlan::query()->create([
            ...$validated,
            'disk' => 'local',
            'file_path' => $path,
            'preview_path' => $previewPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
        ]);

        return redirect()->route('floor-plans.index', ['plan' => $plan])->with('status', 'Plano cargado. Ya puedes dibujar zonas.');
    }

    public function destroy(FloorPlan $floorPlan): RedirectResponse
    {
        Storage::disk($floorPlan->disk)->delete(array_filter([$floorPlan->file_path, $floorPlan->preview_path]));
        $floorPlan->delete();

        return redirect()->route('floor-plans.index')->with('status', 'Plano eliminado.');
    }
}
