<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connector;
use App\Models\Device;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\SignalObservation;
use App\Models\TelemetryEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerakiAccessPointIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_meraki_access_point_inventory_shows_connector_metadata_location_and_activity(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $location = Location::query()->create(['name' => 'Piso 3', 'type' => 'floor']);
        $plan = FloorPlan::query()->create([
            'location_id' => $location->id,
            'name' => 'Oficina norte',
            'file_path' => 'floor-plans/oficina.png',
            'original_name' => 'oficina.png',
            'mime_type' => 'image/png',
            'width_meters' => 30,
            'height_meters' => 18,
        ]);
        $installedAp = Device::query()->create([
            'identifier' => 'E455A815A238',
            'name' => 'MR Oficina Norte',
            'type' => 'scanner',
            'model' => 'Cisco Meraki AP',
            'last_seen_at' => now()->subMinutes(3),
            'metadata' => [
                'meraki' => [
                    'role' => 'access_point_scanner',
                    'network_id' => 'L_123456789',
                    'serial' => 'Q3AE-ONE1-TEST',
                    'reported_latitude' => -31.695,
                    'reported_longitude' => -71.948,
                ],
            ],
        ]);
        DeviceInstallation::query()->create([
            'device_id' => $installedAp->id,
            'location_id' => $location->id,
            'floor_plan_id' => $plan->id,
            'x' => 7.5,
            'y' => 4.25,
            'reference_rssi' => -59,
            'path_loss_exponent' => 2,
            'started_at' => now()->subDay(),
        ]);
        Device::query()->create([
            'identifier' => 'E455A815A240',
            'name' => 'MR Pendiente',
            'type' => 'scanner',
            'model' => 'Cisco Meraki AP',
            'metadata' => ['meraki' => ['role' => 'access_point_scanner', 'serial' => 'Q3AE-TWO2-TEST']],
        ]);
        Device::query()->create([
            'identifier' => 'SCANNER-LOCAL',
            'name' => 'Scanner no Meraki',
            'type' => 'scanner',
        ]);

        $connector = Connector::query()->create(['name' => 'Meraki', 'kind' => 'telemetry', 'provider' => 'meraki_location', 'status' => 'active']);
        $event = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'external_event_id' => 'meraki-ap-index-1',
            'event_type' => 'meraki_location_observation',
            'observed_at' => now()->subMinute(),
            'received_at' => now(),
            'raw_payload' => ['test' => true],
            'processing_status' => 'processed',
        ]);
        foreach (['AA:BB:CC:DD:EE:01', 'AA:BB:CC:DD:EE:02'] as $clientMac) {
            SignalObservation::query()->create([
                'telemetry_event_id' => $event->id,
                'transmitter_mac' => $clientMac,
                'receiver_identifier' => 'E455A815A238',
                'rssi' => -70,
                'observed_at' => now()->subMinute(),
            ]);
        }

        $this->actingAs($admin)->get(route('meraki-access-points.index'))
            ->assertOk()
            ->assertSee('Access points Meraki')
            ->assertSee(route('api.meraki-access-points.index'), false)
            ->assertSee('data-meraki-access-points', false)
            ->assertDontSee('Scanner no Meraki');

        $this->actingAs($admin)->getJson(route('api.meraki-access-points.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'MR Oficina Norte')
            ->assertJsonPath('data.0.identifier', 'E455A815A238')
            ->assertJsonPath('data.0.serial', 'Q3AE-ONE1-TEST')
            ->assertJsonPath('data.0.network_id', 'L_123456789')
            ->assertJsonPath('data.0.reported_latitude', -31.695)
            ->assertJsonPath('data.0.reported_longitude', -71.948)
            ->assertJsonPath('data.0.location_label', 'Piso 3 - Oficina norte - X 7.50 m, Y 4.25 m')
            ->assertJsonPath('data.0.clients_count', 2)
            ->assertJsonPath('data.1.name', 'MR Pendiente')
            ->assertJsonPath('data.1.status_label', 'Pendiente de plano');

        $this->actingAs($admin)->getJson(route('api.meraki-access-points.index', ['q' => 'Q3AE-ONE1']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertSee('MR Oficina Norte')
            ->assertDontSee('Scanner no Meraki');
    }

    public function test_meraki_access_point_inventory_is_paginated(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach (range(1, 55) as $number) {
            Device::query()->create([
                'identifier' => sprintf('MERAKIAP%04d', $number),
                'name' => sprintf('Meraki AP %04d', $number),
                'type' => 'scanner',
                'metadata' => ['meraki' => ['role' => 'access_point_scanner']],
            ]);
        }

        $this->actingAs($admin)->getJson(route('api.meraki-access-points.index'))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 55)
            ->assertJsonPath('data.0.name', 'Meraki AP 0001')
            ->assertJsonMissing(['name' => 'Meraki AP 0055']);

        $this->actingAs($admin)->getJson(route('api.meraki-access-points.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.4.name', 'Meraki AP 0055');
    }
}
