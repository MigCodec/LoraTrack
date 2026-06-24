<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiEventRetention;
use Illuminate\Console\Command;

class PruneMerakiHistory extends Command
{
    protected $signature = 'loratrack:prune-meraki-history';

    protected $description = 'Conserva los últimos diez eventos Meraki por tenant, conector y dispositivo.';

    public function handle(MerakiEventRetention $retention): int
    {
        $deleted = $retention->pruneAll();
        $this->info("Eventos Meraki antiguos eliminados: {$deleted}.");

        return self::SUCCESS;
    }
}
