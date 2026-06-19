<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FloorPlan;
use App\Models\PositionEstimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapController extends Controller
{
    public function index(Request $request): View
    {
        $plans = FloorPlan::query()->with(['location', 'zones'])->where('is_active', true)->get();
        $plan = $plans->firstWhere('id', $request->query('plan')) ?? $plans->first();

        return view('map.index', compact('plans', 'plan'));
    }

    public function data(FloorPlan $floorPlan): JsonResponse
    {
        $positions = PositionEstimate::query()->with(['asset.sku.product', 'zone'])->where('floor_plan_id', $floorPlan->id)->whereIn('id', PositionEstimate::query()->selectRaw('MAX(id)')->where('floor_plan_id', $floorPlan->id)->groupBy('asset_id'))->get();

        return response()->json(['generated_at' => now()->toIso8601String(), 'positions' => $positions->map(function ($p) use ($floorPlan): array {
            $x = (float) $p->x / (float) $floorPlan->width_meters;
            $y = (float) $p->y / (float) $floorPlan->height_meters;

            return ['asset_id' => $p->asset_id, 'name' => $p->asset->name, 'sku' => $p->asset->sku?->code, 'product' => $p->asset->sku?->product?->name, 'zone' => $p->zone?->name, 'x' => min(1, max(0, $x)), 'y' => min(1, max(0, $y)), 'out_of_bounds' => $x < 0 || $x > 1 || $y < 0 || $y > 1, 'confidence' => (float) $p->confidence, 'calculated_at' => $p->calculated_at->toIso8601String(), 'stale' => $p->calculated_at->lt(now()->subMinutes(10))];
        })->values()]);
    }
}
