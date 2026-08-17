<?php

declare(strict_types=1);

namespace App\Support;

final readonly class ArtisanProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {}
}
