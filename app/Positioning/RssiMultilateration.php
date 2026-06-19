<?php

declare(strict_types=1);

namespace App\Positioning;

use InvalidArgumentException;

class RssiMultilateration
{
    /** @param list<AnchorMeasurement> $measurements */
    public function calculate(array $measurements): PositionResult
    {
        if (count($measurements) < 3) {
            throw new InvalidArgumentException('Se requieren al menos tres anclas con posición conocida.');
        }

        $origin = $measurements[0];
        $originDistance = $origin->estimatedDistance();
        $ata00 = $ata01 = $ata11 = $atb0 = $atb1 = 0.0;

        foreach (array_slice($measurements, 1) as $measurement) {
            $a = 2 * ($measurement->x - $origin->x);
            $b = 2 * ($measurement->y - $origin->y);
            $distance = $measurement->estimatedDistance();
            $c = ($originDistance ** 2 - $distance ** 2)
                + ($measurement->x ** 2 - $origin->x ** 2)
                + ($measurement->y ** 2 - $origin->y ** 2);

            $ata00 += $a * $a;
            $ata01 += $a * $b;
            $ata11 += $b * $b;
            $atb0 += $a * $c;
            $atb1 += $b * $c;
        }

        $determinant = ($ata00 * $ata11) - ($ata01 * $ata01);
        if (abs($determinant) < 0.000001) {
            throw new InvalidArgumentException('La geometría de las anclas es degenerada o colineal.');
        }

        $x = (($atb0 * $ata11) - ($atb1 * $ata01)) / $determinant;
        $y = (($ata00 * $atb1) - ($ata01 * $atb0)) / $determinant;

        $errors = [];
        $evidence = [];
        foreach ($measurements as $measurement) {
            $estimated = $measurement->estimatedDistance();
            $geometric = hypot($x - $measurement->x, $y - $measurement->y);
            $errors[] = ($geometric - $estimated) ** 2;
            $evidence[] = [
                'anchor' => $measurement->identifier,
                'rssi' => $measurement->rssi,
                'estimated_distance' => round($estimated, 3),
                'x' => $measurement->x,
                'y' => $measurement->y,
            ];
        }

        $rmse = sqrt(array_sum($errors) / count($errors));
        $confidence = max(0.0, min(1.0, 1 / (1 + $rmse)));

        return new PositionResult($x, $y, $confidence, $rmse, $evidence);
    }
}
