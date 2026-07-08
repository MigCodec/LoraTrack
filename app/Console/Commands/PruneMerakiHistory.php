<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiEventRetention;
use Illuminate\Console\Command;

class PruneMerakiHistory extends Command
{
    protected $signature = 'loratrack:prune-meraki-history
        {--dry-run : Contar eventos vencidos sin eliminarlos}
        {--max-delete=10000 : Maximo de eventos a eliminar en esta ejecucion}';

    protected $description = 'Elimina eventos Meraki y observaciones asociadas anteriores a la retencion de seis dias.';

    public function handle(MerakiEventRetention $retention): int
    {
        $stale = $retention->staleCount();
        $this->info('Eventos Meraki vencidos por retencion de '.MerakiEventRetention::RETENTION_DAYS." dias: {$stale}.");
        if ($this->option('dry-run') || $stale === 0) {
            return self::SUCCESS;
        }

        $maxDeletes = filter_var($this->option('max-delete'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100000],
        ]);
        if ($maxDeletes === false) {
            $this->error('--max-delete debe ser un entero entre 1 y 100000.');

            return self::FAILURE;
        }

        $deleted = $retention->pruneAll($maxDeletes);
        $this->info("Eventos Meraki antiguos eliminados: {$deleted}.");
        $this->info('Pendientes aproximados: '.max(0, $stale - $deleted).'.');

        return self::SUCCESS;
    }
}
