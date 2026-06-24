<?php

declare(strict_types=1);

namespace App\Telemetry;

final readonly class DatabaseStorageUsage
{
    public function __construct(
        public int $databaseBytes,
        public int $freeBytes,
        public float $utilizationPercent,
        public string $source,
    ) {}
}
