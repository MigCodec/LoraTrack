<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Process\Process;

class IsolatedArtisanCommandRunner
{
    /** @param array<string, int|string|bool> $arguments */
    public function run(
        string $command,
        array $arguments,
        string $memoryLimit,
        int $timeoutSeconds,
    ): ArtisanProcessResult {
        $processArguments = [PHP_BINARY, '-d', "memory_limit={$memoryLimit}", base_path('artisan'), $command];
        foreach ($arguments as $name => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $processArguments[] = $name;
                }

                continue;
            }
            $processArguments[] = "{$name}={$value}";
        }

        $process = new Process($processArguments, base_path(), null, null, $timeoutSeconds);
        $process->run();

        return new ArtisanProcessResult(
            $process->getExitCode() ?? 1,
            trim($process->getOutput().PHP_EOL.$process->getErrorOutput()),
        );
    }
}
