<?php

declare(strict_types=1);

namespace App\Positioning;

use App\Models\FloorPlan;
use App\Models\Zone;

class ZoneClassifier
{
    public function classify(FloorPlan $floorPlan, float $xMeters, float $yMeters): ?Zone
    {
        $width = (float) $floorPlan->width_meters;
        $height = (float) $floorPlan->height_meters;
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $normalizedX = $xMeters / $width;
        $normalizedY = $yMeters / $height;

        return $floorPlan->zones->first(
            fn (Zone $zone): bool => $zone->contains($normalizedX, $normalizedY),
        );
    }
}
