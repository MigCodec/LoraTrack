<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Positioning\KalmanPositionFilter;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class KalmanPositionFilterTest extends TestCase
{
    public function test_it_reduces_jitter_across_multiple_position_measurements(): void
    {
        $filter = new KalmanPositionFilter;
        $at = Carbon::parse('2026-06-21T12:00:00Z');
        $measurements = [10.0, 12.0, 8.5, 11.5, 9.0, 10.5];
        $filtered = [];
        $state = null;

        foreach ($measurements as $index => $measurement) {
            $result = $filter->filter($measurement, $measurement, 2.0, $at->copy()->addSeconds($index), $state);
            $filtered[] = $result->x;
            $state = $result->state;
        }

        $this->assertLessThan($this->variance($measurements), $this->variance($filtered));
        $this->assertGreaterThanOrEqual(1.0, $result->accuracyMeters);
        $this->assertLessThanOrEqual(2.0, $result->accuracyMeters);
    }

    public function test_it_resets_for_an_out_of_order_measurement(): void
    {
        $filter = new KalmanPositionFilter;
        $newer = $filter->filter(5, 6, 3, Carbon::parse('2026-06-21T12:01:00Z'));
        $older = $filter->filter(20, 30, 4, Carbon::parse('2026-06-21T12:00:00Z'), $newer->state);

        $this->assertSame(20.0, $older->x);
        $this->assertSame(30.0, $older->y);
        $this->assertSame(4.0, $older->accuracyMeters);
    }

    /** @param list<float> $values */
    private function variance(array $values): float
    {
        $mean = array_sum($values) / count($values);

        return array_sum(array_map(fn (float $value): float => ($value - $mean) ** 2, $values)) / count($values);
    }
}
