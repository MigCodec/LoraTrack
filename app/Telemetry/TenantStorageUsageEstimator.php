<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class TenantStorageUsageEstimator
{
    private const ESTIMATED_ROW_OVERHEAD_BYTES = 128;

    /** @return array{telemetry: int, positions: int, operational: int, meraki_inbox: int, total: int} */
    public function estimate(Organization $organization): array
    {
        $telemetry = $this->tableBytes('telemetry_events', $organization->id, [
            'id', 'connector_id', 'device_id', 'external_event_id', 'event_type', 'normalized_payload',
            'raw_payload', 'processing_status', 'processing_error',
        ]) + $this->tableBytes('signal_observations', $organization->id, [
            'id', 'telemetry_event_id', 'transmitter_mac', 'receiver_identifier', 'metadata',
        ]);
        $positions = $this->tableBytes('position_estimates', $organization->id, [
            'id', 'asset_id', 'location_id', 'floor_plan_id', 'zone_id', 'telemetry_event_id',
            'algorithm', 'algorithm_version', 'evidence', 'filter_state',
        ]);
        $operational = $this->tableBytes('connector_activity_logs', $organization->id, [
            'id', 'connector_id', 'level', 'event', 'message', 'context',
        ]) + $this->tableBytes('audit_logs', $organization->id, [
            'id', 'request_id', 'method', 'route_name', 'path', 'action', 'subject_type',
            'subject_id', 'ip_address', 'context',
        ]) + $this->tableBytes('alerts', $organization->id, [
            'id', 'fingerprint', 'type', 'severity', 'title', 'message', 'context',
        ]);
        $merakiInbox = $this->tableBytes('meraki_webhook_batches', $organization->id, [
            'id', 'connector_id', 'request_hash', 'payload', 'processing_status', 'processing_error',
        ]);

        return [
            'telemetry' => $telemetry,
            'positions' => $positions,
            'operational' => $operational,
            'meraki_inbox' => $merakiInbox,
            'total' => $telemetry + $positions + $operational + $merakiInbox,
        ];
    }

    /** @param list<string> $columns */
    private function tableBytes(string $table, string $organizationId, array $columns): int
    {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $castType = $connection->getDriverName() === 'sqlite' ? 'TEXT' : 'CHAR';
        $lengths = collect($columns)
            ->map(fn (string $column): string => sprintf(
                'COALESCE(LENGTH(CAST(%s AS %s)), 0)',
                $grammar->wrap($column),
                $castType,
            ))
            ->implode(' + ');
        $row = DB::table($table)
            ->where('organization_id', $organizationId)
            ->selectRaw("COUNT(*) AS row_count, COALESCE(SUM({$lengths}), 0) AS content_bytes")
            ->first();

        return (int) ($row->content_bytes ?? 0)
            + ((int) ($row->row_count ?? 0) * self::ESTIMATED_ROW_OVERHEAD_BYTES);
    }
}
