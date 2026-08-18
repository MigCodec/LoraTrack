<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

class WatchMerakiWebhooks extends Command
{
    protected $signature = 'loratrack:watch-meraki-webhooks
        {--interval=5 : Segundos de espera entre ciclos}
        {--limit=3 : Cantidad maxima de lotes procesados por ciclo}
        {--once : Ejecuta un solo ciclo y termina}';

    protected $description = 'Procesa continuamente los webhooks Meraki y muestra cada resultado en la consola.';

    public function handle(): int
    {
        $interval = $this->integerOption('interval', 1, 3600);
        $limit = $this->integerOption('limit', 1, 100000);
        if ($interval === null || $limit === null) {
            return self::INVALID;
        }

        $this->components->info('Monitor de webhooks Meraki iniciado. Presiona Ctrl+C para detenerlo.');
        $lastExitCode = self::SUCCESS;

        do {
            $this->newLine();
            $this->line(sprintf(
                '<fg=gray>[%s]</> Ejecutando <info>loratrack:process-meraki-observations --limit=%d</info>',
                now()->format('Y-m-d H:i:s'),
                $limit,
            ));

            try {
                $exitCode = $this->call('loratrack:process-meraki-observations', ['--limit' => $limit]);
                $lastExitCode = $exitCode;
                if ($exitCode === self::SUCCESS) {
                    $this->line('<fg=green>Ciclo finalizado correctamente.</>');
                } else {
                    $this->error("El procesador termino con codigo {$exitCode}; se reintentara en el siguiente ciclo.");
                }
            } catch (Throwable $exception) {
                $lastExitCode = self::FAILURE;
                report($exception);
                $this->error('El ciclo fallo: '.mb_substr($exception->getMessage(), 0, 500));
            }

            if (! $this->option('once')) {
                $this->line("Esperando {$interval} segundos...");
                sleep($interval);
            }
        } while (! $this->option('once'));

        return $lastExitCode;
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
