<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Connectors\Meraki\MerakiEventRetention;
use App\Connectors\Meraki\MerakiPayloadNormalizer;
use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Jobs\ProcessMerakiLocationObservation;
use App\Models\Asset;
use App\Models\AssetDeviceAssignment;
use App\Models\Connector;
use App\Models\Device;
use App\Models\FloorPlan;
use App\Models\Location;
use App\Models\MerakiFloorPlanMapping;
use App\Models\Organization;
use App\Models\PositionEstimate;
use App\Models\TelemetryEvent;
use App\Positioning\ZoneClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MerakiLocationWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_meraki_v3_registers_mac_deduplicates_and_uses_provider_position(): void
    {
        Queue::fake();
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'acme']);
        $connector = $this->connector($organization, '3');
        $location = Location::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Piso 1',
            'type' => 'floor',
        ]);
        $plan = FloorPlan::query()->create([
            'organization_id' => $organization->id,
            'location_id' => $location->id,
            'name' => 'Piso 1',
            'file_path' => 'floor-plans/test.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'width_meters' => 30,
            'height_meters' => 20,
        ]);
        $zone = $plan->zones()->create([
            'organization_id' => $organization->id,
            'name' => 'Centro',
            'x_min' => 0.2,
            'y_min' => 0.4,
            'x_max' => 0.5,
            'y_max' => 0.8,
        ]);
        MerakiFloorPlanMapping::query()->create([
            'organization_id' => $organization->id,
            'connector_id' => $connector->id,
            'floor_plan_id' => $plan->id,
            'external_floor_plan_id' => 'g_123',
            'invert_y' => true,
        ]);
        $device = Device::query()->create([
            'organization_id' => $organization->id,
            'identifier' => 'AABBCCDDEEFF',
            'name' => 'Beacon pallet',
            'type' => 'beacon',
        ]);
        $asset = Asset::query()->create([
            'organization_id' => $organization->id,
            'asset_tag' => 'PALLET-1',
            'name' => 'Pallet 1',
            'mobility' => 'mobile',
        ]);
        AssetDeviceAssignment::query()->create([
            'organization_id' => $organization->id,
            'asset_id' => $asset->id,
            'device_id' => $device->id,
            'tracking_strategy' => 'mobile_beacon_fixed_scanners',
            'started_at' => now()->subHour(),
        ]);
        $payload = [
            'version' => '3.0',
            'secret' => 'meraki-shared-secret-value',
            'type' => 'BLE',
            'data' => [
                'networkId' => 'L_123',
                'observations' => [[
                    'clientMac' => 'aa:bb:cc:dd:ee:ff',
                    'name' => 'Beacon pallet',
                    'locations' => [[
                        'floorPlan' => ['id' => 'g_123', 'name' => 'Piso Meraki', 'x' => 9, 'y' => 8],
                        'time' => now()->subMinute()->toIso8601String(),
                        'lat' => -33.45,
                        'lng' => -70.66,
                        'variance' => 2.5,
                        'rssiRecords' => [
                            ['apMac' => '00:11:22:33:44:01', 'rssi' => -51],
                            ['apMac' => '00:11:22:33:44:02', 'rssi' => -62],
                            ['apMac' => '00:11:22:33:44:03', 'rssi' => -68],
                        ],
                    ]],
                ]],
            ],
        ];

        $this->postJson(route('api.meraki.ingest', $connector), $payload)
            ->assertAccepted()
            ->assertJsonPath('observations_queued', 1);
        $this->postJson(route('api.meraki.ingest', $connector), $payload)
            ->assertAccepted()
            ->assertJsonPath('duplicates', 1);

        Queue::assertPushed(ProcessMerakiLocationObservation::class, 1);
        $event = TelemetryEvent::query()->firstOrFail();
        $this->process($event);

        $this->assertSame(1, Device::query()->where('identifier', 'AABBCCDDEEFF')->count());
        $this->assertCount(3, $event->fresh()->signalObservations);
        $position = PositionEstimate::query()->firstOrFail();
        $this->assertSame('meraki_location', $position->algorithm);
        $this->assertSame($zone->id, $position->zone_id);
        $this->assertEqualsWithDelta(9, (float) $position->x, 0.001);
        $this->assertEqualsWithDelta(12, (float) $position->y, 0.001);
        $this->assertEqualsWithDelta(8, (float) $position->raw_y, 0.001);
        $this->assertEqualsWithDelta(2.5, (float) $position->accuracy_meters, 0.001);
        $this->assertTrue($asset->fresh()->last_seen_at->equalTo($event->observed_at));
        $this->assertArrayNotHasKey('rssi_records', $event->fresh()->raw_payload);
        $this->assertCount(3, $event->fresh()->normalized_payload['rssi_records']);
    }

    public function test_meraki_v2_registers_unassigned_ble_device_and_validator_is_available_in_draft(): void
    {
        Queue::fake();
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'acme']);
        $connector = $this->connector($organization, '2');
        $connector->update(['status' => ConnectorStatus::Draft]);

        $this->get(route('api.meraki.validate', $connector))
            ->assertOk()
            ->assertSeeText('meraki-validator-value');

        $connector->update(['status' => ConnectorStatus::Active]);
        $payload = [
            'version' => '2.1',
            'secret' => 'meraki-shared-secret-value',
            'type' => 'BluetoothDevicesSeen',
            'data' => [
                'apMac' => '00:18:0a:13:dd:b0',
                'apFloors' => ['Bodega'],
                'observations' => [[
                    'clientMac' => '18:fe:34:d7:ff:aa',
                    'name' => 'asset-1',
                    'seenTime' => now()->subMinute()->toIso8601String(),
                    'rssi' => -56,
                    'location' => ['lat' => -33.4, 'lng' => -70.6, 'unc' => 4.2, 'x' => [5], 'y' => [7]],
                ]],
            ],
        ];

        $this->postJson(route('api.meraki.ingest', $connector), $payload)->assertAccepted();
        $event = TelemetryEvent::query()->firstOrFail();
        $this->process($event);

        $this->assertDatabaseHas('devices', [
            'identifier' => '18FE34D7FFAA',
            'type' => 'beacon',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('position_estimates', 0);
        $this->assertDatabaseHas('signal_observations', [
            'transmitter_mac' => '18FE34D7FFAA',
            'receiver_identifier' => '00180A13DDB0',
            'rssi' => -56,
        ]);
    }

    public function test_meraki_rejects_wrong_secret_and_wrong_major_version(): void
    {
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'acme']);
        $connector = $this->connector($organization, '3');

        $this->postJson(route('api.meraki.ingest', $connector), [
            'version' => '3.0',
            'secret' => 'wrong',
        ])->assertUnauthorized();

        $this->postJson(route('api.meraki.ingest', $connector), [
            'version' => '2.1',
            'secret' => 'meraki-shared-secret-value',
        ])->assertStatus(422);
    }

    public function test_same_mac_is_isolated_between_organizations(): void
    {
        Queue::fake();
        $firstOrganization = Organization::query()->create(['name' => 'Empresa A', 'slug' => 'empresa-a']);
        $secondOrganization = Organization::query()->create(['name' => 'Empresa B', 'slug' => 'empresa-b']);
        $firstConnector = $this->connector($firstOrganization, '3');
        $secondConnector = $this->connector($secondOrganization, '3');
        $payload = $this->versionThreePayload('aa:bb:cc:dd:ee:ff');

        $this->postJson(route('api.meraki.ingest', $firstConnector), $payload)->assertAccepted();
        $this->postJson(route('api.meraki.ingest', $secondConnector), $payload)->assertAccepted();

        foreach (TelemetryEvent::query()->orderBy('id')->get() as $event) {
            $this->process($event);
        }

        $devices = Device::query()->where('identifier', 'AABBCCDDEEFF')->get();
        $this->assertCount(2, $devices);
        $this->assertEqualsCanonicalizing(
            [$firstOrganization->id, $secondOrganization->id],
            $devices->pluck('organization_id')->all(),
        );
        $this->assertDatabaseHas('telemetry_events', [
            'organization_id' => $firstOrganization->id,
            'connector_id' => $firstConnector->id,
        ]);
        $this->assertDatabaseHas('telemetry_events', [
            'organization_id' => $secondOrganization->id,
            'connector_id' => $secondConnector->id,
        ]);
    }

    public function test_inactive_organization_cannot_validate_or_receive_meraki_events(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Empresa suspendida',
            'slug' => 'empresa-suspendida',
            'active' => false,
        ]);
        $connector = $this->connector($organization, '3');

        $this->get(route('api.meraki.validate', $connector))->assertNotFound();
        $this->postJson(
            route('api.meraki.ingest', $connector),
            $this->versionThreePayload('aa:bb:cc:dd:ee:ff'),
        )->assertNotFound();
        $this->assertDatabaseCount('telemetry_events', 0);
    }

    public function test_real_expanded_meraki_record_is_compacted_into_normalized_payload(): void
    {
        $payload = json_decode((string) file_get_contents(
            base_path('tests/Fixtures/meraki/v3-expanded-record.json'),
        ), true, 512, JSON_THROW_ON_ERROR);

        $record = app(MerakiPayloadNormalizer::class)->records($payload, 3)[0];

        $this->assertSame('E455A815A240', strtoupper(str_replace(':', '', $record['client_mac'])));
        $this->assertSame('L_597289900580014244', $record['network_id']);
        $this->assertSame('f6b2afd3-1419-40c9-877b-2135fd81ec4b', $record['metadata']['ble_beacons'][0]['uuid']);
        $this->assertCount(3, $record['rssi_records']);
        $this->assertSame('AP-01', $record['rssi_records'][0]['apName']);
        $this->assertSame('Q3AE-ONE1-TEST', $record['rssi_records'][0]['apSerial']);
        $this->assertSame(3, $record['source_summary']['reporting_ap_count']);
        $this->assertSame(6, $record['source_summary']['location_count']);
        $this->assertTrue($record['source_summary']['compacted_from_expanded_record']);
        $this->assertArrayNotHasKey('raw', $record);
        $this->assertArrayNotHasKey('locations', $record['metadata']);
        $this->assertLessThan(5000, strlen(json_encode($record, JSON_THROW_ON_ERROR)));
    }

    public function test_authenticated_expanded_record_can_enter_the_webhook_pipeline(): void
    {
        Queue::fake();
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'expanded-acme']);
        $connector = $this->connector($organization, '3');
        $connector->update(['configuration' => [
            'api_version' => '3',
            'network_id' => 'L_597289900580014244',
        ]]);
        $payload = json_decode((string) file_get_contents(
            base_path('tests/Fixtures/meraki/v3-expanded-record.json'),
        ), true, 512, JSON_THROW_ON_ERROR);
        $payload['secret'] = 'meraki-shared-secret-value';

        $this->postJson(route('api.meraki.ingest', $connector), $payload)
            ->assertAccepted()
            ->assertJsonPath('observations_queued', 1);

        $event = TelemetryEvent::query()->firstOrFail();
        $this->assertLessThan(5000, strlen(json_encode($event->raw_payload, JSON_THROW_ON_ERROR)));
        $this->process($event);
        $event->refresh();
        $this->assertSame('E455A815A240', $event->device->identifier);
        $this->assertSame(3, $event->normalized_payload['source_summary']['reporting_ap_count']);
        $this->assertArrayNotHasKey('rssi_records', $event->raw_payload);
    }

    public function test_meraki_history_keeps_only_latest_ten_events_per_device(): void
    {
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'history-acme']);
        $connector = $this->connector($organization, '3');
        $device = Device::query()->create([
            'organization_id' => $organization->id,
            'identifier' => 'AABBCCDDEEFF',
            'name' => 'Beacon',
            'type' => 'beacon',
        ]);

        for ($index = 0; $index < 12; $index++) {
            $event = TelemetryEvent::query()->create([
                'organization_id' => $organization->id,
                'connector_id' => $connector->id,
                'device_id' => $device->id,
                'external_event_id' => hash('sha256', 'meraki-history-'.$index),
                'event_type' => 'meraki_location',
                'observed_at' => now()->subMinutes(12 - $index),
                'received_at' => now()->subMinutes(12 - $index),
                'raw_payload' => ['client_mac' => $device->identifier],
                'processing_status' => 'processed',
            ]);
            app(MerakiEventRetention::class)->prune($event);
        }

        $events = TelemetryEvent::query()
            ->where('connector_id', $connector->id)
            ->where('device_id', $device->id)
            ->orderBy('observed_at')
            ->get();
        $this->assertCount(10, $events);
        $this->assertSame(
            hash('sha256', 'meraki-history-2'),
            $events->first()->external_event_id,
        );
    }

    public function test_scheduled_pruner_corrects_existing_meraki_history(): void
    {
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'pruner-acme']);
        $connector = $this->connector($organization, '3');
        $device = Device::query()->create([
            'organization_id' => $organization->id,
            'identifier' => '112233445566',
            'name' => 'Beacon',
            'type' => 'beacon',
        ]);
        for ($index = 0; $index < 14; $index++) {
            TelemetryEvent::query()->create([
                'organization_id' => $organization->id,
                'connector_id' => $connector->id,
                'device_id' => $device->id,
                'external_event_id' => hash('sha256', 'scheduled-history-'.$index),
                'event_type' => 'meraki_location',
                'observed_at' => now()->subMinutes(14 - $index),
                'received_at' => now()->subMinutes(14 - $index),
                'raw_payload' => ['client_mac' => $device->identifier],
                'processing_status' => 'processed',
            ]);
        }

        $this->artisan('loratrack:prune-meraki-history')
            ->expectsOutput('Eventos Meraki que exceden la retención: 4.')
            ->expectsOutput('Eventos Meraki antiguos eliminados: 4.')
            ->expectsOutput('Pendientes aproximados: 0.')
            ->assertSuccessful();
        $this->assertSame(10, TelemetryEvent::query()
            ->where('connector_id', $connector->id)
            ->where('device_id', $device->id)
            ->count());
    }

    public function test_meraki_pruner_supports_dry_run_and_delete_limits(): void
    {
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'limited-pruner']);
        $connector = $this->connector($organization, '3');
        $device = Device::query()->create([
            'organization_id' => $organization->id,
            'identifier' => '665544332211',
            'name' => 'Beacon',
            'type' => 'beacon',
        ]);
        for ($index = 0; $index < 15; $index++) {
            TelemetryEvent::query()->create([
                'organization_id' => $organization->id,
                'connector_id' => $connector->id,
                'device_id' => $device->id,
                'external_event_id' => hash('sha256', 'limited-history-'.$index),
                'event_type' => 'meraki_location',
                'observed_at' => now()->subMinutes(15 - $index),
                'received_at' => now()->subMinutes(15 - $index),
                'raw_payload' => ['client_mac' => $device->identifier],
            ]);
        }

        $this->artisan('loratrack:prune-meraki-history', ['--dry-run' => true])
            ->expectsOutput('Eventos Meraki que exceden la retención: 5.')
            ->assertSuccessful();
        $this->assertSame(15, TelemetryEvent::query()->where('device_id', $device->id)->count());

        $this->artisan('loratrack:prune-meraki-history', ['--max-delete' => 2])
            ->expectsOutput('Eventos Meraki antiguos eliminados: 2.')
            ->expectsOutput('Pendientes aproximados: 3.')
            ->assertSuccessful();
        $this->assertSame(13, TelemetryEvent::query()->where('device_id', $device->id)->count());
    }

    private function connector(Organization $organization, string $version): Connector
    {
        return Connector::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Meraki',
            'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::MerakiLocation,
            'status' => ConnectorStatus::Active,
            'configuration' => ['api_version' => $version, 'network_id' => $version === '3' ? 'L_123' : null],
            'credentials' => [
                'validator' => 'meraki-validator-value',
                'shared_secret' => 'meraki-shared-secret-value',
            ],
        ]);
    }

    private function process(TelemetryEvent $event): void
    {
        (new ProcessMerakiLocationObservation($event->id))->handle(
            app(ZoneClassifier::class),
            app(MerakiEventRetention::class),
        );
    }

    /** @return array<string, mixed> */
    private function versionThreePayload(string $mac): array
    {
        return [
            'version' => '3.0',
            'secret' => 'meraki-shared-secret-value',
            'type' => 'BLE',
            'data' => [
                'networkId' => 'L_123',
                'observations' => [[
                    'clientMac' => $mac,
                    'locations' => [[
                        'floorPlan' => ['id' => 'g_123', 'x' => 9, 'y' => 8],
                        'time' => '2026-06-23T12:00:00Z',
                        'variance' => 2.5,
                        'rssiRecords' => [['apMac' => '00:11:22:33:44:01', 'rssi' => -51]],
                    ]],
                ]],
            ],
        ];
    }
}
