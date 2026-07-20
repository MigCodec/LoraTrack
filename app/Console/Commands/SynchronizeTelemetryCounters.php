<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SynchronizeTelemetryCounters extends Command
{
    protected $signature = 'loratrack:sync-telemetry-counters {--connector= : Limitar la sincronizacion a un conector}';

    protected $description = 'Reconstruye los contadores de telemetria sin bloquear la fila del conector por cada evento.';

    public function handle(): int
    {
        $query = DB::table('connectors')->select('id')->orderBy('id');
        if (filled($this->option('connector'))) {
            $query->where('id', (string) $this->option('connector'));
        }

        $synchronized = 0;
        $query->chunkById(100, function ($connectors) use (&$synchronized): void {
            foreach ($connectors as $connector) {
                $counts = DB::table('telemetry_events')
                    ->where('connector_id', $connector->id)
                    ->selectRaw('COUNT(*) total')
                    ->selectRaw("SUM(CASE WHEN processing_status = 'pending' THEN 1 ELSE 0 END) pending")
                    ->selectRaw("SUM(CASE WHEN processing_status = 'processed' THEN 1 ELSE 0 END) processed")
                    ->selectRaw("SUM(CASE WHEN processing_status = 'failed' THEN 1 ELSE 0 END) failed")
                    ->first();

                DB::table('connectors')->where('id', $connector->id)->update([
                    'telemetry_events_count' => (int) ($counts->total ?? 0),
                    'pending_events_count' => (int) ($counts->pending ?? 0),
                    'processed_events_count' => (int) ($counts->processed ?? 0),
                    'failed_events_count' => (int) ($counts->failed ?? 0),
                    'updated_at' => now(),
                ]);
                $synchronized++;
            }
        });

        $this->info("Contadores de telemetria sincronizados: {$synchronized}.");

        return self::SUCCESS;
    }
}
