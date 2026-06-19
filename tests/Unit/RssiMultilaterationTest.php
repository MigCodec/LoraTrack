<?php

namespace Tests\Unit;

use App\Positioning\AnchorMeasurement;
use App\Positioning\BleObservationExtractor;
use App\Positioning\RssiMultilateration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RssiMultilaterationTest extends TestCase
{
    public function test_it_estimates_position_from_three_non_collinear_anchors(): void
    {
        $result = (new RssiMultilateration)->calculate([
            new AnchorMeasurement('A', 0, 0, -76),
            new AnchorMeasurement('B', 10, 0, -76),
            new AnchorMeasurement('C', 0, 10, -76),
        ]);

        $this->assertEqualsWithDelta(5.0, $result->x, 0.01);
        $this->assertEqualsWithDelta(5.0, $result->y, 0.01);
        $this->assertGreaterThan(0.9, $result->confidence);
    }

    public function test_it_rejects_insufficient_evidence(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RssiMultilateration)->calculate([
            new AnchorMeasurement('A', 0, 0, -60),
            new AnchorMeasurement('B', 10, 0, -70),
        ]);
    }

    public function test_it_extracts_and_normalizes_mac_and_rssi(): void
    {
        $observations = (new BleObservationExtractor)->extract([
            'beacons' => [
                ['mac' => 'AA:BB:CC:DD:EE:FF', 'rssi' => -71],
                ['mac_address' => '11-22-33-44-55-66', 'signal_strength' => -82],
            ],
        ]);

        $this->assertSame('AABBCCDDEEFF', $observations[0]['mac']);
        $this->assertSame(-82, $observations[1]['rssi']);
    }
}
