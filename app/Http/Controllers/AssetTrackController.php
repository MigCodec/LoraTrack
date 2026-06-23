<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\FloorPlan;
use App\Models\PositionEstimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AssetTrackController extends Controller
{
    public function show(Request $request, Asset $asset): View
    {
        $asset->load(['sku.product', 'latestPosition.floorPlan', 'latestPosition.zone', 'latestPosition.telemetryEvent']);

        $plans = FloorPlan::query()
            ->whereIn('id', PositionEstimate::query()
                ->where('asset_id', $asset->id)
                ->whereNotNull('floor_plan_id')
                ->select('floor_plan_id'))
            ->orderBy('name')
            ->get();

        $selectedPlan = $plans->firstWhere('id', $request->query('plan'))
            ?? $asset->latestPosition?->floorPlan
            ?? $plans->first();

        return view('assets.track', [
            'asset' => $asset,
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
        ]);
    }

    public function data(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'floor_plan_id' => ['nullable', 'string'],
            'range' => ['nullable', 'in:1h,24h,7d,30d'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'after' => ['nullable', 'date'],
        ]);

        [$from, $to] = $this->window($validated);
        $floorPlan = $this->floorPlan($asset, $validated['floor_plan_id'] ?? null);

        $query = PositionEstimate::query()
            ->with(['floorPlan', 'zone', 'telemetryEvent'])
            ->where('asset_id', $asset->id)
            ->whereNotNull('floor_plan_id')
            ->whereBetween('calculated_at', [$from, $to])
            ->orderBy('calculated_at')
            ->orderBy('id');

        if ($floorPlan) {
            $query->where('floor_plan_id', $floorPlan->id);
        }

        if (! empty($validated['after'])) {
            $query->where('calculated_at', '>', Carbon::parse($validated['after']));
        }

        $positions = $query->limit(1000)->get();

        return response()->json([
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'asset_tag' => $asset->asset_tag,
                'mobility' => $asset->mobility,
                'last_seen_at' => $asset->last_seen_at?->toIso8601String(),
            ],
            'floor_plan' => $floorPlan ? [
                'id' => $floorPlan->id,
                'name' => $floorPlan->name,
                'width_meters' => (float) $floorPlan->width_meters,
                'height_meters' => (float) $floorPlan->height_meters,
            ] : null,
            'generated_at' => now()->toIso8601String(),
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'positions' => $positions->map(fn (PositionEstimate $position): array => $this->serializePosition($position))->values(),
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function window(array $validated): array
    {
        $to = ! empty($validated['to']) ? Carbon::parse($validated['to']) : now();
        $from = match ($validated['range'] ?? null) {
            '1h' => $to->copy()->subHour(),
            '7d' => $to->copy()->subDays(7),
            '30d' => $to->copy()->subDays(30),
            default => $to->copy()->subDay(),
        };

        if (! empty($validated['from'])) {
            $from = Carbon::parse($validated['from']);
        }

        return [$from, $to];
    }

    private function floorPlan(Asset $asset, ?string $floorPlanId): ?FloorPlan
    {
        $query = FloorPlan::query()
            ->whereIn('id', PositionEstimate::query()
                ->where('asset_id', $asset->id)
                ->whereNotNull('floor_plan_id')
                ->select('floor_plan_id'));

        if ($floorPlanId) {
            return $query->whereKey($floorPlanId)->firstOrFail();
        }

        return $asset->latestPosition?->floorPlan
            ?? $query->orderBy('name')->first();
    }

    private function serializePosition(PositionEstimate $position): array
    {
        $floorPlan = $position->floorPlan;
        $width = max(0.0001, (float) $floorPlan?->width_meters);
        $height = max(0.0001, (float) $floorPlan?->height_meters);
        $x = (float) $position->x;
        $y = (float) $position->y;
        $accuracy = max(0, (float) $position->accuracy_meters);
        $planDiagonal = hypot($width, $height);

        return [
            'id' => $position->id,
            'floor_plan_id' => $position->floor_plan_id,
            'floor_plan_name' => $floorPlan?->name,
            'zone' => $position->zone?->name,
            'x' => min(1, max(0, $x / $width)),
            'y' => min(1, max(0, $y / $height)),
            'x_meters' => round($x, 3),
            'y_meters' => round($y, 3),
            'out_of_bounds' => $x < 0 || $x > $width || $y < 0 || $y > $height,
            'confidence' => (float) $position->confidence,
            'accuracy_meters' => round($accuracy, 3),
            'relative_error' => $planDiagonal > 0 ? round($accuracy / $planDiagonal, 6) : 0,
            'algorithm' => $position->algorithm,
            'algorithm_version' => $position->algorithm_version,
            'calculated_at' => $position->calculated_at->toIso8601String(),
            'observed_at' => $position->telemetryEvent?->observed_at?->toIso8601String(),
            'received_at' => $position->telemetryEvent?->received_at?->toIso8601String(),
            'stale' => $position->calculated_at->lt(now()->subMinutes(10)),
            'evidence_count' => count($position->evidence ?? []),
        ];
    }
}
