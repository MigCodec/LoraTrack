<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\AssetDeviceAssignment;
use App\Models\Connector;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\PositionEstimate;
use App\Models\SignalObservation;
use App\Models\TelemetryEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_links_to_device_inventory_with_locations_and_recent_receivers(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $location = Location::query()->create(['name' => 'Piso 1', 'type' => 'floor']);
        $plan = FloorPlan::query()->create([
            'location_id' => $location->id,
            'name' => 'Bodega',
            'file_path' => 'floor-plans/bodega.png',
            'original_name' => 'bodega.png',
            'mime_type' => 'image/png',
            'width_meters' => 20,
            'height_meters' => 10,
        ]);
        $scanner = Device::query()->create(['identifier' => 'AP0011223344', 'name' => 'AP norte', 'type' => 'scanner']);
        DeviceInstallation::query()->create([
            'device_id' => $scanner->id,
            'location_id' => $location->id,
            'floor_plan_id' => $plan->id,
            'x' => 5,
            'y' => 3,
            'reference_rssi' => -59,
            'path_loss_exponent' => 2,
            'started_at' => now(),
        ]);

        $beacon = Device::query()->create(['identifier' => 'AA:BB:CC:DD:EE:01', 'name' => 'Beacon movil', 'type' => 'beacon']);
        $asset = Asset::query()->create(['asset_tag' => 'AS-001', 'name' => 'Taladro', 'mobility' => 'mobile']);
        AssetDeviceAssignment::query()->create([
            'asset_id' => $asset->id,
            'device_id' => $beacon->id,
            'tracking_strategy' => 'mobile_beacon_fixed_scanners',
            'started_at' => now()->subHour(),
        ]);
        PositionEstimate::query()->create([
            'asset_id' => $asset->id,
            'location_id' => $location->id,
            'floor_plan_id' => $plan->id,
            'algorithm' => 'weighted_centroid',
            'algorithm_version' => '1',
            'x' => 8,
            'y' => 4,
            'confidence' => 0.5,
            'calculated_at' => now()->subMinutes(2),
        ]);
        $connector = Connector::query()->create(['name' => 'TTI', 'kind' => 'telemetry', 'provider' => 'tti_webhook', 'status' => 'active']);
        $event = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'external_event_id' => 'device-index-1',
            'event_type' => 'uplink',
            'observed_at' => now()->subMinute(),
            'received_at' => now(),
            'raw_payload' => ['test' => true],
            'processing_status' => 'processed',
        ]);
        SignalObservation::query()->create([
            'telemetry_event_id' => $event->id,
            'transmitter_mac' => 'AABBCCDDEE01',
            'receiver_identifier' => 'AP0011223344',
            'rssi' => -70,
            'observed_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('devices.index'), false)
            ->assertSee('Ver dispositivos');

        $this->actingAs($admin)->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Inventario de dispositivos')
            ->assertSee('AP norte')
            ->assertSee('Piso 1 - Bodega - X 5.00 m, Y 3.00 m')
            ->assertSee('Beacon movil')
            ->assertSee('Sin area/zona establecida')
            ->assertSee('AP0011223344')
            ->assertSee('Ver historial AP MAC')
            ->assertSee(route('devices.ap-history', $beacon), false);
    }

    public function test_device_inventory_is_paginated(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach (range(1, 55) as $number) {
            Device::query()->create([
                'identifier' => sprintf('DEVICE%04d', $number),
                'name' => sprintf('Device %04d', $number),
                'type' => 'beacon',
            ]);
        }

        $this->actingAs($admin)->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Device 0001')
            ->assertDontSee('Device 0055')
            ->assertSee('page=2', false);

        $this->actingAs($admin)->get(route('devices.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Device 0055');
    }

    public function test_device_inventory_can_be_searched_by_name_or_identifier(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Device::query()->create([
            'identifier' => 'AA:BB:CC:DD:EE:01',
            'name' => 'Beacon sala norte',
            'type' => 'beacon',
            'model' => 'B1000',
        ]);
        Device::query()->create([
            'identifier' => '112233445566',
            'name' => 'Tracker bodega sur',
            'type' => 'lorawan_tracker',
        ]);

        $this->actingAs($admin)->get(route('devices.index', ['q' => 'sala norte']))
            ->assertOk()
            ->assertSee('Beacon sala norte')
            ->assertDontSee('Tracker bodega sur')
            ->assertSee('value="sala norte"', false);

        $this->actingAs($admin)->get(route('devices.index', ['q' => 'aa-bb-cc-dd-ee-01']))
            ->assertOk()
            ->assertSee('Beacon sala norte')
            ->assertDontSee('Tracker bodega sur');
    }

    public function test_device_ap_history_is_paginated_and_limited_to_retention_window(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $device = Device::query()->create([
            'identifier' => 'AA:BB:CC:DD:EE:01',
            'name' => 'Beacon movil',
            'type' => 'beacon',
        ]);
        $connector = Connector::query()->create(['name' => 'Meraki', 'kind' => 'telemetry', 'provider' => 'meraki_location', 'status' => 'active']);

        for ($index = 1; $index <= 27; $index++) {
            $event = TelemetryEvent::query()->create([
                'connector_id' => $connector->id,
                'device_id' => $device->id,
                'external_event_id' => 'device-ap-history-'.$index,
                'event_type' => 'meraki_location',
                'observed_at' => now()->subMinutes($index),
                'received_at' => now()->subMinutes($index),
                'raw_payload' => ['client_mac' => $device->identifier],
                'processing_status' => 'processed',
            ]);
            SignalObservation::query()->create([
                'telemetry_event_id' => $event->id,
                'transmitter_mac' => 'AABBCCDDEE01',
                'receiver_identifier' => sprintf('AP%010d', $index),
                'rssi' => -50 - $index,
                'observed_at' => now()->subMinutes($index),
                'metadata' => ['source' => 'meraki'],
            ]);
        }

        $oldEvent = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'device_id' => $device->id,
            'external_event_id' => 'device-ap-history-old',
            'event_type' => 'meraki_location',
            'observed_at' => now()->subDays(7),
            'received_at' => now()->subDays(7),
            'raw_payload' => ['client_mac' => $device->identifier],
            'processing_status' => 'processed',
        ]);
        SignalObservation::query()->create([
            'telemetry_event_id' => $oldEvent->id,
            'transmitter_mac' => 'AABBCCDDEE01',
            'receiver_identifier' => 'APOLD000001',
            'rssi' => -80,
            'observed_at' => now()->subDays(7),
        ]);

        $this->actingAs($admin)->getJson(route('devices.ap-history', $device))
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 27)
            ->assertJsonPath('meta.retention_days', 6)
            ->assertJsonPath('data.0.ap_mac', 'AP0000000001')
            ->assertJsonMissing(['ap_mac' => 'APOLD000001']);

        $this->actingAs($admin)->getJson(route('devices.ap-history', [$device, 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.ap_mac', 'AP0000000027');
    }
}
