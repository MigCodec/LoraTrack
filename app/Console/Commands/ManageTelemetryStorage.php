<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Telemetry\DatabaseStorageInspector;
use App\Telemetry\DatabaseStorageUsage;
use App\Telemetry\TenantRetentionPolicy;
use App\Telemetry\TelemetryStorageCleaner;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManageTelemetryStorage extends Command
{
    protected $signature = 'loratrack:manage-telemetry-storage
        {--dry-run : Medir sin eliminar datos vencidos}
        {--max-delete=0 : Máximo por categoría y tenant; 0 procesa todo lo vencido}
        {--profile : Mostrar política efectiva, fechas de corte y registros vencidos por tenant}';

    protected $description = 'Aplica la retención configurada por tenant y mide el almacenamiento cuando está disponible.';

    public function handle(
        DatabaseStorageInspector $inspector,
        TelemetryStorageCleaner $cleaner,
        OrganizationContext $context,
    ): int {
        $allOrganizations = Organization::query()
            ->orderBy('id')
            ->get();
        if ($this->option('profile')) {
            $this->renderPolicyOverview($allOrganizations);
        }
        $organizations = $allOrganizations->where('storage_cleanup_enabled', true);

        if ($organizations->isEmpty()) {
            $this->info('La retención automática no está habilitada en ninguna organización.');

            return self::SUCCESS;
        }

        $maxDeletes = filter_var($this->option('max-delete'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);
        if ($maxDeletes === false) {
            $this->error('--max-delete debe ser cero o un entero positivo.');

            return self::INVALID;
        }

        $usage = null;
        try {
            $usage = $inspector->inspect();
            $this->line($this->usageMessage($usage));
        } catch (Throwable $exception) {
            Log::warning('No se pudo medir el almacenamiento; la retención continuará por antigüedad.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->warn('No fue posible medir el volumen: '.$exception->getMessage());
        }

        $retentionViolations = 0;
        foreach ($organizations as $organization) {
            if ($this->option('profile')) {
                $this->renderRetentionDetails(
                    $organization,
                    $cleaner->expiredCounts($organization),
                    'Antes de ejecutar',
                );
            }
            if ($usage) {
                $organization->forceFill([
                    'last_storage_utilization_percent' => $usage->utilizationPercent,
                    'storage_checked_at' => now(),
                ])->save();
            }
            if ($this->option('dry-run')) {
                $this->line("{$organization->name}: modo diagnóstico; no se eliminó información.");
                continue;
            }

            $context->set($organization);
            try {
                $result = $cleaner->clean($organization, $maxDeletes);
                $deleted = $result['telemetry_events'] + $result['position_estimates']
                    + $result['operational_logs'] + $result['resolved_alerts'] + $result['terminal_inbox'];
                if ($deleted > 0) {
                    $organization->forceFill([
                        'storage_cleanup_at' => now(),
                        'storage_cleanup_deleted_events' => $organization->storage_cleanup_deleted_events + $deleted,
                    ])->save();
                    Log::warning('La política de retención eliminó datos vencidos.', [
                        'organization_id' => $organization->id,
                        'deleted_by_category' => $result,
                        'utilization_percent' => $usage?->utilizationPercent,
                    ]);
                }

                $this->line(sprintf(
                    '%s: %d telemetrías, %d posiciones, %d logs, %d alertas resueltas y %d lotes vencidos eliminados; %d lotes interrumpidos detectados.',
                    $organization->name,
                    $result['telemetry_events'],
                    $result['position_estimates'],
                    $result['operational_logs'],
                    $result['resolved_alerts'],
                    $result['terminal_inbox'],
                    $result['recovered_inbox'],
                ));
                $remaining = $cleaner->expiredCounts($organization);
                if ($this->option('profile')) {
                    $this->renderExpiredCounts($remaining, 'Después de ejecutar');
                }
                $remainingCount = array_sum($remaining);
                if ($remainingCount > 0) {
                    $retentionViolations += $remainingCount;
                    $this->error("{$organization->name}: quedan {$remainingCount} registros fuera de retención.");
                } else {
                    $this->info("{$organization->name}: retención verificada, sin registros vencidos.");
                }
            } finally {
                $context->set(null);
            }
        }

        return $retentionViolations === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param Collection<int, Organization> $organizations */
    private function renderPolicyOverview(Collection $organizations): void
    {
        $this->components->info('Políticas de retención efectivas');
        $this->table(
            ['Organización', 'Limpieza', 'Modo', 'Telemetría', 'Posiciones', 'Operacional', 'Inbox Meraki'],
            $organizations->map(function (Organization $organization): array {
                $policy = TenantRetentionPolicy::for($organization);

                return [
                    $organization->name,
                    $organization->storage_cleanup_enabled ? 'Activa' : 'Inactiva',
                    $organization->use_system_recommended_retention ? 'Recomendado' : 'Manual',
                    $policy->telemetryDays.' días',
                    $policy->positionHistoryDays.' días',
                    $policy->operationalLogDays.' días',
                    $policy->terminalInboxDays.' días',
                ];
            })->all(),
        );
    }

    /** @param array<string, int> $expired */
    private function renderRetentionDetails(Organization $organization, array $expired, string $moment): void
    {
        $policy = TenantRetentionPolicy::for($organization);
        $now = now()->utc();
        $this->newLine();
        $this->components->info("{$organization->name} · {$moment}");
        $this->table(['Categoría', 'Retención', 'Fecha de corte UTC', 'Vencidos'], [
            ['Telemetría y señales', $policy->telemetryDays.' días', $now->copy()->subDays($policy->telemetryDays)->format('Y-m-d H:i:s'), number_format($expired['telemetry_events'] ?? 0)],
            ['Historial de posiciones', $policy->positionHistoryDays.' días', $now->copy()->subDays($policy->positionHistoryDays)->format('Y-m-d H:i:s'), number_format($expired['position_estimates'] ?? 0)],
            ['Logs operacionales y auditoría', $policy->operationalLogDays.' días', $now->copy()->subDays($policy->operationalLogDays)->format('Y-m-d H:i:s'), number_format($expired['operational_logs'] ?? 0)],
            ['Alertas resueltas', $policy->operationalLogDays.' días', $now->copy()->subDays($policy->operationalLogDays)->format('Y-m-d H:i:s'), number_format($expired['resolved_alerts'] ?? 0)],
            ['Bandeja de entrada Meraki', $policy->terminalInboxDays.' días', $now->copy()->subDays($policy->terminalInboxDays)->format('Y-m-d H:i:s'), number_format($expired['meraki_inbox'] ?? 0)],
        ]);
    }

    /** @param array<string, int> $expired */
    private function renderExpiredCounts(array $expired, string $moment): void
    {
        $this->line($moment.': '.collect($expired)
            ->map(fn (int $count, string $category): string => "{$category}=".number_format($count))
            ->implode(' · '));
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
