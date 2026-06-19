<?php

declare(strict_types=1);

namespace App\Positioning;

final readonly class AnchorMeasurement
{
    public function __construct(
        public string $identifier,
        public float $x,
        public float $y,
        public int $rssi,
        public int $referenceRssi = -59,
        public float $pathLossExponent = 2.0,
    ) {}

    public function estimatedDistance(): float
    {
        $exponent = max(0.5, $this->pathLossExponent);

        return 10 ** (($this->referenceRssi - $this->rssi) / (10 * $exponent));
    }
}
