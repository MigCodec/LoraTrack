<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\SignalObservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MerakiAccessPointController extends Controller
{
    public function __invoke(): View
    {
        $accessPoints = Device::query()
            ->with([
                'installations' => fn ($query) => $query
                    ->with(['floorPlan.location', 'location'])
                    ->whereNull('ended_at')
                    ->latest('started_at'),
            ])
            ->where('type', 'scanner')
            ->where('metadata->meraki->role', 'access_point_scanner')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $observationsByReceiver = $this->observationSummary($accessPoints->getCollection()->pluck('identifier'));

        $rows = $accessPoints->getCollection()->map(function (Device $accessPoint) use ($observationsByReceiver): array {
            $installation = $accessPoint->installations->first();
            $summary = $observationsByReceiver->get($accessPoint->identifier);
            $metadata = $accessPoint->metadata ?? [];
            $lastObservedAt = $summary?->last_observed_at ? Carbon::parse($summary->last_observed_at) : null;

            return [
                'access_point' => $accessPoint,
                'serial' => data_get($metadata, 'meraki.serial'),
                'network_id' => data_get($metadata, 'meraki.network_id'),
                'reported_latitude' => data_get($metadata, 'meraki.reported_latitude'),
                'reported_longitude' => data_get($metadata, 'meraki.reported_longitude'),
                'installation' => $installation,
                'status_label' => $installation ? 'Ubicado en plano' : 'Pendiente de plano',
                'status_class' => $installation ? 'active' : 'disabled',
                'location_label' => $this->locationLabel($installation),
                'clients_count' => (int) ($summary?->clients_count ?? 0),
                'last_observed_at' => collect([$accessPoint->last_seen_at, $lastObservedAt])->filter()->sortDesc()->first(),
            ];
        });

        $accessPoints->setCollection($rows);

        return view('meraki-access-points.index', ['accessPointRows' => $accessPoints]);
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
