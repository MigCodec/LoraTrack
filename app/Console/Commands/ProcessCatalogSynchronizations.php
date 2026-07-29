<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncCatalogConnector;
use App\Models\Connector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessCatalogSynchronizations extends Command
{
    protected $signature = 'loratrack:process-catalog-syncs {--limit=1 : Cantidad maxima de sincronizaciones, entre 1 y 3}';

    protected $description = 'Procesa sincronizaciones de catalogo solicitadas desde el scheduler.';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 3],
        ]);
        if ($limit === false) {
            $this->error('--limit debe ser un entero entre 1 y 3.');

            return self::FAILURE;
        }

        $processed = 0;
        $failed = 0;
        for ($index = 0; $index < $limit; $index++) {
            $connector = DB::transaction(function (): ?Connector {
                $candidate = Connector::query()
                    ->withoutGlobalScope('organization')
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

        $this->info("Sincronizaciones de catalogo procesadas: {$processed}; fallidas: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
