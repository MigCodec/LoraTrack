<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FloorPlan;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ZoneController extends Controller
{
    public function store(Request $request, FloorPlan $floorPlan): RedirectResponse
    {
        $validated = $this->validateZone($request, $floorPlan->id);
        $floorPlan->zones()->create($this->attributes($validated));

        return redirect()->route('floor-plans.index', ['plan' => $floorPlan])->with('status', 'Zona creada.');
    }

    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $validated = $this->validateZone($request, $zone->floor_plan_id, $zone);
        foreach (['x_min', 'y_min', 'x_max', 'y_max'] as $coordinate) {
            $validated[$coordinate] ??= $zone->{$coordinate};
        }
        $zone->update($this->attributes($validated));

        return back()->with('status', 'Área actualizada.');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        $plan = $zone->floor_plan_id;
        $zone->delete();

        return redirect()->route('floor-plans.index', ['plan' => $plan])->with('status', 'Zona eliminada.');
    }

    /** @return array<string, mixed> */
    private function validateZone(Request $request, string $floorPlanId, ?Zone $zone = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('zones')->where('floor_plan_id', $floorPlanId)->ignore($zone)],
            'code' => ['nullable', 'string', 'max:64'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'x_min' => [$zone ? 'sometimes' : 'required', 'numeric', 'between:0,1'],
            'y_min' => [$zone ? 'sometimes' : 'required', 'numeric', 'between:0,1'],
            'x_max' => [$zone ? 'sometimes' : 'required', 'numeric', 'between:0,1'],
            'y_max' => [$zone ? 'sometimes' : 'required', 'numeric', 'between:0,1'],
        ]);

        $coordinates = $zone ? [
            'x_min' => $validated['x_min'] ?? $zone->x_min, 'y_min' => $validated['y_min'] ?? $zone->y_min,
            'x_max' => $validated['x_max'] ?? $zone->x_max, 'y_max' => $validated['y_max'] ?? $zone->y_max,
        ] : $validated;
        if ((float) $coordinates['x_min'] >= (float) $coordinates['x_max']
            || (float) $coordinates['y_min'] >= (float) $coordinates['y_max']) {
            throw ValidationException::withMessages(['zone' => 'Dibuja un rectángulo con ancho y alto mayores que cero.']);
        }

        return $validated;
    }

    /** @param array<string, mixed> $validated */
    private function attributes(array $validated): array
    {
        return [
            ...collect($validated)->only(['name', 'code', 'color', 'x_min', 'y_min', 'x_max', 'y_max'])->all(),
            'shape' => 'rectangle',
            'geometry' => [
                'type' => 'Rectangle',
                'coordinates' => [
                    [(float) $validated['x_min'], (float) $validated['y_min']],
                    [(float) $validated['x_max'], (float) $validated['y_max']],
                ],
            ],
        ];
    }
}
