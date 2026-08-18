<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesTenantProcessingLimits;
use App\Connectors\Meraki\MerakiEventRetention;
use App\Models\Organization;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class PruneMerakiHistoryIncrementally extends Command
{
    use ResolvesTenantProcessingLimits;

    protected $signature = 'loratrack:prune-meraki-history-incremental
        {--limit= : Sobrescribe el maximo por organizacion}
        {--batch-size=100 : Eventos eliminados por lote}
        {--dry-run : Selecciona los registros sin eliminarlos}
        {--profile : Muestra metricas de eliminacion y rendimiento SQL}';

    protected $description = 'Elimina incrementalmente eventos Meraki vencidos sin contar todo el historial.';

    public function handle(MerakiEventRetention $retention): int
    {
        $override = $this->optionalIntegerOption('limit', 1, 100000);
        $batchSize = $this->integerOption('batch-size', 1, 1000);
        if ($override === -1 || $batchSize === null) {
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
        $reachedLimit = false;
        $selected = ['events' => 0, 'observations' => 0];

        $organizationsQuery = Organization::query()->where('active', true)->orderBy('id');
        if ($organizationId = app(OrganizationContext::class)->id()) {
            $organizationsQuery->whereKey($organizationId);
        }
        $organizations = $organizationsQuery->get();
        foreach ($organizations as $organization) {
            $limit = $override ?? max(1, (int) ($organization->storage_cleanup_max_events ?? 10000));
            if ($this->option('dry-run')) {
                $preview = $retention->previewBatchForOrganization($organization, min($batchSize, $limit), $profile);
                $selected['events'] += $preview['events'];
                $selected['observations'] += $preview['observations'];
                continue;
            }

            $tenantDeleted = 0;
            while ($tenantDeleted < $limit) {
                $currentLimit = min($batchSize, $limit - $tenantDeleted);
                $result = $retention->pruneBatchForOrganization($organization, $currentLimit, $profile);
                if ($result['events'] === 0) {
                    break;
                }
                $tenantDeleted += $result['events'];
                $deletedEvents += $result['events'];
                $deletedObservations += $result['observations'];
                $batches++;
            }
            $reachedLimit = $reachedLimit || $tenantDeleted >= $limit;
        }

        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

        if ($this->option('dry-run')) {
            $this->warn('El modo --dry-run del comando incremental no elimina registros.');
            $this->info("Eventos que seleccionaria este ciclo: {$selected['events']}.");

            if ($profile) {
                $this->table(['Metrica', 'Valor'], [
                    ['Eventos seleccionados', number_format($selected['events'])],
                    ['Observaciones que se eliminarian por cascada', number_format($selected['observations'])],
                    ['Consultas SQL', number_format($queryCount)],
                    ['Tiempo SQL', number_format($queryTimeMs, 1).' ms'],
                    ['Tiempo total', number_format($durationMs, 1).' ms'],
                ]);
            }

            return self::SUCCESS;
        }

        $remainingLabel = $reachedLimit
            ? 'No verificado; se alcanzo el limite solicitado'
            : 'No se encontraron mas en este ciclo';
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
