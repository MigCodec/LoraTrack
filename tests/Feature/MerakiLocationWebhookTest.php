<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Connectors\Meraki\MerakiAccessPointRegistrar;
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
use App\Tenancy\OrganizationContext;
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
        $this->assertCount(3, $record['reporting_aps']);
        $this->assertSame('AP-01', $record['rssi_records'][0]['apName']);
        $this->assertSame('Q3AE-ONE1-TEST', $record['rssi_records'][0]['apSerial']);
        $this->assertSame('AP-03', $record['reporting_aps'][2]['apName']);
        $this->assertSame(3, $record['source_summary']['reporting_ap_count']);
        $this->assertSame(6, $record['source_summary']['location_count']);
        $this->assertTrue($record['source_summary']['compacted_from_expanded_record']);
        $this->assertArrayNotHasKey('raw', $record);
        $this->assertArrayNotHasKey('locations', $record['metadata']);
        $this->assertLessThan(5000, strlen(json_encode($record, JSON_THROW_ON_ERROR)));
    }

    public function test_compacted_meraki_record_preserves_summary_and_snake_case_metadata(): void
    {
        $payload = [
            'version' => '3.0',
            'type' => 'Bluetooth',
            'network_id' => 'L_597289900580014244',
            'client_mac' => 'e4:55:a8:15:a2:d7',
            'client_name' => 'unknown',
            'observed_at' => '2026-06-26T19:35:17Z',
            'external_floor_plan_id' => '',
            'external_floor_plan_name' => '',
            'x' => null,
            'y' => null,
            'latitude' => 16.436738861377624,
            'longitude' => -106.87157129737767,
            'accuracy_meters' => 100,
            'rssi_records' => [
                [
                    'apMac' => 'e4:55:a8:15:a3:b2',
                    'rssi' => -61,
                    'apName' => 'ANF-AP-THER-2-3',
                    'apSerial' => 'Q3AE-TGJL-V7HD',
                    'lat' => -33.465,
                    'lng' => -70.656,
                ],
                [
                    'apMac' => 'e4:55:a8:15:a2:a0',
                    'rssi' => -58,
                    'apName' => 'ANF-AP-THER-2-2',
                    'apSerial' => 'Q3AE-RDXF-NLEL',
                    'lat' => 37.4180951010362,
                    'lng' => -122.098531723022,
                ],
            ],
            'metadata' => [
                'name' => 'unknown',
                'ble_beacons' => [[
                    'uuid' => 'f6b2afd3-1419-40c9-877b-2135fd81ec4b',
                    'txPower' => -59,
                    'major' => 0,
                    'bleType' => 'iBeacon',
                    'minor' => 212,
                ]],
                'latest_record' => [
                    'time' => '2026-06-26T19:35:18Z',
                    'nearest_ap_mac' => 'e4:55:a8:15:a2:a0',
                    'nearest_ap_rssi' => -58,
                ],
            ],
            'source_summary' => [
                'reporting_ap_count' => 169,
                'location_count' => 6,
                'rssi_record_count' => 17,
                'nearest_ap_mac' => 'e4:55:a8:15:a2:a0',
            ],
        ];

        $record = app(MerakiPayloadNormalizer::class)->records($payload, 3)[0];

        $this->assertSame('E455A815A2D7', strtoupper(str_replace(':', '', $record['client_mac'])));
        $this->assertCount(2, $record['rssi_records']);
        $this->assertCount(0, $record['reporting_aps']);
        $this->assertSame('f6b2afd3-1419-40c9-877b-2135fd81ec4b', $record['metadata']['ble_beacons'][0]['uuid']);
        $this->assertSame('e4:55:a8:15:a2:a0', $record['metadata']['latest_record']['nearest_ap_mac']);
        $this->assertSame(-58, $record['metadata']['latest_record']['nearest_ap_rssi']);
        $this->assertSame(169, $record['source_summary']['reporting_ap_count']);
        $this->assertSame(6, $record['source_summary']['location_count']);
        $this->assertSame('e4:55:a8:15:a2:a0', $record['source_summary']['nearest_ap_mac']);
        $this->assertTrue($record['source_summary']['compacted_from_expanded_record']);
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
        $scanners = Device::query()->where('type', 'scanner')->orderBy('identifier')->get();
        $this->assertCount(3, $scanners);
        $this->assertSame('AP-01', $scanners->firstWhere('identifier', 'E455A815A238')->name);
        $this->assertSame('Q3AE-ONE1-TEST', data_get($scanners->firstWhere('identifier', 'E455A815A238')->metadata, 'meraki.serial'));
        $this->assertSame('pending_floor_plan', data_get($scanners->firstWhere('identifier', 'E455A815A238')->metadata, 'meraki.installation_status'));
        $this->assertTrue($scanners->every(fn (Device $scanner): bool => $scanner->last_seen_at->equalTo($event->observed_at)));
        $this->assertTrue($scanners->every(fn (Device $scanner): bool => $scanner->installations()->doesntExist()));
    }

    public function test_meraki_v3_registers_reporting_aps_even_without_rssi_records(): void
    {
        Queue::fake();
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'reporting-aps']);
        $connector = $this->connector($organization, '3');
        $payload = [
            'version' => '3.0',
            'secret' => 'meraki-shared-secret-value',
            'type' => 'Bluetooth',
            'data' => [
                'networkId' => 'L_123',
                'reportingAps' => [
                    ['serial' => 'Q3AE-ONE1-TEST', 'mac' => 'e4:55:a8:15:a2:38', 'name' => 'AP-01'],
                    ['serial' => 'Q3AE-TWO2-TEST', 'mac' => 'e4:55:a8:15:a2:a9', 'name' => 'AP-02'],
                    ['serial' => 'Q3AE-THR3-TEST', 'mac' => 'f8:9e:28:81:8d:bb', 'name' => 'AP-03'],
                ],
                'observations' => [[
                    'clientMac' => 'aa:bb:cc:dd:ee:ff',
                    'latestRecord' => [
                        'time' => now()->subMinute()->toIso8601String(),
                        'nearestApMac' => 'e4:55:a8:15:a2:38',
                        'nearestApRssi' => -64,
                    ],
                    'locations' => [],
                ]],
            ],
        ];

        $this->postJson(route('api.meraki.ingest', $connector), $payload)
            ->assertAccepted()
            ->assertJsonPath('observations_queued', 1);

        $event = TelemetryEvent::query()->firstOrFail();
        $this->process($event);

        $scanners = Device::query()->where('type', 'scanner')->orderBy('identifier')->get();
        $this->assertCount(3, $scanners);
        $this->assertSame('AP-01', $scanners->firstWhere('identifier', 'E455A815A238')->name);
        $this->assertSame('AP-02', $scanners->firstWhere('identifier', 'E455A815A2A9')->name);
        $this->assertSame('AP-03', $scanners->firstWhere('identifier', 'F89E28818DBB')->name);
        $this->assertCount(1, $event->fresh()->signalObservations);
    }

    public function test_meraki_ap_reading_promotes_unassigned_existing_device_to_scanner(): void
    {
        Queue::fake();
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'promote-ap']);
        $connector = $this->connector($organization, '3');
        Device::query()->create([
            'organization_id' => $organization->id,
            'identifier' => 'E455A815A3B2',
            'name' => 'Dispositivo observado previamente',
            'type' => 'beacon',
        ]);
        $payload = [
            'version' => '3.0',
            'secret' => 'meraki-shared-secret-value',
            'type' => 'Bluetooth',
            'data' => [
                'networkId' => 'L_123',
                'observations' => [[
                    'clientMac' => 'aa:bb:cc:dd:ee:ff',
                    'locations' => [[
                        'time' => now()->subMinute()->toIso8601String(),
                        'rssiRecords' => [[
                            'apMac' => 'e4:55:a8:15:a3:b2',
                            'rssi' => -63,
                            'apName' => 'ANF-AP-THER-2-3',
                            'apSerial' => 'Q3AE-TGJL-V7HD',
                        ]],
                    ]],
                ]],
            ],
        ];

        $this->postJson(route('api.meraki.ingest', $connector), $payload)->assertAccepted();
        $event = TelemetryEvent::query()->firstOrFail();
        $this->process($event);

        $device = Device::query()->where('identifier', 'E455A815A3B2')->firstOrFail();
        $this->assertSame('scanner', $device->type);
        $this->assertSame('access_point_scanner', data_get($device->metadata, 'meraki.role'));
        $this->assertSame('Q3AE-TGJL-V7HD', data_get($device->metadata, 'meraki.serial'));
    }

    public function test_meraki_access_point_backfill_registers_aps_from_processed_normalized_payloads(): void
    {
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'backfill-aps']);
        $connector = $this->connector($organization, '3');
        TelemetryEvent::query()->create([
            'organization_id' => $organization->id,
            'connector_id' => $connector->id,
            'external_event_id' => 'backfill-meraki-aps',
            'event_type' => 'meraki_location',
            'observed_at' => now()->subMinute(),
            'received_at' => now(),
            'raw_payload' => ['source_summary' => ['payload_checksum' => 'backfill']],
            'normalized_payload' => [
                'network_id' => 'L_123',
                'rssi_records' => [
                    [
                        'apMac' => 'e4:55:a8:15:a3:b2',
                        'rssi' => -63,
                        'apName' => 'ANF-AP-THER-2-3',
                        'apSerial' => 'Q3AE-TGJL-V7HD',
                    ],
                    [
                        'apMac' => 'e4:55:a8:15:a2:ff',
                        'rssi' => -65,
                        'apName' => 'ANF-AP-THER-1-3',
                        'apSerial' => 'Q3AE-TX6J-MQAL',
                    ],
                ],
            ],
            'processing_status' => 'processed',
        ]);

        $this->artisan('loratrack:backfill-meraki-access-points')
            ->expectsOutput('AP Meraki detectables en eventos revisados: 2.')
            ->expectsOutput('AP Meraki creados o actualizados como scanner: 2.')
            ->assertSuccessful();

        $this->assertDatabaseHas('devices', [
            'organization_id' => $organization->id,
            'identifier' => 'E455A815A3B2',
            'type' => 'scanner',
        ]);
        $this->assertDatabaseHas('devices', [
            'organization_id' => $organization->id,
            'identifier' => 'E455A815A2FF',
            'type' => 'scanner',
        ]);
    }

    public function test_older_meraki_observation_does_not_move_scanner_last_seen_backwards(): void
    {
        $organization = Organization::query()->create(['name' => 'ACME', 'slug' => 'scanner-last-seen']);
        app(OrganizationContext::class)->set($organization);
        try {
            $registrar = app(MerakiAccessPointRegistrar::class);
            $registrar->register([
                'apMac' => '00:11:22:33:44:55',
                'apName' => 'AP principal',
                'apSerial' => 'Q3AE-LAST-SEEN',
            ], now(), 'L_123');
            $registrar->register([
                'apMac' => '00:11:22:33:44:55',
                'apName' => 'AP principal',
                'apSerial' => 'Q3AE-LAST-SEEN',
            ], now()->subDay(), 'L_123');

            $scanner = Device::query()->where('identifier', '001122334455')->firstOrFail();
            $this->assertTrue($scanner->last_seen_at->greaterThan(now()->subMinute()));
            $this->assertSame('scanner', $scanner->type);
            $this->assertSame($organization->id, $scanner->organization_id);
        } finally {
            app(OrganizationContext::class)->set(null);
        }
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
            app(MerakiAccessPointRegistrar::class),
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
