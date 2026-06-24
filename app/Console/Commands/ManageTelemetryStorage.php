<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Telemetry\DatabaseStorageInspector;
use App\Telemetry\DatabaseStorageUsage;
use App\Telemetry\TelemetryStorageCleaner;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManageTelemetryStorage extends Command
{
    private const THRESHOLD_PERCENT = 50.0;

    private const MAX_BATCHES_PER_ORGANIZATION = 10;

    protected $signature = 'loratrack:manage-telemetry-storage {--dry-run : Medir sin eliminar telemetría}';

    protected $description = 'Mide presión de almacenamiento y elimina telemetría antigua de tenants que lo autorizaron.';

    public function handle(
        DatabaseStorageInspector $inspector,
        TelemetryStorageCleaner $cleaner,
        OrganizationContext $context,
    ): int {
        $organizations = Organization::query()
            ->where('active', true)
            ->where('storage_cleanup_enabled', true)
            ->orderBy('id')
            ->get();

        if ($organizations->isEmpty()) {
            $this->info('La limpieza automática de telemetría no está habilitada en ninguna organización.');

            return self::SUCCESS;
        }

        try {
            $usage = $inspector->inspect();
        } catch (Throwable $exception) {
            Log::warning('No se pudo medir el almacenamiento de la base de datos; no se eliminó telemetría.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($this->usageMessage($usage));
        foreach ($organizations as $organization) {
            $organization->forceFill([
                'last_storage_utilization_percent' => $usage->utilizationPercent,
                'storage_checked_at' => now(),
            ])->save();
        }

        if ($usage->utilizationPercent <= self::THRESHOLD_PERCENT || $this->option('dry-run')) {
            $this->info($usage->utilizationPercent <= self::THRESHOLD_PERCENT
                ? 'La ocupación no supera el umbral del 50%; no se eliminó telemetría.'
                : 'Modo dry-run: no se eliminó telemetría.');

            return self::SUCCESS;
        }

        foreach ($organizations as $organization) {
            $context->set($organization);
            $deleted = 0;

            try {
                for ($batch = 0; $batch < self::MAX_BATCHES_PER_ORGANIZATION; $batch++) {
                    $batchDeleted = $cleaner->deleteOldestBatch($organization);
                    $deleted += $batchDeleted;
                    if ($batchDeleted < TelemetryStorageCleaner::BATCH_SIZE) {
                        break;
                    }
                }

                if ($deleted > 0) {
                    $organization->forceFill([
                        'storage_cleanup_at' => now(),
                        'storage_cleanup_deleted_events' => $organization->storage_cleanup_deleted_events + $deleted,
                    ])->save();
                    Log::warning('Telemetría antigua eliminada por presión de almacenamiento.', [
                        'organization_id' => $organization->id,
                        'deleted_events' => $deleted,
                        'retention_days' => $organization->telemetry_retention_days,
                        'utilization_percent' => $usage->utilizationPercent,
                    ]);
                }

                $this->line("{$organization->name}: {$deleted} eventos eliminados.");
            } finally {
                $context->set(null);
            }
        }

        return self::SUCCESS;
    }

    private function usageMessage(DatabaseStorageUsage $usage): string
    {
        return sprintf(
            'Base: %s · libre medible: %s · ocupación: %.2f%% · fuente: %s',
            $this->formatBytes($usage->databaseBytes),
            $this->formatBytes($usage->freeBytes),
            $usage->utilizationPercent,
            $usage->source,
        );
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, $bytes);
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 2).' '.$units[$unit];
    }
}
