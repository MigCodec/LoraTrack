<?php

declare(strict_types=1);

namespace App\Positioning;

final readonly class KalmanPositionResult
{
    /** @param array<string, mixed> $state */
    public function __construct(
        public float $x,
        public float $y,
        public float $accuracyMeters,
        public array $state,
    ) {}
}
