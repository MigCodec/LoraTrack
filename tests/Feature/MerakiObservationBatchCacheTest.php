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

    public function test_command_reuses_inventory_cache_and_groups_connector_updates(): void
    {
        $organization = Organization::query()->create(['name' => 'Cache AP', 'slug' => 'cache-ap']);
        $connector = $this->connector($organization);
        $firstSeenAt = now()->subMinute();
        $lastSeenAt = now();
        $this->event($organization, $connector, 'AABBCCDDEE01', 'event-one', $firstSeenAt);
        $this->event($organization, $connector, 'AABBCCDDEE01', 'event-two', $lastSeenAt);

        $accessPointSelects = 0;
        $clientSelects = 0;
        $connectorUpdates = 0;
        DB::listen(function (QueryExecuted $query) use (&$accessPointSelects, &$clientSelects, &$connectorUpdates): void {
            $sql = mb_strtolower(ltrim($query->sql));
            if (str_starts_with($sql, 'select') && in_array('001122334455', $query->bindings, true)) {
                $accessPointSelects++;
            }
            if (str_starts_with($sql, 'select') && in_array('AABBCCDDEE01', $query->bindings, true)) {
                $clientSelects++;
            }
            if (str_starts_with($sql, 'update') && str_contains($sql, 'connectors')) {
                $connectorUpdates++;
            }
        });

        $this->artisan('loratrack:process-meraki-observations', ['--limit' => 2])->assertSuccessful();

        $this->assertSame(1, $accessPointSelects);
        $this->assertSame(1, $clientSelects);
        $this->assertSame(1, $connectorUpdates);
        $this->assertSame(2, TelemetryEvent::query()->where('processing_status', 'processed')->count());
        $this->assertTrue(
            Device::query()->where('identifier', '001122334455')->firstOrFail()->last_seen_at->equalTo($lastSeenAt),
        );
    }

    public function test_shared_cache_is_isolated_by_organization(): void
    {
        $first = Organization::query()->create(['name' => 'Empresa A', 'slug' => 'empresa-a']);
        $second = Organization::query()->create(['name' => 'Empresa B', 'slug' => 'empresa-b']);
        $this->event($first, $this->connector($first), 'AABBCCDDEE01', 'event-a');
        $this->event($second, $this->connector($second), 'AABBCCDDEE02', 'event-b');

        $this->artisan('loratrack:process-meraki-observations', ['--limit' => 2])->assertSuccessful();

        $accessPoints = Device::query()->where('identifier', '001122334455')->get();
        $this->assertCount(2, $accessPoints);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $accessPoints->pluck('organization_id')->all());
    }

    public function test_profile_option_renders_query_and_timing_diagnostics(): void
    {
        $organization = Organization::query()->create(['name' => 'Perfil', 'slug' => 'perfil']);
        $this->event($organization, $this->connector($organization), 'AABBCCDDEE03', 'profile-event');

        $this->artisan('loratrack:process-meraki-observations', ['--limit' => 1, '--profile' => true])
            ->expectsOutputToContain('Perfil: seleccionando IDs pendientes...')
            ->expectsOutputToContain('1 IDs seleccionados')
            ->expectsOutputToContain('procesando evento')
            ->expectsOutputToContain('Perfil de rendimiento')
            ->expectsOutputToContain('Consultas SQL')
            ->expectsOutputToContain('Tiempo total')
            ->assertSuccessful();
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
    ): void {
        $seenAt ??= now();
        TelemetryEvent::query()->create([
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
