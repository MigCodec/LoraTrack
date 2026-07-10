<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Meraki\MerakiEventRetention;
use App\Http\Requests\UpdateDeviceInstallationRequest;
use App\Models\AssetDeviceAssignment;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\SignalObservation;
use App\Models\TelemetryEvent;
use App\Positioning\BleObservationExtractor;
use App\Tenancy\OrganizationContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);
        $term = trim((string) ($validated['q'] ?? ''));
        $like = '%'.addcslashes($term, '\%_').'%';
        $normalized = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $term) ?? '');
        $normalizedLike = '%'.addcslashes($normalized, '\%_').'%';

        $devices = Device::query()
            ->with([
                'installations' => fn ($query) => $query
                    ->with(['floorPlan.location', 'location'])
                    ->whereNull('ended_at')
                    ->latest('started_at'),
                'assignments' => fn ($query) => $query
                    ->with(['asset.latestPosition.zone', 'asset.latestPosition.floorPlan', 'asset.latestPosition.location'])
                    ->whereNull('ended_at')
                    ->latest('started_at'),
            ])
            ->when($term !== '', function ($query) use ($like, $normalized, $normalizedLike): void {
                $query->where(function ($search) use ($like, $normalized, $normalizedLike): void {
                    $search->where('name', 'like', $like)
                        ->orWhere('identifier', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhere('type', 'like', $like);

                    if ($normalized !== '') {
                        $search->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(identifier, ':', ''), '-', ''), ' ', '')) LIKE ?", [$normalizedLike]);
                    }
                });
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();
        $normalizedDeviceIdentifiers = $devices
            ->getCollection()
            ->map(fn (Device $device): string => BleObservationExtractor::normalizeMac($device->identifier))
            ->filter()
            ->unique()
            ->values();
        $deviceIds = $devices->getCollection()->pluck('id');

        $lastSeenByDeviceId = TelemetryEvent::query()
            ->selectRaw('device_id, MAX(COALESCE(observed_at, received_at)) as last_seen_at')
            ->whereIn('device_id', $deviceIds)
            ->groupBy('device_id')
            ->pluck('last_seen_at', 'device_id');

        $observationsByTransmitter = SignalObservation::query()
            ->select(['transmitter_mac', 'receiver_identifier', 'rssi', 'observed_at'])
            ->whereIn('transmitter_mac', $normalizedDeviceIdentifiers)
            ->whereNotNull('receiver_identifier')
            ->latest('observed_at')
            ->get()
            ->groupBy(fn (SignalObservation $observation): string => BleObservationExtractor::normalizeMac($observation->transmitter_mac))
            ->map(fn (Collection $observations): Collection => $observations
                ->unique(fn (SignalObservation $observation): string => (string) $observation->receiver_identifier)
                ->take(3)
                ->values());

        $rows = $devices->getCollection()->map(function (Device $device) use ($lastSeenByDeviceId, $observationsByTransmitter): array {
            $installation = $device->installations->first();
            $assignment = $device->assignments->first();
            $position = $assignment?->asset?->latestPosition;
            $normalizedIdentifier = BleObservationExtractor::normalizeMac($device->identifier);
            $observations = $observationsByTransmitter->get($normalizedIdentifier, collect());
            $lastObservation = $observations->first();
            $lastSeenAt = collect([
                $device->last_seen_at,
                $lastSeenByDeviceId->get($device->id) ? Carbon::parse($lastSeenByDeviceId->get($device->id)) : null,
                $lastObservation?->observed_at,
                $position?->calculated_at,
            ])->filter()->sortDesc()->first();

            return [
                'device' => $device,
                'type_label' => match ($device->type) {
                    'beacon' => 'Beacon BLE',
                    'scanner' => 'Scanner/AP',
                    'lorawan_tracker' => 'Tracker LoRaWAN',
                    default => $device->type,
                },
                'installation' => $installation,
                'assignment' => $assignment,
                'position' => $position,
                'observations' => $observations,
                'last_seen_at' => $lastSeenAt,
                'location_label' => $this->deviceLocationLabel($installation, $assignment),
            ];
        });

        $devices->setCollection($rows);

        return view('devices.index', ['deviceRows' => $devices, 'search' => $term]);
    }

    public function apHistory(Request $request, Device $device): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $normalizedIdentifier = BleObservationExtractor::normalizeMac($device->identifier);
        if ($normalizedIdentifier === '') {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                    'total' => 0,
                    'retention_days' => MerakiEventRetention::RETENTION_DAYS,
                ],
            ]);
        }

        $history = SignalObservation::query()
            ->select(['receiver_identifier', 'rssi', 'observed_at', 'metadata'])
            ->where('transmitter_mac', $normalizedIdentifier)
            ->whereNotNull('receiver_identifier')
            ->where('observed_at', '>=', now()->subDays(MerakiEventRetention::RETENTION_DAYS))
            ->latest('observed_at')
            ->paginate(25, ['*'], 'page', (int) ($validated['page'] ?? 1))
            ->withQueryString();

        return response()->json([
            'data' => $history->getCollection()->map(fn (SignalObservation $observation): array => [
                'ap_mac' => $observation->receiver_identifier,
                'rssi' => $observation->rssi,
                'observed_at' => $observation->observed_at?->toIso8601String(),
                'observed_at_label' => $observation->observed_at?->format('d-m-Y H:i:s'),
                'observed_at_human' => $observation->observed_at?->diffForHumans(),
                'source' => data_get($observation->metadata, 'source'),
            ]),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'from' => $history->firstItem(),
                'to' => $history->lastItem(),
                'total' => $history->total(),
                'retention_days' => MerakiEventRetention::RETENTION_DAYS,
            ],
        ]);
    }

    public function installationDeviceOptions(Request $request, FloorPlan $floorPlan): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'in:beacon,scanner'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.addcslashes($term, '\%_').'%';
        $normalized = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $term) ?? '');
        $normalizedLike = '%'.addcslashes($normalized, '\%_').'%';

        $devices = Device::query()
            ->select(['id', 'name', 'identifier', 'model', 'type'])
            ->where('status', 'active')
            ->whereIn('type', ['beacon', 'scanner'])
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->whereDoesntHave('installations', fn ($query) => $query
                ->where('floor_plan_id', $floorPlan->id)
                ->whereNull('ended_at'))
            ->where(function ($query) use ($like, $normalized, $normalizedLike): void {
                $query->where('name', 'like', $like)
                    ->orWhere('identifier', 'like', $like)
                    ->orWhere('model', 'like', $like);

                if ($normalized !== '') {
                    $query->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(identifier, ':', ''), '-', ''), ' ', '')) LIKE ?", [$normalizedLike]);
                }
            })
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'text' => collect([
                    $device->name,
                    $device->type === 'scanner' ? 'Scanner/AP' : 'Beacon BLE',
                    $device->identifier,
                    $device->model,
                ])->filter()->join(' - '),
            ]);

        return response()->json(['results' => $devices]);
    }

    public function observedMacOptions(Request $request, FloorPlan $floorPlan, BleObservationExtractor $extractor): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $normalized = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $term) ?? '');
        if ($normalized === '') {
            return response()->json(['results' => []]);
        }

        $excluded = DeviceInstallation::query()
            ->with('device:id,identifier')
            ->where('floor_plan_id', $floorPlan->id)
            ->whereNull('ended_at')
            ->get()
            ->map(fn (DeviceInstallation $installation): string => BleObservationExtractor::normalizeMac($installation->device->identifier));

        $results = $this->signalObservationMacOptions($normalized, $excluded);
        if ($results->count() < 25) {
            $results = $results
                ->merge($this->telemetryPayloadMacOptions($extractor, $normalized, $excluded, 25 - $results->count()))
                ->unique('id')
                ->values();
        }

        return response()->json(['results' => $results->take(25)->values()]);
    }

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
            'reference_type' => ['nullable', 'in:beacon,scanner'],
            'device_type' => ['nullable', 'in:beacon,scanner'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'x_normalized' => ['nullable', 'required_without:x_meters', 'numeric', 'between:0,1'],
            'y_normalized' => ['nullable', 'required_without:y_meters', 'numeric', 'between:0,1'],
            'x_meters' => ['nullable', 'required_without:x_normalized', 'numeric', 'min:0'],
            'y_meters' => ['nullable', 'required_without:y_normalized', 'numeric', 'min:0'],
            'z_meters' => ['nullable', 'numeric', 'min:0'],
            'reference_rssi' => ['required', 'integer', 'between:-127,-1'],
            'path_loss_exponent' => ['required', 'numeric', 'between:0.5,8'],
        ]);

        try {
            DB::transaction(function () use ($validated, $floorPlan): void {
                $x = array_key_exists('x_meters', $validated) && $validated['x_meters'] !== null
                    ? (float) $validated['x_meters']
                    : (float) $validated['x_normalized'] * (float) $floorPlan->width_meters;
                $y = array_key_exists('y_meters', $validated) && $validated['y_meters'] !== null
                    ? (float) $validated['y_meters']
                    : (float) $validated['y_normalized'] * (float) $floorPlan->height_meters;
                $z = array_key_exists('z_meters', $validated) && $validated['z_meters'] !== null ? (float) $validated['z_meters'] : null;

                if ($x > (float) $floorPlan->width_meters) {
                    throw ValidationException::withMessages(['x_meters' => 'La posicion X debe estar dentro del ancho del plano.']);
                }
                if ($y > (float) $floorPlan->height_meters) {
                    throw ValidationException::withMessages(['y_meters' => 'La posicion Y debe estar dentro del largo del plano.']);
                }
                if ($floorPlan->depth_meters && $z !== null && $z > (float) $floorPlan->depth_meters) {
                    throw ValidationException::withMessages(['z_meters' => 'La altura Z debe estar dentro de la altura del modelo.']);
                }

                $referenceType = $validated['reference_type'] ?? $validated['device_type'] ?? null;

                if (! empty($validated['device_identifier'])) {
                    $deviceType = $referenceType ?? 'beacon';
                    $identifier = BleObservationExtractor::normalizeMac($validated['device_identifier']);
                    if (strlen($identifier) !== 12) {
                        throw ValidationException::withMessages(['device_identifier' => 'La MAC del dispositivo debe contener exactamente 12 digitos hexadecimales.']);
                    }
                    $existingDevice = Device::query()->get()->first(
                        fn (Device $candidate): bool => BleObservationExtractor::normalizeMac($candidate->identifier) === $identifier,
                    );
                    if ($existingDevice && $existingDevice->type !== $deviceType) {
                        throw ValidationException::withMessages([
                            'device_identifier' => sprintf(
                                'Esta MAC ya existe como %s. Selecciona el tipo correcto o usa otro dispositivo.',
                                $existingDevice->type === 'scanner' ? 'AP Meraki / scanner fijo' : 'Beacon BLE fijo',
                            ),
                        ]);
                    }
                    $device = $existingDevice ?? Device::query()->create([
                        'identifier' => $identifier,
                        'name' => ($validated['device_name'] ?? null) ?: ($deviceType === 'scanner' ? 'AP/scanner ' : 'Beacon fijo ').implode(':', str_split($identifier, 2)),
                        'type' => $deviceType,
                        'status' => 'active',
                    ]);
                } else {
                    $device = Device::query()->whereKey($validated['device_id'])->lockForUpdate()->first();
                    if (! $device) {
                        throw ValidationException::withMessages([
                            'device_id' => 'Selecciona un dispositivo activo de esta organizacion o crea uno nuevo por MAC.',
                        ]);
                    }
                    $referenceType ??= $device->type;
                    if ($device->type !== $referenceType) {
                        throw ValidationException::withMessages([
                            'device_id' => sprintf(
                                'El dispositivo seleccionado es %s, pero el formulario esta configurado para %s.',
                                $device->type === 'scanner' ? 'AP Meraki / scanner fijo' : 'Beacon BLE fijo',
                                $referenceType === 'scanner' ? 'AP Meraki / scanner fijo' : 'Beacon BLE fijo',
                            ),
                        ]);
                    }
                }
                if (DeviceInstallation::query()
                    ->where('device_id', $device->id)
                    ->where('floor_plan_id', $floorPlan->id)
                    ->whereNull('ended_at')
                    ->exists()) {
                    throw ValidationException::withMessages([
                        ! empty($validated['device_identifier']) ? 'device_identifier' : 'device_id' => 'Este dispositivo ya esta instalado en el plano seleccionado.',
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
                    'x' => $x,
                    'y' => $y,
                    'z' => $z,
                    'reference_rssi' => $validated['reference_rssi'],
                    'path_loss_exponent' => $validated['path_loss_exponent'],
                    'started_at' => now(),
                ]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'fixed_reference' => 'No se pudo guardar el punto de referencia. Revisa los datos e intenta nuevamente.',
            ]);
        }

        return redirect()->route('floor-plans.index', ['plan' => $floorPlan])->with('status', 'Punto de referencia ubicado en el plano.');
    }

    public function removeInstallation(DeviceInstallation $deviceInstallation): RedirectResponse
    {
        $floorPlanId = $deviceInstallation->floor_plan_id;
        $locationId = $deviceInstallation->location_id;
        $deviceInstallation->forceFill(['ended_at' => now()])->save();
        $plan = FloorPlan::query()->find($floorPlanId)
            ?? FloorPlan::query()->where('location_id', $locationId)->where('is_active', true)->first();

        return redirect()->route('floor-plans.index', ['plan' => $plan?->id])->with('status', 'Instalacion cerrada.');
    }

    public function updateInstallation(UpdateDeviceInstallationRequest $request, DeviceInstallation $deviceInstallation): RedirectResponse
    {
        $validated = $request->validated();
        $floorPlan = $deviceInstallation->floorPlan;
        if ((float) $validated['x_meters'] > (float) $floorPlan->width_meters) {
            throw ValidationException::withMessages(['x_meters' => 'La posicion X debe estar dentro del ancho del plano.']);
        }
        if ((float) $validated['y_meters'] > (float) $floorPlan->height_meters) {
            throw ValidationException::withMessages(['y_meters' => 'La posicion Y debe estar dentro del alto del plano.']);
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
            ->with('status', 'Parametros y posicion del dispositivo actualizados.');
    }

    private function signalObservationMacOptions(string $normalized, Collection $excluded): Collection
    {
        $normalizedLike = '%'.addcslashes($normalized, '\%_').'%';

        return SignalObservation::query()
            ->selectRaw('transmitter_mac, MAX(observed_at) as last_observed_at')
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(transmitter_mac, ':', ''), '-', ''), ' ', '')) LIKE ?", [$normalizedLike])
            ->groupBy('transmitter_mac')
            ->latest('last_observed_at')
            ->limit(25)
            ->get()
            ->toBase()
            ->map(function (SignalObservation $observation) use ($excluded): ?array {
                $normalizedMac = BleObservationExtractor::normalizeMac($observation->transmitter_mac);
                if (strlen($normalizedMac) !== 12 || $excluded->contains($normalizedMac)) {
                    return null;
                }

                $mac = implode(':', str_split($normalizedMac, 2));

                return [
                    'id' => $mac,
                    'text' => $mac.' - observado en telemetria',
                ];
            })
            ->filter()
            ->values();
    }

    private function telemetryPayloadMacOptions(BleObservationExtractor $extractor, string $normalized, Collection $excluded, int $limit): Collection
    {
        $reported = collect();
        $events = TelemetryEvent::query()
            ->select(['id', 'connector_id', 'device_id', 'received_at', 'normalized_payload'])
            ->with(['device:id,name', 'connector:id,name'])
            ->whereNotNull('normalized_payload')
            ->latest('received_at')
            ->limit(250)
            ->get();

        foreach ($events as $event) {
            $decoded = data_get($event->normalized_payload, 'decoded', []);

            foreach ($extractor->extract($decoded) as $observation) {
                $normalizedMac = BleObservationExtractor::normalizeMac($observation['mac']);
                if (strlen($normalizedMac) !== 12
                    || ! str_contains($normalizedMac, $normalized)
                    || $excluded->contains($normalizedMac)
                    || $reported->has($normalizedMac)) {
                    continue;
                }

                $mac = implode(':', str_split($normalizedMac, 2));
                $source = $event->device?->name
                    ?? data_get($event->normalized_payload, 'device_identifier', 'sensor sin nombre');
                $connector = $event->connector?->name;
                $rssi = $observation['rssi'] ?? null;

                $reported->put($normalizedMac, [
                    'id' => $mac,
                    'text' => collect([
                        $mac,
                        $source,
                        $rssi !== null ? 'RSSI '.$rssi.' dBm' : null,
                        $connector,
                    ])->filter()->join(' - '),
                ]);

                if ($reported->count() >= $limit) {
                    break 2;
                }
            }
        }

        return $reported->values();
    }

    private function deviceLocationLabel(?DeviceInstallation $installation, ?AssetDeviceAssignment $assignment): string
    {
        if ($installation) {
            $plan = $installation->floorPlan;
            $location = $plan?->location ?? $installation->location;
            $coordinates = $installation->x !== null && $installation->y !== null
                ? sprintf('X %.2f m, Y %.2f m', (float) $installation->x, (float) $installation->y)
                : null;

            return collect([$location?->name, $plan?->name, $coordinates])->filter()->join(' - ') ?: 'Instalacion fija';
        }

        $asset = $assignment?->asset;
        $position = $asset?->latestPosition;
        if (! $asset || ! $position) {
            return 'Sin ubicacion establecida';
        }

        if ($position->zone) {
            return collect([$asset->name, $position->zone->name, $position->floorPlan?->name])->filter()->join(' - ');
        }

        $coordinates = $position->x !== null && $position->y !== null
            ? sprintf('X %.2f m, Y %.2f m', (float) $position->x, (float) $position->y)
            : null;

        return collect([$asset->name, $position->floorPlan?->name ?? $position->location?->name, $coordinates])->filter()->join(' - ') ?: 'Sin area establecida';
    }
}
