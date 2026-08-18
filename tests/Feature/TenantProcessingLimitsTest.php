<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\Organization;
use App\Models\TelemetryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProcessingLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_meraki_observation_scheduler_applies_each_tenant_limit(): void
    {
        $first = Organization::query()->create([
            'name' => 'Primera',
            'slug' => 'limite-primera',
            'meraki_observation_limit' => 1,
        ]);
        $second = Organization::query()->create([
            'name' => 'Segunda',
            'slug' => 'limite-segunda',
            'meraki_observation_limit' => 2,
        ]);
        $firstConnector = $this->connector($first);
        $secondConnector = $this->connector($second);

        foreach (range(1, 3) as $index) {
            $this->invalidPendingEvent($firstConnector, 'first-'.$index);
            $this->invalidPendingEvent($secondConnector, 'second-'.$index);
        }

        $this->artisan('loratrack:process-meraki-observations')
            ->expectsOutputToContain('fallidas: 3')
            ->assertFailed();

        $this->assertSame(1, TelemetryEvent::query()->withoutGlobalScopes()
            ->where('organization_id', $first->id)->where('processing_status', 'failed')->count());
        $this->assertSame(2, TelemetryEvent::query()->withoutGlobalScopes()
            ->where('organization_id', $second->id)->where('processing_status', 'failed')->count());
        $this->assertSame(2, TelemetryEvent::query()->withoutGlobalScopes()
            ->where('organization_id', $first->id)->where('processing_status', 'pending')->count());
        $this->assertSame(1, TelemetryEvent::query()->withoutGlobalScopes()
            ->where('organization_id', $second->id)->where('processing_status', 'pending')->count());
    }

    public function test_scheduler_does_not_override_meraki_webhook_tenant_limit(): void
    {
        $this->assertArrayNotHasKey('arguments', config('scheduled-tasks.process-meraki-webhooks'));
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

    private function invalidPendingEvent(Connector $connector, string $externalId): void
    {
        TelemetryEvent::query()->create([
            'organization_id' => $connector->organization_id,
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', $externalId),
            'event_type' => 'meraki_location',
            'observed_at' => now(),
            'received_at' => now(),
            'raw_payload' => ['client_mac' => 'invalid'],
            'processing_status' => 'pending',
        ]);
    }
}
