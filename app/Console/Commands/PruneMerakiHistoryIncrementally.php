<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiEventRetention;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class PruneMerakiHistoryIncrementally extends Command
{
    protected $signature = 'loratrack:prune-meraki-history-incremental
        {--limit=1 : Cantidad maxima de eventos a eliminar}
        {--batch-size=100 : Eventos eliminados por lote}
        {--dry-run : Selecciona los registros sin eliminarlos}
        {--profile : Muestra metricas de eliminacion y rendimiento SQL}';

    protected $description = 'Elimina incrementalmente eventos Meraki vencidos sin contar todo el historial.';

    public function handle(MerakiEventRetention $retention): int
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
        $deletedEvents = 0;
        $deletedObservations = 0;
        $batches = 0;

        while ($deletedEvents < $limit) {
            $currentLimit = min($batchSize, $limit - $deletedEvents);
            if ($this->option('dry-run')) {
                $selected = $retention->previewBatch($currentLimit, $profile);
                break;
            }

            $result = $retention->pruneBatch($currentLimit, $profile);
            if ($result['events'] === 0) {
                break;
            }
            $deletedEvents += $result['events'];
            $deletedObservations += $result['observations'];
            $batches++;
        }

        if ($this->option('dry-run')) {
            $this->warn('El modo --dry-run del comando incremental no elimina registros.');
            $this->info("Eventos que seleccionaria este ciclo: {$selected['events']}.");

            return self::SUCCESS;
        }

        $reachedLimit = $deletedEvents >= $limit;
        $remainingLabel = $reachedLimit
            ? 'No verificado; se alcanzo el limite solicitado'
            : 'No se encontraron mas en este ciclo';
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $this->info("Eventos Meraki eliminados: {$deletedEvents}.");
        $this->line("Pendientes: {$remainingLabel}.");

        if ($profile) {
            $this->table(['Metrica', 'Valor'], [
                ['Eventos eliminados', number_format($deletedEvents)],
                ['Observaciones eliminadas por cascada', number_format($deletedObservations)],
                ['Lotes ejecutados', number_format($batches)],
                ['Consultas SQL', number_format($queryCount)],
                ['Tiempo SQL', number_format($queryTimeMs, 1).' ms'],
                ['Tiempo total', number_format($durationMs, 1).' ms'],
                ['Pendientes', $remainingLabel],
            ]);
        }

        return self::SUCCESS;
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
