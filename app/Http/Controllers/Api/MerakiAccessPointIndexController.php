<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\SignalObservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MerakiAccessPointIndexController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);
        $term = trim((string) ($validated['q'] ?? ''));

        $accessPoints = Device::query()
            ->with([
                'installations' => fn ($query) => $query
                    ->with(['floorPlan.location', 'location'])
                    ->whereNull('ended_at')
                    ->latest('started_at'),
            ])
            ->where('type', 'scanner')
            ->where('metadata->meraki->role', 'access_point_scanner')
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '\%_').'%';
                $normalized = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $term) ?? '');
                $normalizedLike = '%'.addcslashes($normalized, '\%_').'%';

                $query->where(function ($search) use ($like, $normalized, $normalizedLike): void {
                    $search->where('name', 'like', $like)
                        ->orWhere('identifier', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhere('metadata->meraki->serial', 'like', $like)
                        ->orWhere('metadata->meraki->network_id', 'like', $like);

                    if ($normalized !== '') {
                        $search->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(identifier, ':', ''), '-', ''), ' ', '')) LIKE ?", [$normalizedLike]);
                    }
                });
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $observationsByReceiver = $this->observationSummary($accessPoints->getCollection()->pluck('identifier'));

        $rows = $accessPoints->getCollection()->map(function (Device $accessPoint) use ($observationsByReceiver): array {
            $installation = $accessPoint->installations->first();
            $summary = $observationsByReceiver->get($accessPoint->identifier);
            $metadata = $accessPoint->metadata ?? [];
            $lastObservedAt = $summary?->last_observed_at ? Carbon::parse($summary->last_observed_at) : null;
            $lastActivityAt = collect([$accessPoint->last_seen_at, $lastObservedAt])->filter()->sortDesc()->first();

            return [
                'id' => $accessPoint->id,
                'name' => $accessPoint->name,
                'identifier' => $accessPoint->identifier,
                'model' => $accessPoint->model,
                'serial' => data_get($metadata, 'meraki.serial'),
                'network_id' => data_get($metadata, 'meraki.network_id'),
                'reported_latitude' => data_get($metadata, 'meraki.reported_latitude'),
                'reported_longitude' => data_get($metadata, 'meraki.reported_longitude'),
                'status_label' => $installation ? 'Ubicado en plano' : 'Pendiente de plano',
                'status_class' => $installation ? 'active' : 'disabled',
                'location_label' => $this->locationLabel($installation),
                'clients_count' => (int) ($summary?->clients_count ?? 0),
                'last_activity_human' => $lastActivityAt?->diffForHumans() ?? 'Sin senal',
                'last_activity_at' => $lastActivityAt?->toIso8601String(),
                'last_activity_label' => $lastActivityAt?->format('d-m-Y H:i'),
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $accessPoints->currentPage(),
                'from' => $accessPoints->firstItem(),
                'last_page' => $accessPoints->lastPage(),
                'per_page' => $accessPoints->perPage(),
                'to' => $accessPoints->lastItem(),
                'total' => $accessPoints->total(),
            ],
            'links' => [
                'first' => $accessPoints->url(1),
                'last' => $accessPoints->url($accessPoints->lastPage()),
                'next' => $accessPoints->nextPageUrl(),
                'prev' => $accessPoints->previousPageUrl(),
            ],
        ]);
    }

    private function observationSummary(Collection $identifiers): Collection
    {
        $receiverIdentifiers = $identifiers
            ->filter()
            ->unique()
            ->values();

        if ($receiverIdentifiers->isEmpty()) {
            return collect();
        }

        return SignalObservation::query()
            ->selectRaw('receiver_identifier, COUNT(DISTINCT transmitter_mac) as clients_count, MAX(observed_at) as last_observed_at')
            ->whereIn('receiver_identifier', $receiverIdentifiers)
            ->whereNotNull('receiver_identifier')
            ->groupBy('receiver_identifier')
            ->get()
            ->keyBy('receiver_identifier');
    }

    private function locationLabel(?DeviceInstallation $installation): string
    {
        if (! $installation) {
            return 'Sin ubicacion establecida';
        }

        $plan = $installation->floorPlan;
        $location = $plan?->location ?? $installation->location;
        $coordinates = $installation->x !== null && $installation->y !== null
            ? sprintf('X %.2f m, Y %.2f m', (float) $installation->x, (float) $installation->y)
            : null;

        return collect([$location?->name, $plan?->name, $coordinates])->filter()->join(' - ') ?: 'Instalacion fija';
    }
}
