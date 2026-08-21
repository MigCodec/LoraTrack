<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloorPlanRequest;
use App\Http\Requests\UpdateFloorPlanRequest;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\PositionEstimate;
use App\Tenancy\OrganizationContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FloorPlanController extends Controller
{
    public function index(Request $request): View
    {
        $plans = FloorPlan::query()->with(['location', 'zones.alertRules'])->latest()->get();
        $selectedPlan = $plans->firstWhere('id', $request->query('plan')) ?? $plans->first();
        $installations = $selectedPlan
            ? DeviceInstallation::query()->with('device')->where('floor_plan_id', $selectedPlan->id)->whereNull('ended_at')->get()
            : collect();
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
            'devices' => collect(),
            'reportedBeaconMacs' => collect(),
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
            'assetPositions' => $assetPositions,
            'installations' => $installations,
        ]);
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
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'plan' => 'El archivo temporal no está disponible. Selecciónalo nuevamente e intenta otra vez.',
            ]);
        }
        $root = 'organizations/'.app(OrganizationContext::class)->id().'/floor-plans';
        $path = $this->storeUploadedFile($file, $root, 'plan');
        $previewPath = null;
        $preview = $request->file('preview');
        if ($preview instanceof UploadedFile) {
            try {
                $previewPath = $this->storeUploadedFile($preview, $root.'/previews', 'preview');
            } catch (ValidationException $exception) {
                Storage::disk('local')->delete($path);

                throw $exception;
            }
        }

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

    private function storeUploadedFile(UploadedFile $file, string $directory, string $field): string
    {
        $temporaryPath = $file->getPathname();
        if ($temporaryPath === '' || ! is_file($temporaryPath) || ! is_readable($temporaryPath)) {
            throw ValidationException::withMessages([
                $field => 'El archivo temporal no está disponible. Selecciónalo nuevamente e intenta otra vez.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $path = trim($directory, '/').'/'.Str::ulid().($extension !== '' ? '.'.$extension : '');
        $stream = fopen($temporaryPath, 'rb');
        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                $field => 'No fue posible leer el archivo temporal. Verifica sus permisos e intenta nuevamente.',
            ]);
        }

        try {
            $stored = Storage::disk('local')->put($path, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw ValidationException::withMessages([
                $field => 'No fue posible almacenar el archivo. Verifica el almacenamiento e intenta nuevamente.',
            ]);
        }

        return $path;
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
        if ($request->exists('width_meters') || $request->exists('height_meters')) {
            return redirect()->route('floor-plans.index', ['plan' => $floorPlan])->with('status', 'Escala del plano actualizada.');
        }
        $status = $request->exists('name') ? 'Nombre del plano actualizado.' : 'Color de la pestaña actualizado.';

        return redirect()->route('floor-plans.index', ['plan' => $floorPlan])->with('status', $status);
    }
}
