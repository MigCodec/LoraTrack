<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiEventRetention;
use App\Models\Organization;
use App\Telemetry\TenantRetentionPolicy;
use Illuminate\Console\Command;

class PruneMerakiHistory extends Command
{
    protected $signature = 'loratrack:prune-meraki-history
        {--dry-run : Contar eventos vencidos sin eliminarlos}
        {--max-delete=0 : Máximo por tenant; 0 procesa todo lo vencido}';

    protected $description = 'Aplica a Meraki la ventana de retención configurada para cada tenant.';

    public function handle(MerakiEventRetention $retention): int
    {
        $maxDeletes = filter_var($this->option('max-delete'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);
        if ($maxDeletes === false) {
            $this->error('--max-delete debe ser cero o un entero positivo.');

            return self::INVALID;
        }

        Organization::query()->where('storage_cleanup_enabled', true)
            ->orderBy('id')->each(function (Organization $organization) use ($retention, $maxDeletes): void {
                $days = TenantRetentionPolicy::for($organization)->telemetryDays;
                $stale = $retention->staleCount($organization);
                $this->line("{$organization->name}: {$stale} eventos Meraki vencidos ({$days} días).");
                if (! $this->option('dry-run') && $stale > 0) {
                    $deleted = $retention->pruneAll($organization, $maxDeletes);
                    $this->line("{$organization->name}: {$deleted} eventos eliminados.");
                }
            });

        return self::SUCCESS;
    }
}
