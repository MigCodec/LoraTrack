<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TelemetryEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class CompactMerakiPayloads extends Command
{
    protected $signature = 'loratrack:compact-meraki-payloads
        {--limit=1000 : Cantidad máxima de eventos históricos a compactar}
        {--batch-size=100 : Eventos procesados por lote}
        {--dry-run : Calcula el ahorro sin modificar registros}
        {--profile : Muestra volumen y rendimiento SQL}';

    protected $description = 'Elimina incrementalmente los JSON de eventos Meraki ya procesados.';

    public function handle(): int
    {
        $limit = $this->integerOption('limit', 1, 100000);
        $batchSize = $this->integerOption('batch-size', 1, 1000);
        if ($limit === null || $batchSize === null) {
            return self::INVALID;
        }

        $profile = (bool) $this->option('profile');
        $queryCount = 0;
        $queryTimeMs = 0.0;
        if ($profile) {
            DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs): void {
                $queryCount++;
                $queryTimeMs += $query->time;
            });
        }

        $startedAt = hrtime(true);
        $processed = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;
        $lastReceivedAt = null;
        $lastId = null;

        while ($processed < $limit) {
            $query = TelemetryEvent::query()
                ->withoutGlobalScopes()
                ->select(['id', 'received_at', 'raw_payload', 'normalized_payload'])
                ->where('event_type', 'meraki_location')
                ->where('processing_status', 'processed')
                ->where('payload_storage_version', '<', 2);
            if ($this->option('dry-run') && $lastReceivedAt !== null && $lastId !== null) {
                $query->where(function ($cursor) use ($lastReceivedAt, $lastId): void {
                    $cursor->where('received_at', '>', $lastReceivedAt)
                        ->orWhere(function ($sameTimestamp) use ($lastReceivedAt, $lastId): void {
                            $sameTimestamp->where('received_at', $lastReceivedAt)->where('id', '>', $lastId);
                        });
                });
            }
            $events = $query
                ->orderBy('received_at')
                ->orderBy('id')
                ->limit(min($batchSize, $limit - $processed))
                ->get();

            if ($events->isEmpty()) {
                break;
            }

            foreach ($events as $event) {
                $bytesBefore += $this->jsonBytes($event->raw_payload) + $this->jsonBytes($event->normalized_payload);

                if (! $this->option('dry-run')) {
                    TelemetryEvent::query()
                        ->withoutGlobalScopes()
                        ->whereKey($event->id)
                        ->where('payload_storage_version', '<', 2)
                        ->update([
                            'raw_payload' => null,
                            'normalized_payload' => null,
                            'payload_storage_version' => 2,
                            'updated_at' => now(),
                        ]);
                }
                $processed++;
                $lastReceivedAt = $event->received_at;
                $lastId = (string) $event->id;
            }
        }

        $savedBytes = max(0, $bytesBefore - $bytesAfter);
        $action = $this->option('dry-run') ? 'analizados' : 'liberados de JSON';
        $this->info("Eventos Meraki {$action}: {$processed}.");
        if ($this->option('dry-run')) {
            $this->warn('El modo --dry-run no modifica registros.');
        }

        if ($profile) {
            $this->table(['Métrica', 'Valor'], [
                ['Eventos '.$action, number_format($processed)],
                ['Volumen anterior', $this->formatBytes($bytesBefore)],
                ['JSON conservado', $this->formatBytes($bytesAfter)],
                ['Ahorro estimado', $this->formatBytes($savedBytes)],
                ['Reducción', $bytesBefore > 0 ? number_format(($savedBytes / $bytesBefore) * 100, 1).'%' : '0.0%'],
                ['Consultas SQL', number_format($queryCount)],
                ['Tiempo SQL', number_format($queryTimeMs, 1).' ms'],
                ['Tiempo total', number_format((hrtime(true) - $startedAt) / 1_000_000, 1).' ms'],
            ]);
        }

        return self::SUCCESS;
    }

    private function jsonBytes(mixed $payload): int
    {
        return strlen(json_encode($payload ?? [], JSON_THROW_ON_ERROR));
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1_073_741_824
            ? number_format($bytes / 1_073_741_824, 2).' GB'
            : ($bytes >= 1_048_576
                ? number_format($bytes / 1_048_576, 2).' MB'
                : number_format($bytes / 1024, 2).' KB');
    }

    private function integerOption(string $name, int $minimum, int $maximum): ?int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $minimum, 'max_range' => $maximum],
        ]);
        if ($value === false) {
            $this->error("--{$name} debe ser un entero entre {$minimum} y {$maximum}.");

            return null;
        }

        return $value;
    }
}
