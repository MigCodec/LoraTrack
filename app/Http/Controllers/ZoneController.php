<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FloorPlan;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ZoneController extends Controller
{
    public function store(Request $request, FloorPlan $floorPlan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'x_min' => ['required', 'numeric', 'between:0,1'],
            'y_min' => ['required', 'numeric', 'between:0,1'],
            'x_max' => ['required', 'numeric', 'between:0,1'],
            'y_max' => ['required', 'numeric', 'between:0,1'],
            'alert_types' => ['nullable', 'array'],
            'alert_types.*' => ['in:entry,exit,dwell'],
            'alert_recipients' => ['nullable', 'string', 'max:4000'],
            'dwell_minutes' => ['nullable', 'integer', 'between:10,10080'],
        ]);

        if ((float) $validated['x_min'] >= (float) $validated['x_max']
            || (float) $validated['y_min'] >= (float) $validated['y_max']) {
            throw ValidationException::withMessages(['zone' => 'Dibuja un rectángulo con ancho y alto mayores que cero.']);
        }

        $alertTypes = $validated['alert_types'] ?? [];
        if (in_array('dwell', $alertTypes, true) && empty($validated['dwell_minutes'])) {
            throw ValidationException::withMessages(['dwell_minutes' => 'Indica el tiempo máximo de permanencia.']);
        }
        $recipients = empty($alertTypes) ? [] : ZoneAlertRuleController::recipients($validated['alert_recipients'] ?? '');

        DB::transaction(function () use ($floorPlan, $validated, $alertTypes, $recipients): void {
            $zone = $floorPlan->zones()->create([
                ...collect($validated)->only(['name', 'code', 'color', 'x_min', 'y_min', 'x_max', 'y_max'])->all(),
                'shape' => 'rectangle',
                'geometry' => [
                    'type' => 'Rectangle',
                    'coordinates' => [
                        [(float) $validated['x_min'], (float) $validated['y_min']],
                        [(float) $validated['x_max'], (float) $validated['y_max']],
                    ],
                ],
            ]);
            foreach ($alertTypes as $eventType) {
                $zone->alertRules()->create([
                    'event_type' => $eventType,
                    'dwell_minutes' => $eventType === 'dwell' ? $validated['dwell_minutes'] : null,
                    'recipients' => $recipients,
                ]);
            }
        });

        return redirect()->route('floor-plans.index', ['plan' => $floorPlan])->with('status', 'Zona creada.');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        $plan = $zone->floor_plan_id;
        $zone->delete();

        return redirect()->route('floor-plans.index', ['plan' => $plan])->with('status', 'Zona eliminada.');
    }

    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('zones')->where('floor_plan_id', $zone->floor_plan_id)->ignore($zone)],
            'code' => ['nullable', 'string', 'max:64'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'name.required' => 'Indica un nombre para el área.',
            'name.unique' => 'Ya existe un área con ese nombre en el plano.',
            'color.required' => 'Selecciona un color.',
            'color.regex' => 'El color seleccionado no es válido.',
        ]);

        $zone->update($validated);

        return back()->with('status', 'Área actualizada.');
    }
}
