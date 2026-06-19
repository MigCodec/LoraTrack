<?php

declare(strict_types=1);

namespace App\Positioning;

final readonly class PositionResult
{
    /** @param array<int, array<string, int|float|string>> $evidence */
    public function __construct(
        public float $x,
        public float $y,
        public float $confidence,
        public float $accuracyMeters,
        public array $evidence,
    ) {}
}
