<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesTenantProcessingLimits;
use App\Jobs\SyncCatalogConnector;
use App\Models\Connector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessCatalogSynchronizations extends Command
{
    use ResolvesTenantProcessingLimits;

    protected $signature = 'loratrack:process-catalog-syncs {--limit= : Sobrescribe el limite por organizacion (1 a 10)}';

    protected $description = 'Procesa sincronizaciones de catalogo solicitadas desde el scheduler.';

    public function handle(): int
    {
        $override = $this->optionalIntegerOption('limit', 1, 10);
        if ($override === -1) {
            return self::INVALID;
        }

        $processed = 0;
        $failed = 0;
        foreach ($this->tenantLimits('catalog_sync_limit', 1, $override) as $organizationId => $limit) {
            for ($index = 0; $index < $limit; $index++) {
                $connector = DB::transaction(function () use ($organizationId): ?Connector {
                    $candidate = Connector::query()
                        ->withoutGlobalScope('organization')
                        ->where('organization_id', $organizationId)
                        ->whereNotNull('sync_requested_at')
                        ->orderBy('sync_requested_at')
                        ->lockForUpdate()
                        ->first();
                    if (! $candidate) {
                        return null;
                    }
                    $candidate->forceFill(['sync_requested_at' => null, 'sync_started_at' => now()])->save();

                    return $candidate;
                });
                if (! $connector) {
                    break;
                }

                try {
                    app()->call([new SyncCatalogConnector($connector->id), 'handle']);
                    $connector->forceFill(['sync_started_at' => null])->save();
                    $processed++;
                } catch (Throwable $exception) {
                    $connector->forceFill(['sync_started_at' => null])->save();
                    $failed++;
                    Log::warning('El scheduler no pudo sincronizar un conector de catalogo.', [
                        'connector_id' => $connector->id,
                        'exception' => $exception::class,
                    ]);
                }
            }
        }

        $this->info("Sincronizaciones de catalogo procesadas: {$processed}; fallidas: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
