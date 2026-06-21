<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use App\Models\Asset;
use App\Models\AssetDeviceAssignment;
use App\Models\Connector;
use App\Models\Device;
use App\Models\TelemetryEvent;
use App\Telemetry\AssetLastSeenUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetLastSeenUpdaterTest extends TestCase
{
    use RefreshDatabase;

    public function test_beacon_asset_last_seen_advances_from_uplinks_and_never_moves_backwards(): void
    {
        $asset = Asset::query()->create(['asset_tag' => 'STATIC-1', 'name' => 'Activo estático', 'mobility' => 'static']);
        $beacon = Device::query()->create(['identifier' => 'AA:BB:CC:DD:EE:01', 'name' => 'Beacon móvil', 'type' => 'beacon']);
        AssetDeviceAssignment::query()->create([
            'asset_id' => $asset->id,
            'device_id' => $beacon->id,
            'tracking_strategy' => 'mobile_beacon_fixed_scanners',
            'started_at' => now()->subDay(),
        ]);
        $connector = Connector::query()->create([
            'name' => 'TTI',
            'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::TtiWebhook,
        ]);
        $newer = $this->event($connector, 'newer', now()->subMinute());
        $newer->signalObservations()->create([
            'transmitter_mac' => 'AABBCCDDEE01',
            'receiver_identifier' => 'SCANNER01',
            'rssi' => -70,
            'observed_at' => $newer->observed_at,
        ]);

        app(AssetLastSeenUpdater::class)->updateFromUplink($newer->load('signalObservations'));
        $this->assertTrue($asset->fresh()->last_seen_at->equalTo($newer->observed_at));

        $older = $this->event($connector, 'older', now()->subHours(2));
        $older->signalObservations()->create([
            'transmitter_mac' => 'AABBCCDDEE01',
            'receiver_identifier' => 'SCANNER01',
            'rssi' => -75,
            'observed_at' => $older->observed_at,
        ]);
        app(AssetLastSeenUpdater::class)->updateFromUplink($older->load('signalObservations'));

        $this->assertTrue($asset->fresh()->last_seen_at->equalTo($newer->observed_at));
    }

    private function event(Connector $connector, string $externalId, mixed $observedAt): TelemetryEvent
    {
        return TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', $externalId),
            'observed_at' => $observedAt,
            'received_at' => $observedAt,
            'raw_payload' => [],
        ]);
    }
}
