<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDeviceInstallationRequest;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeviceController extends Controller
{
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
        $events = TelemetryEvent::query()->with(['device', 'connector'])->latest('received_at')->limit(250)->get();

        foreach ($events as $event) {
            $decoded = data_get($event->normalized_payload, 'decoded')
                ?? data_get($event->raw_payload, 'uplink_message.decoded_payload', []);

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
                    ?? data_get($event->raw_payload, 'end_device_ids.device_id', 'sensor sin nombre');
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
}
