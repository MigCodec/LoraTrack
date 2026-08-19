<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Models\Organization;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TelemetryStorageCleaner
{
    public const BATCH_SIZE = 1000;

    /**
     * @return array{telemetry_events: int, position_estimates: int, operational_logs: int, resolved_alerts: int, terminal_inbox: int, recovered_inbox: int}
     */
    public function clean(Organization $organization, int $maxDeletesPerCategory = 0): array
    {
        $policy = TenantRetentionPolicy::for($organization);
        $recovered = DB::table('meraki_webhook_batches')
            ->where('organization_id', $organization->id)
            ->where('processing_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes((int) config('telemetry-retention.stale_processing_minutes', 30)))
            ->update([
                'processing_status' => 'failed',
                'processing_error' => 'Ejecución interrumpida; lote recuperado automáticamente para reintento.',
                'updated_at' => now(),
            ]);

        return [
            'telemetry_events' => $this->deleteInBatches(
                $this->expiredTelemetryQuery($organization, $policy),
                'telemetry_events', $maxDeletesPerCategory, ['received_at', 'id'],
            ),
            'position_estimates' => $this->deleteInBatches(
                DB::table('position_estimates')->where('organization_id', $organization->id)
                    ->where('calculated_at', '<', now()->subDays($policy->positionHistoryDays))
                    ->whereExists(function (Builder $newer): void {
                        $newer->selectRaw('1')->from('position_estimates as newer_position')
                            ->whereColumn('newer_position.asset_id', 'position_estimates.asset_id')
                            ->where(function (Builder $chronology): void {
                                $chronology->whereColumn('newer_position.calculated_at', '>', 'position_estimates.calculated_at')
                                    ->orWhere(function (Builder $sameTime): void {
                                        $sameTime->whereColumn('newer_position.calculated_at', '=', 'position_estimates.calculated_at')
                                            ->whereColumn('newer_position.id', '>', 'position_estimates.id');
                                    });
                            });
                    }),
                'position_estimates', $maxDeletesPerCategory, ['calculated_at', 'id'],
            ),
            'operational_logs' => $this->deleteInBatches(
                DB::table('connector_activity_logs')->where('organization_id', $organization->id)
                    ->where('created_at', '<', now()->subDays($policy->operationalLogDays)),
                'connector_activity_logs', $maxDeletesPerCategory, ['created_at', 'id'],
            ) + $this->deleteInBatches(
                DB::table('audit_logs')->where('organization_id', $organization->id)
                    ->where('created_at', '<', now()->subDays($policy->operationalLogDays)),
                'audit_logs', $maxDeletesPerCategory, ['created_at', 'id'],
            ),
            'resolved_alerts' => $this->deleteInBatches(
                DB::table('alerts')->where('organization_id', $organization->id)
                    ->whereNotNull('resolved_at')
                    ->where('resolved_at', '<', now()->subDays($policy->operationalLogDays)),
                'alerts', $maxDeletesPerCategory, ['resolved_at', 'id'],
            ),
            'terminal_inbox' => $this->deleteInBatches(
                DB::table('meraki_webhook_batches')->where('organization_id', $organization->id)
                    ->where('received_at', '<', now()->subDays($policy->terminalInboxDays)),
                'meraki_webhook_batches', $maxDeletesPerCategory, ['received_at', 'id'],
            ),
            'recovered_inbox' => $recovered,
        ];
    }

    public function deleteOldestBatch(Organization $organization): int
    {
        return $this->deleteInBatches(
            $this->expiredTelemetryQuery($organization, TenantRetentionPolicy::for($organization)),
            'telemetry_events', self::BATCH_SIZE, ['received_at', 'id'],
        );
    }

    public function cutoff(Organization $organization): Carbon
    {
        return now()->subDays(TenantRetentionPolicy::for($organization)->telemetryDays);
    }

    private function expiredTelemetryQuery(Organization $organization, TenantRetentionPolicy $policy): Builder
    {
        return DB::table('telemetry_events')->where('organization_id', $organization->id)
            ->where('received_at', '<', now()->subDays($policy->telemetryDays));
    }

    /** @param list<string> $orderBy */
    private function deleteInBatches(Builder $query, string $table, int $maximum, array $orderBy): int
    {
        $deleted = 0;
        do {
            $limit = $maximum > 0 ? min(self::BATCH_SIZE, $maximum - $deleted) : self::BATCH_SIZE;
            if ($limit <= 0) {
                break;
            }
            $idsQuery = clone $query;
            foreach ($orderBy as $column) {
                $idsQuery->orderBy($column);
            }
            $ids = $idsQuery->limit($limit)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $batchDeleted = DB::table($table)->whereIn('id', $ids)->delete();
            $deleted += $batchDeleted;
        } while ($batchDeleted > 0 && ($maximum === 0 || $deleted < $maximum));

        return $deleted;
    }
}
