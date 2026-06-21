<?php

declare(strict_types=1);

namespace App\Positioning;

use Illuminate\Support\Carbon;

class KalmanPositionFilter
{
    private const MIN_MEASUREMENT_VARIANCE = 0.25;

    private const PROCESS_ACCELERATION_VARIANCE = 0.01;

    private const MAX_INTERVAL_SECONDS = 5.0;

    /** @param array<string, mixed>|null $previousState */
    public function filter(float $x, float $y, float $accuracyMeters, Carbon $observedAt, ?array $previousState = null): KalmanPositionResult
    {
        $measurementVariance = max(self::MIN_MEASUREMENT_VARIANCE, $accuracyMeters ** 2);
        $previousAt = isset($previousState['observed_at']) ? Carbon::parse($previousState['observed_at']) : null;

        if (! $previousAt || $observedAt->lessThanOrEqualTo($previousAt)) {
            return $this->initialResult($x, $y, $accuracyMeters, $measurementVariance, $observedAt);
        }

        $interval = min(self::MAX_INTERVAL_SECONDS, max(0.1, $previousAt->diffInMilliseconds($observedAt) / 1000));
        $xAxis = $this->filterAxis(
            (float) ($previousState['x'] ?? $x),
            (float) ($previousState['vx'] ?? 0),
            $this->covariance($previousState['covariance_x'] ?? null, $measurementVariance),
            $x,
            $measurementVariance,
            $interval,
        );
        $yAxis = $this->filterAxis(
            (float) ($previousState['y'] ?? $y),
            (float) ($previousState['vy'] ?? 0),
            $this->covariance($previousState['covariance_y'] ?? null, $measurementVariance),
            $y,
            $measurementVariance,
            $interval,
        );
        $statisticalAccuracy = sqrt(max(0, ($xAxis['covariance'][0] + $yAxis['covariance'][0]) / 2));
        $reportedAccuracy = max($statisticalAccuracy, $accuracyMeters * 0.5);
        $state = [
            'observed_at' => $observedAt->toIso8601String(),
            'x' => $xAxis['position'],
            'y' => $yAxis['position'],
            'vx' => $xAxis['velocity'],
            'vy' => $yAxis['velocity'],
            'covariance_x' => $xAxis['covariance'],
            'covariance_y' => $yAxis['covariance'],
            'measurement_variance' => $measurementVariance,
        ];

        return new KalmanPositionResult($xAxis['position'], $yAxis['position'], $reportedAccuracy, $state);
    }

    private function initialResult(float $x, float $y, float $accuracyMeters, float $variance, Carbon $observedAt): KalmanPositionResult
    {
        $covariance = [$variance, 0.0, 0.0, 0.01];

        return new KalmanPositionResult($x, $y, $accuracyMeters, [
            'observed_at' => $observedAt->toIso8601String(),
            'x' => $x,
            'y' => $y,
            'vx' => 0.0,
            'vy' => 0.0,
            'covariance_x' => $covariance,
            'covariance_y' => $covariance,
            'measurement_variance' => $variance,
        ]);
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $covariance
     * @return array{position: float, velocity: float, covariance: array{0: float, 1: float, 2: float, 3: float}}
     */
    private function filterAxis(float $position, float $velocity, array $covariance, float $measurement, float $measurementVariance, float $interval): array
    {
        [$p00, $p01, $p10, $p11] = $covariance;
        $q = self::PROCESS_ACCELERATION_VARIANCE;
        $predictedPosition = $position + ($interval * $velocity);
        $predictedP00 = $p00 + ($interval * ($p01 + $p10)) + (($interval ** 2) * $p11) + ($q * ($interval ** 4) / 4);
        $predictedP01 = $p01 + ($interval * $p11) + ($q * ($interval ** 3) / 2);
        $predictedP10 = $p10 + ($interval * $p11) + ($q * ($interval ** 3) / 2);
        $predictedP11 = $p11 + ($q * ($interval ** 2));
        $innovationVariance = $predictedP00 + $measurementVariance;
        $positionGain = $predictedP00 / $innovationVariance;
        $velocityGain = $predictedP10 / $innovationVariance;
        $innovation = $measurement - $predictedPosition;
        $filteredP00 = (1 - $positionGain) * $predictedP00;
        $filteredP01 = (1 - $positionGain) * $predictedP01;
        $filteredP10 = $predictedP10 - ($velocityGain * $predictedP00);
        $filteredP11 = $predictedP11 - ($velocityGain * $predictedP01);
        $crossCovariance = ($filteredP01 + $filteredP10) / 2;

        return [
            'position' => $predictedPosition + ($positionGain * $innovation),
            'velocity' => $velocity + ($velocityGain * $innovation),
            'covariance' => [max(0, $filteredP00), $crossCovariance, $crossCovariance, max(0, $filteredP11)],
        ];
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} */
    private function covariance(mixed $value, float $fallbackVariance): array
    {
        if (! is_array($value) || count($value) !== 4 || collect($value)->contains(fn (mixed $item): bool => ! is_numeric($item))) {
            return [$fallbackVariance, 0.0, 0.0, 0.01];
        }

        return array_map('floatval', array_values($value));
    }
}
