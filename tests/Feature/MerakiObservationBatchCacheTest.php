<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\Device;
use App\Models\Organization;
use App\Models\TelemetryEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MerakiObservationBatchCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reuses_access_point_cache_across_its_observation_batch(): void
    {
        $organization = Organization::query()->create(['name' => 'Cache AP', 'slug' => 'cache-ap']);
        $connector = $this->connector($organization);
        $firstSeenAt = now()->subMinute();
        $lastSeenAt = now();
        $this->event($organization, $connector, 'AABBCCDDEE01', 'event-one', $firstSeenAt);
        $this->event($organization, $connector, 'AABBCCDDEE02', 'event-two', $lastSeenAt);

        $accessPointSelects = 0;
        DB::listen(function (QueryExecuted $query) use (&$accessPointSelects): void {
            if (str_starts_with(mb_strtolower(ltrim($query->sql)), 'select')
                && in_array('001122334455', $query->bindings, true)
            ) {
                $accessPointSelects++;
            }
        });

        $this->artisan('loratrack:process-meraki-observations', ['--limit' => 2])
            ->assertSuccessful();

        $this->assertSame(1, $accessPointSelects);
        $this->assertSame(2, TelemetryEvent::query()->where('processing_status', 'processed')->count());
        $this->assertSame(1, Device::query()->where('identifier', '001122334455')->count());
        $this->assertTrue(
            Device::query()->where('identifier', '001122334455')->firstOrFail()->last_seen_at->equalTo($lastSeenAt),
        );
    }

    public function test_shared_cache_keeps_identical_access_point_macs_isolated_by_organization(): void
    {
        $first = Organization::query()->create(['name' => 'Empresa AP A', 'slug' => 'empresa-ap-a']);
        $second = Organization::query()->create(['name' => 'Empresa AP B', 'slug' => 'empresa-ap-b']);
        $this->event($first, $this->connector($first), 'AABBCCDDEE01', 'event-a');
        $this->event($second, $this->connector($second), 'AABBCCDDEE02', 'event-b');

        $this->artisan('loratrack:process-meraki-observations', ['--limit' => 2])
            ->assertSuccessful();

        $accessPoints = Device::query()->where('identifier', '001122334455')->get();
        $this->assertCount(2, $accessPoints);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $accessPoints->pluck('organization_id')->all(),
        );
    }

    private function connector(Organization $organization): Connector
    {
        return Connector::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Meraki '.$organization->name,
            'kind' => 'telemetry',
            'provider' => 'meraki_location',
            'status' => 'active',
        ]);
    }

    private function event(
        Organization $organization,
        Connector $connector,
        string $clientMac,
        string $externalId,
        ?Carbon $seenAt = null,
    ): TelemetryEvent {
        $seenAt ??= now();

        return TelemetryEvent::query()->create([
            'organization_id' => $organization->id,
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', $externalId),
            'event_type' => 'meraki_location',
            'observed_at' => $seenAt,
            'received_at' => $seenAt,
            'raw_payload' => [
                'version' => '3.0',
                'type' => 'BLE',
                'network_id' => 'L_123',
                'client_mac' => $clientMac,
                'client_name' => $clientMac,
                'rssi_records' => [[
                    'apMac' => '00:11:22:33:44:55',
                    'apName' => 'AP compartido',
                    'rssi' => -60,
                ]],
            ],
            'processing_status' => 'pending',
        ]);
    }
}
