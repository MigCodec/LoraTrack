<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\IsolatedArtisanCommandRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DrainMerakiBacklog extends Command
{
    protected $signature = 'loratrack:drain-meraki-backlog
        {--observation-batch=1000 : Observaciones procesadas por cada proceso aislado}
        {--memory=512M : Límite de memoria de cada proceso hijo}
        {--child-timeout=900 : Timeout en segundos de cada proceso hijo}
        {--max-runtime=0 : Duración máxima total en segundos; 0 procesa hasta vaciar la cola}';

    protected $description = 'Vacía masivamente el backlog Meraki en una VM usando procesos aislados y memoria acotada.';

    public function handle(IsolatedArtisanCommandRunner $runner): int
    {
        $observationBatch = $this->integerOption('observation-batch', 1, 1000);
        $childTimeout = $this->integerOption('child-timeout', 30, 3600);
        $maxRuntime = $this->integerOption('max-runtime', 0, 86400);
        $memory = strtoupper(trim((string) $this->option('memory')));
        if ($observationBatch === null || $childTimeout === null || $maxRuntime === null) {
            return self::INVALID;
        }
        if (preg_match('/^(?:128|192|256|384|512|768|1024)M$/', $memory) !== 1) {
            $this->error('--memory debe usar un valor permitido entre 128M y 1024M.');

            return self::INVALID;
        }

        $lock = Cache::lock('loratrack:drain-meraki-backlog', $maxRuntime > 0 ? $maxRuntime + 300 : 86400);
        if (! $lock->get()) {
            $this->error('Ya existe otro drenaje masivo de Meraki en ejecución.');

            return self::FAILURE;
        }

        $startedAt = hrtime(true);
        $initialBatches = $this->eligibleBatchCount();
        $initialObservations = $this->pendingObservationCount();
        $processedBatches = 0;
        $processedObservations = 0;
        $failures = 0;

        $this->components->info("Backlog inicial: {$initialBatches} lotes y {$initialObservations} observaciones.");

        try {
            while (true) {
                if ($this->runtimeExceeded($startedAt, $maxRuntime)) {
                    $this->warn('Se alcanzó --max-runtime; el backlog restante se conservará para la próxima ejecución.');
                    break;
                }

                [$beforeBatches, $beforeAttempts] = $this->eligibleBatchState();
                if ($beforeBatches > 0) {
                    $result = $runner->run(
                        'loratrack:process-meraki-webhooks',
                        ['--limit' => 1],
                        $memory,
                        $childTimeout,
                    );
                    [$afterBatches, $afterAttempts] = $this->eligibleBatchState();
                    $processedBatches += max(0, $beforeBatches - $afterBatches);
                    $this->renderChildOutput($result->output);
                    $advanced = $afterBatches < $beforeBatches || $afterAttempts > $beforeAttempts;
                    if ($result->exitCode !== self::SUCCESS || ! $advanced) {
                        $failures++;
                        $this->error('El proceso de lotes no avanzó; se detiene para evitar un ciclo infinito.');
                        break;
                    }

                    $this->line("Lotes restantes: {$afterBatches}");
                    continue;
                }

                $beforeObservations = $this->pendingObservationCount();
                if ($beforeObservations > 0) {
                    $result = $runner->run(
                        'loratrack:process-meraki-observations',
                        ['--limit' => $observationBatch],
                        $memory,
                        $childTimeout,
                    );
                    $afterObservations = $this->pendingObservationCount();
                    $advanced = max(0, $beforeObservations - $afterObservations);
                    $processedObservations += $advanced;
                    $this->renderChildOutput($result->output);
                    if ($advanced === 0) {
                        $failures++;
                        $this->error('El proceso de observaciones no avanzó; se detiene para evitar un ciclo infinito.');
                        break;
                    }
                    if ($result->exitCode !== self::SUCCESS) {
                        $failures++;
                        $this->warn('El bloque avanzó, pero contiene observaciones fallidas. Se continuará con las pendientes.');
                    }

                    $this->line("Observaciones pendientes: {$afterObservations}");
                    continue;
                }

                // A webhook may arrive while observations are being drained.
                if ($this->eligibleBatchCount() === 0) {
                    break;
                }
            }

            $counterResult = $runner->run('loratrack:sync-telemetry-counters', [], $memory, $childTimeout);
            if ($counterResult->exitCode !== self::SUCCESS) {
                $failures++;
                $this->warn('No fue posible sincronizar los contadores al finalizar.');
                $this->renderChildOutput($counterResult->output);
            }

            $remainingBatches = $this->eligibleBatchCount();
            $remainingObservations = $this->pendingObservationCount();
            $terminalBatches = $this->terminalBatchCount();
            $processingBatches = $this->processingBatchCount();
            $this->newLine();
            $this->table(
                ['Resultado', 'Cantidad'],
                [
                    ['Lotes drenados', number_format($processedBatches)],
                    ['Observaciones drenadas', number_format($processedObservations)],
                    ['Lotes restantes', number_format($remainingBatches)],
                    ['Observaciones pendientes', number_format($remainingObservations)],
                    ['Lotes fallidos sin reintentos', number_format($terminalBatches)],
                    ['Lotes bloqueados en processing', number_format($processingBatches)],
                    ['Bloques con incidencias', number_format($failures)],
                ],
            );

            return $remainingBatches === 0 && $remainingObservations === 0
                && $terminalBatches === 0 && $processingBatches === 0 && $failures === 0
                ? self::SUCCESS
                : self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function eligibleBatchCount(): int
    {
        return $this->eligibleBatchState()[0];
    }

    /** @return array{int, int} */
    private function eligibleBatchState(): array
    {
        $state = DB::table('meraki_webhook_batches')
            ->selectRaw('COUNT(*) as aggregate, COALESCE(SUM(attempts), 0) as attempts')
            ->where(fn ($query) => $query->where('processing_status', 'pending')
                ->orWhere(fn ($failed) => $failed->where('processing_status', 'failed')->where('attempts', '<', 3)))
            ->first();

        return [(int) ($state->aggregate ?? 0), (int) ($state->attempts ?? 0)];
    }

    private function terminalBatchCount(): int
    {
        return DB::table('meraki_webhook_batches')
            ->where('processing_status', 'failed')
            ->where('attempts', '>=', 3)
            ->count();
    }

    private function processingBatchCount(): int
    {
        return DB::table('meraki_webhook_batches')
            ->where('processing_status', 'processing')
            ->count();
    }

    private function pendingObservationCount(): int
    {
        return DB::table('telemetry_events')
            ->join('connectors', 'connectors.id', '=', 'telemetry_events.connector_id')
            ->where('telemetry_events.event_type', 'meraki_location')
            ->where('telemetry_events.processing_status', 'pending')
            ->where('connectors.provider', 'meraki_location')
            ->count();
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

    private function runtimeExceeded(int $startedAt, int $maxRuntime): bool
    {
        return $maxRuntime > 0 && (hrtime(true) - $startedAt) / 1_000_000_000 >= $maxRuntime;
    }

    private function renderChildOutput(string $output): void
    {
        if ($output !== '') {
            $this->line($output);
        }
    }
}
