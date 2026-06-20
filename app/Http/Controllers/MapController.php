<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DeviceInstallation;
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
        $positions = PositionEstimate::query()->with(['asset.sku.product', 'zone', 'telemetryEvent'])->where('floor_plan_id', $floorPlan->id)->whereIn('id', PositionEstimate::query()->selectRaw('MAX(id)')->where('floor_plan_id', $floorPlan->id)->groupBy('asset_id'))->get();
        $anchors = DeviceInstallation::query()
            ->with('device')
            ->where('floor_plan_id', $floorPlan->id)
            ->whereNull('ended_at')
            ->whereHas('device', fn ($query) => $query->whereIn('type', ['beacon', 'scanner']))
            ->get();
        $installationsByIdentifier = $anchors->keyBy(fn (DeviceInstallation $installation): string => $installation->device->identifier);

        return response()->json(['generated_at' => now()->toIso8601String(), 'anchors' => $anchors->map(fn ($installation): array => [
            'id' => $installation->id,
            'name' => $installation->device->name,
            'identifier' => $installation->device->identifier,
            'type' => $installation->device->type,
            'x' => min(1, max(0, (float) $installation->x / (float) $floorPlan->width_meters)),
            'y' => min(1, max(0, (float) $installation->y / (float) $floorPlan->height_meters)),
        ])->values(), 'positions' => $positions->map(function ($p) use ($floorPlan, $installationsByIdentifier): array {
            $x = (float) $p->x / (float) $floorPlan->width_meters;
            $y = (float) $p->y / (float) $floorPlan->height_meters;
            $accuracyMeters = max(0, (float) $p->accuracy_meters);
            $planDiagonal = hypot((float) $floorPlan->width_meters, (float) $floorPlan->height_meters);
            $evidence = collect($p->evidence ?? [])->map(function (array $item) use ($floorPlan, $installationsByIdentifier, $p): array {
                $identifier = (string) ($item['anchor'] ?? '');
                $installation = $installationsByIdentifier->get($identifier);
                $anchorX = (float) ($item['x'] ?? 0);
                $anchorY = (float) ($item['y'] ?? 0);
                $estimatedDistance = max(0, (float) ($item['estimated_distance'] ?? 0));
                $geometricDistance = hypot((float) $p->x - $anchorX, (float) $p->y - $anchorY);

                return [
                    'identifier' => $identifier,
                    'name' => $installation?->device->name ?? $identifier,
                    'type' => $installation?->device->type ?? 'anchor',
                    'rssi' => (int) ($item['rssi'] ?? 0),
                    'estimated_distance_meters' => round($estimatedDistance, 3),
                    'geometric_distance_meters' => round($geometricDistance, 3),
                    'residual_meters' => round($geometricDistance - $estimatedDistance, 3),
                    'reference_rssi' => $installation?->reference_rssi,
                    'path_loss_exponent' => $installation?->path_loss_exponent,
                    'x_meters' => $anchorX,
                    'y_meters' => $anchorY,
                    'x' => min(1, max(0, $anchorX / (float) $floorPlan->width_meters)),
                    'y' => min(1, max(0, $anchorY / (float) $floorPlan->height_meters)),
                    'circle_diameter_x' => ($estimatedDistance * 2) / (float) $floorPlan->width_meters,
                    'circle_diameter_y' => ($estimatedDistance * 2) / (float) $floorPlan->height_meters,
                ];
            })->values();

            return ['asset_id' => $p->asset_id, 'name' => $p->asset->name, 'sku' => $p->asset->sku?->code, 'product' => $p->asset->sku?->product?->name, 'zone' => $p->zone?->name, 'x' => min(1, max(0, $x)), 'y' => min(1, max(0, $y)), 'x_meters' => round((float) $p->x, 3), 'y_meters' => round((float) $p->y, 3), 'out_of_bounds' => $x < 0 || $x > 1 || $y < 0 || $y > 1, 'confidence' => (float) $p->confidence, 'accuracy_meters' => round($accuracyMeters, 3), 'relative_error' => $planDiagonal > 0 ? round($accuracyMeters / $planDiagonal, 6) : 0, 'error_radius_x' => $accuracyMeters / (float) $floorPlan->width_meters, 'error_radius_y' => $accuracyMeters / (float) $floorPlan->height_meters, 'algorithm' => $p->algorithm, 'algorithm_version' => $p->algorithm_version, 'calculated_at' => $p->calculated_at->toIso8601String(), 'observed_at' => $p->telemetryEvent?->observed_at?->toIso8601String(), 'received_at' => $p->telemetryEvent?->received_at?->toIso8601String(), 'stale' => $p->calculated_at->lt(now()->subMinutes(10)), 'evidence' => $evidence];
        })->values()]);
    }
}
