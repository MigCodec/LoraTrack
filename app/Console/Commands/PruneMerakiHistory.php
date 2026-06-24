<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiEventRetention;
use Illuminate\Console\Command;

class PruneMerakiHistory extends Command
{
    protected $signature = 'loratrack:prune-meraki-history
        {--dry-run : Contar eventos excedentes sin eliminarlos}
        {--max-delete=10000 : Máximo de eventos a eliminar en esta ejecución}';

    protected $description = 'Conserva los últimos diez eventos Meraki por tenant, conector y dispositivo.';

    public function handle(MerakiEventRetention $retention): int
    {
        $excess = $retention->excessCount();
        $this->info("Eventos Meraki que exceden la retención: {$excess}.");
        if ($this->option('dry-run') || $excess === 0) {
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
        $this->info('Pendientes aproximados: '.max(0, $excess - $deleted).'.');

        return self::SUCCESS;
    }
}
