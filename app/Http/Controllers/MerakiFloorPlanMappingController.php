<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectorProvider;
use App\Models\Connector;
use App\Models\FloorPlan;
use App\Models\MerakiFloorPlanMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerakiFloorPlanMappingController extends Controller
{
    public function store(Request $request, Connector $connector): RedirectResponse
    {
        abort_unless($connector->provider === ConnectorProvider::MerakiLocation, 422);
        $validated = $request->validate([
            'floor_plan_id' => ['required', Rule::exists(FloorPlan::class, 'id')],
            'external_floor_plan_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique(MerakiFloorPlanMapping::class)->where('connector_id', $connector->id),
            ],
            'external_floor_plan_name' => ['nullable', 'string', 'max:255'],
            'invert_y' => ['nullable', 'boolean'],
        ]);

        $connector->merakiFloorPlanMappings()->create([
            ...$validated,
            'invert_y' => $request->boolean('invert_y'),
        ]);
        $connector->logActivity('floor_plan_mapped', 'Plano Meraki asociado a un plano de LoraTrack.', 'info', [
            'external_floor_plan_id' => $validated['external_floor_plan_id'],
            'floor_plan_id' => $validated['floor_plan_id'],
        ]);

        return back()->with('status', 'Mapeo de plano Meraki guardado.');
    }

    public function destroy(Connector $connector, MerakiFloorPlanMapping $mapping): RedirectResponse
    {
        abort_unless(
            $connector->provider === ConnectorProvider::MerakiLocation
            && $mapping->connector_id === $connector->id,
            404,
        );
        $mapping->delete();

        return back()->with('status', 'Mapeo de plano eliminado.');
    }
}
