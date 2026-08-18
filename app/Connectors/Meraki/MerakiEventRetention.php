<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Models\Organization;
use App\Models\SignalObservation;
use App\Models\TelemetryEvent;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;

class MerakiEventRetention
{
    public const DEFAULT_RETENTION_DAYS = 2;

    public function prune(TelemetryEvent $event, int $limit = 1000): int
    {
        if (! $event->device_id || $event->event_type !== 'meraki_location') {
            return 0;
        }

        $organization = $event->organization()->first();
        if (! $organization) {
            return 0;
        }

        return $this->deleteStaleQuery($this->cutoff($organization), $limit, $organization->id)
            ->where('organization_id', $event->organization_id)
            ->where('connector_id', $event->connector_id)
            ->where('device_id', $event->device_id)
            ->whereKeyNot($event->id)
            ->delete();
    }

    public function pruneAll(int $maxDeletes = 10000): int
    {
        $deleted = 0;
        do {
            $remaining = $maxDeletes - $deleted;
            if ($remaining <= 0) {
                break;
            }

            $batchDeleted = $this->pruneBatch(min(1000, $remaining))['events'];
            $deleted += $batchDeleted;
        } while ($batchDeleted > 0 && $deleted < $maxDeletes);

        return $deleted;
    }

    /** @return array{events: int, observations: int} */
    public function pruneBatch(int $limit, bool $countObservations = false): array
    {
        $ids = $this->staleIdsAcrossOrganizations($limit);
        if ($ids->isEmpty()) {
            return ['events' => 0, 'observations' => 0];
        }

        $observations = $countObservations
            ? SignalObservation::query()->withoutGlobalScopes()->whereIn('telemetry_event_id', $ids)->count()
            : 0;
        $events = TelemetryEvent::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->delete();

        return ['events' => $events, 'observations' => $observations];
    }

    /** @return array{events: int, observations: int} */
    public function previewBatch(int $limit, bool $countObservations = false): array
    {
        $ids = $this->staleIdsAcrossOrganizations($limit);

        return [
            'events' => $ids->count(),
            'observations' => $countObservations
                ? SignalObservation::query()->withoutGlobalScopes()->whereIn('telemetry_event_id', $ids)->count()
                : 0,
        ];
    }

    public function staleCount(): int
    {
        return Organization::query()->get()->sum(
            fn (Organization $organization): int => $this->staleQuery($this->cutoff($organization), $organization->id)->count(),
        );
    }

    public function retentionDays(?Organization $organization = null): int
    {
        $organization ??= app(OrganizationContext::class)->organization();

        return max(1, (int) ($organization?->meraki_retention_days ?? self::DEFAULT_RETENTION_DAYS));
    }

    public function cutoff(?Organization $organization = null): Carbon
    {
        return now()->subDays($this->retentionDays($organization));
    }

    private function deleteStaleQuery(Carbon $cutoff, int $limit, string $organizationId)
    {
        $ids = $this->staleIds($cutoff, $limit, $organizationId);

        return TelemetryEvent::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids);
    }

    private function staleIdsAcrossOrganizations(int $limit)
    {
        $ids = collect();
        foreach (Organization::query()->orderBy('id')->get() as $organization) {
            $remaining = $limit - $ids->count();
            if ($remaining <= 0) {
                break;
            }
            $ids->push(...$this->staleIds($this->cutoff($organization), $remaining, $organization->id));
        }

        return $ids;
    }

    private function staleIds(Carbon $cutoff, int $limit, string $organizationId)
    {
        return $this->staleQuery($cutoff, $organizationId)
            ->orderBy('observed_at')
            ->orderBy('received_at')
            ->orderBy('id')
            ->limit(max(1, min(1000, $limit)))
            ->pluck('id');
    }

    private function staleQuery(Carbon $cutoff, string $organizationId)
    {
        return TelemetryEvent::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('event_type', 'meraki_location')
            ->where(function ($query) use ($cutoff): void {
                $query->where('observed_at', '<', $cutoff)
                    ->orWhere(function ($receivedQuery) use ($cutoff): void {
                        $receivedQuery->whereNull('observed_at')
                            ->where('received_at', '<', $cutoff);
                    });
            });
    }
}
