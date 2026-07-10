<?php

namespace Tests\Feature;

use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use App\Enums\UserRole;
use App\Jobs\ProcessTtiUplink;
use App\Models\Asset;
use App\Models\AssetDeviceAssignment;
use App\Models\Connector;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\PositionEstimate;
use App\Models\TelemetryEvent;
use App\Models\User;
use App\Positioning\BleObservationExtractor;
use App\Positioning\PayloadProfileDecoder;
use App\Positioning\TelemetryPositioningService;
use App\Telemetry\AssetLastSeenUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TtiPositioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_mac_and_rssi_observations_position_asset_inside_zone(): void
    {
        $location = Location::query()->create(['name' => 'Piso 1', 'type' => 'floor']);
        $plan = FloorPlan::query()->create([
            'location_id' => $location->id,
            'name' => 'Piso 1',
            'file_path' => 'floor-plans/test.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'width_meters' => 10,
            'height_meters' => 10,
        ]);
        $zone = $plan->zones()->create([
            'name' => 'Zona central',
            'color' => '#78A22F',
            'x_min' => 0.4,
            'y_min' => 0.4,
            'x_max' => 0.6,
            'y_max' => 0.6,
        ]);

        foreach ([
            ['AABBCCDDEE01', 0, 0],
            ['AABBCCDDEE02', 10, 0],
            ['AABBCCDDEE03', 0, 10],
        ] as [$identifier, $x, $y]) {
            $anchor = Device::query()->create(['identifier' => $identifier, 'name' => $identifier, 'type' => 'beacon']);
            DeviceInstallation::query()->create([
                'device_id' => $anchor->id,
                'location_id' => $location->id,
                'floor_plan_id' => $plan->id,
                'x' => $x,
                'y' => $y,
                'reference_rssi' => -59,
                'path_loss_exponent' => 2,
                'started_at' => now(),
            ]);
        }

        $tracker = Device::query()->create(['identifier' => 'TRACKER01', 'name' => 'Tracker 01', 'type' => 'lorawan_tracker']);
        $asset = Asset::query()->create(['asset_tag' => 'ASSET-1', 'name' => 'Producto A', 'mobility' => 'mobile']);
        AssetDeviceAssignment::query()->create([
            'asset_id' => $asset->id,
            'device_id' => $tracker->id,
            'tracking_strategy' => 'fixed_beacons_mobile_tracker',
            'started_at' => now(),
        ]);
        $connector = Connector::query()->create([
            'name' => 'TTI',
            'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::TtiWebhook,
        ]);
        $event = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', 'position-test'),
            'observed_at' => now(),
            'received_at' => now(),
            'raw_payload' => [
                'end_device_ids' => ['device_id' => 'Tracker 01', 'dev_eui' => 'TRACKER01'],
                'uplink_message' => ['decoded_payload' => ['beacons' => [
                    ['mac' => 'AA:BB:CC:DD:EE:01', 'rssi' => -76],
                    ['mac' => 'AA:BB:CC:DD:EE:02', 'rssi' => -76],
                    ['mac' => 'AA:BB:CC:DD:EE:03', 'rssi' => -76],
                ]]],
            ],
        ]);

        (new ProcessTtiUplink($event->id))->handle(
            app(BleObservationExtractor::class),
            app(PayloadProfileDecoder::class),
            app(TelemetryPositioningService::class),
            app(AssetLastSeenUpdater::class),
        );

        $position = PositionEstimate::query()->firstOrFail();
        $this->assertSame($zone->id, $position->zone_id);
        $this->assertEqualsWithDelta(5.0, (float) $position->x, 0.01);
        $this->assertEqualsWithDelta(5.0, (float) $position->raw_x, 0.01);
        $this->assertSame('rssi_multilateration_kalman', $position->algorithm);
        $this->assertNotNull($position->filter_state);
        $this->assertCount(3, $position->evidence);
        $this->assertSame($location->id, $asset->fresh()->location_id);
        $this->assertTrue($asset->fresh()->last_seen_at->equalTo($event->observed_at));

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->getJson(route('map.data', $plan))
            ->assertOk()
            ->assertJsonStructure(['anchors' => [[
                'id', 'name', 'identifier', 'type', 'x', 'y',
            ]], 'positions' => [[
                'accuracy_meters', 'relative_error', 'error_radius_x', 'error_radius_y',
                'x_meters', 'y_meters', 'raw_x_meters', 'raw_y_meters', 'algorithm', 'algorithm_version', 'calculated_at', 'last_seen_at', 'observed_at', 'received_at',
                'evidence' => [[
                    'identifier', 'name', 'type', 'rssi', 'estimated_distance_meters',
                    'geometric_distance_meters', 'residual_meters', 'reference_rssi',
                    'path_loss_exponent', 'x_meters', 'y_meters', 'x', 'y',
                    'circle_diameter_x', 'circle_diameter_y',
                ]],
            ]]])
            ->assertJsonCount(3, 'anchors')
            ->assertJsonCount(3, 'positions.0.evidence')
            ->assertJsonPath('positions.0.evidence.0.type', 'beacon')
            ->assertJsonPath('positions.0.evidence.0.reference_rssi', -59)
            ->assertJsonPath('positions.0.asset_id', $asset->id)
            ->assertJsonPath('positions.0.last_seen_at', $event->observed_at->toIso8601String());

        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]))
            ->get(route('assets.index', ['mobility' => 'mobile']))
            ->assertOk()
            ->assertSee('X 5.00 m')
            ->assertSee('Y 5.00 m')
            ->assertSee('Última señal')
            ->assertSee('Ver en mapa');

        $secondEvent = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', 'second-position-test'),
            'observed_at' => now()->addMinute(),
            'received_at' => now()->addMinute(),
            'raw_payload' => [
                'end_device_ids' => ['device_id' => 'Tracker 01', 'dev_eui' => 'TRACKER01'],
                'uplink_message' => ['decoded_payload' => ['beacons' => [
                    ['mac' => 'AA:BB:CC:DD:EE:01', 'rssi' => -76],
                    ['mac' => 'AA:BB:CC:DD:EE:02', 'rssi' => -76],
                    ['mac' => 'AA:BB:CC:DD:EE:03', 'rssi' => -76],
                ]]],
            ],
        ]);

        (new ProcessTtiUplink($secondEvent->id))->handle(
            app(BleObservationExtractor::class),
            app(PayloadProfileDecoder::class),
            app(TelemetryPositioningService::class),
            app(AssetLastSeenUpdater::class),
        );

        $this->assertDatabaseHas('position_estimates', [
            'asset_id' => $asset->id,
            'telemetry_event_id' => $event->id,
        ]);
        $this->assertDatabaseHas('position_estimates', [
            'asset_id' => $asset->id,
            'telemetry_event_id' => $secondEvent->id,
        ]);
        $this->assertSame(2, PositionEstimate::query()->where('asset_id', $asset->id)->count());

        $asset->deviceAssignments()->whereNull('ended_at')->update(['ended_at' => now()]);
        PositionEstimate::query()->delete();
        $newerUnusableEvent = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'device_id' => $tracker->id,
            'external_event_id' => hash('sha256', 'newer-unusable-position-test'),
            'observed_at' => now()->addMinute(),
            'received_at' => now()->addMinute(),
            'raw_payload' => ['end_device_ids' => ['dev_eui' => 'TRACKER01']],
            'processing_status' => 'processed',
        ]);
        foreach (['FFFFFFFFFF01', 'FFFFFFFFFF02', 'FFFFFFFFFF03'] as $mac) {
            $newerUnusableEvent->signalObservations()->create([
                'transmitter_mac' => $mac,
                'receiver_identifier' => 'TRACKER01',
                'rssi' => -75,
                'observed_at' => now()->addMinute(),
            ]);
        }
        $historicalDevice = Device::query()->create([
            'identifier' => 'HISTORICAL-TRACKER-ROW',
            'name' => 'Registro histórico duplicado',
            'type' => 'lorawan_tracker',
        ]);
        $event->update(['device_id' => $historicalDevice->id]);
        $lateAsset = Asset::query()->create(['asset_tag' => 'ASSET-LATE', 'name' => 'Asignado después del uplink', 'mobility' => 'mobile']);
        $this->post(route('asset-assignments.store', $lateAsset), [
            'device_id' => $tracker->id,
            'tracking_strategy' => 'fixed_beacons_mobile_tracker',
        ])->assertRedirect();
        $this->assertDatabaseHas('position_estimates', [
            'asset_id' => $lateAsset->id,
            'telemetry_event_id' => $secondEvent->id,
            'floor_plan_id' => $plan->id,
        ]);
    }
}
