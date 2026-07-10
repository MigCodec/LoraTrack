<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Models\TelemetryEvent;
use Illuminate\Support\Facades\DB;

class TelemetryCounterUpdater
{
    public function recordCreated(TelemetryEvent $event): void
    {
        $this->applyDelta($event->connector_id, [
            'telemetry_events_count' => 1,
            $this->columnForStatus((string) $event->processing_status) => 1,
        ]);
    }

    public function recordStatusChanged(TelemetryEvent $event): void
    {
        $oldStatus = (string) $event->getOriginal('processing_status');
        $newStatus = (string) $event->processing_status;

        if ($oldStatus === $newStatus) {
            return;
        }

        $this->applyDelta($event->connector_id, [
            $this->columnForStatus($oldStatus) => -1,
            $this->columnForStatus($newStatus) => 1,
        ]);
    }

    /** @param array<string|null, int> $deltas */
    private function applyDelta(?string $connectorId, array $deltas): void
    {
        if (! $connectorId) {
            return;
        }

        $updates = [];
        foreach ($deltas as $column => $delta) {
            if (! is_string($column) || $column === '' || $delta === 0) {
                continue;
            }

            $updates[$column] = DB::raw("CASE WHEN {$column} + ({$delta}) < 0 THEN 0 ELSE {$column} + ({$delta}) END");
        }

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = now();

        DB::table('connectors')
            ->where('id', $connectorId)
            ->update($updates);
    }

    private function columnForStatus(string $status): ?string
    {
        return match ($status) {
            'pending' => 'pending_events_count',
            'processed' => 'processed_events_count',
            'failed' => 'failed_events_count',
            default => null,
        };
    }
}
