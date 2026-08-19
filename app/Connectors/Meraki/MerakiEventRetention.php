<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Models\Organization;
use App\Models\TelemetryEvent;
use App\Telemetry\TenantRetentionPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MerakiEventRetention
{
    public function prune(TelemetryEvent $event, int $limit = 1000): int
    {
        if (! $event->device_id || $event->event_type !== 'meraki_location') {
            return 0;
        }
        $organization = Organization::query()->find($event->organization_id);
        if (! $organization?->storage_cleanup_enabled) {
            return 0;
        }

        return $this->deleteStaleQuery($organization, $limit)
            ->where('connector_id', $event->connector_id)
            ->where('device_id', $event->device_id)
            ->whereKeyNot($event->id)
            ->delete();
    }

    public function pruneAll(Organization $organization, int $maxDeletes = 0): int
    {
        $deleted = 0;
        do {
            $remaining = $maxDeletes > 0 ? $maxDeletes - $deleted : 1000;
            if ($remaining <= 0) {
                break;
            }
            $batchDeleted = $this->deleteStaleQuery($organization, min(1000, $remaining))->delete();
            $deleted += $batchDeleted;
        } while ($batchDeleted > 0 && ($maxDeletes === 0 || $deleted < $maxDeletes));

        return $deleted;
    }

    public function staleCount(Organization $organization): int
    {
        return $this->staleQuery($organization)->count();
    }

    public function cutoff(Organization $organization): Carbon
    {
        return now()->subDays(TenantRetentionPolicy::for($organization)->telemetryDays);
    }

    private function deleteStaleQuery(Organization $organization, int $limit): Builder
    {
        $ids = $this->staleQuery($organization)
            ->orderBy('observed_at')->orderBy('received_at')->orderBy('id')
            ->limit(max(1, min(1000, $limit)))->pluck('id');

        return TelemetryEvent::query()->withoutGlobalScopes()->whereIn('id', $ids);
    }

    private function staleQuery(Organization $organization): Builder
    {
        $cutoff = $this->cutoff($organization);

        return TelemetryEvent::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('event_type', 'meraki_location')
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('observed_at', '<', $cutoff)
                    ->orWhere(function (Builder $receivedQuery) use ($cutoff): void {
                        $receivedQuery->whereNull('observed_at')->where('received_at', '<', $cutoff);
                    });
            });
    }
}
