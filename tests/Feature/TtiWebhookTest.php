<?php

namespace Tests\Feature;

use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Jobs\ProcessTtiUplink;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TtiWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_tti_uplink_is_authenticated_and_deduplicated(): void
    {
        Queue::fake();
        $connector = Connector::query()->create([
            'name' => 'TTI',
            'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::TtiWebhook,
            'status' => ConnectorStatus::Active,
            'credentials' => ['webhook_token' => 'a-secure-token-with-24-characters'],
        ]);
        $payload = [
            'end_device_ids' => ['device_id' => 'tracker-01', 'dev_eui' => '0011223344556677'],
            'received_at' => '2026-06-18T20:00:00Z',
            'uplink_message' => [
                'f_cnt' => 42,
                'f_port' => 1,
                'decoded_payload' => ['battery' => 89],
            ],
        ];
        $headers = ['Authorization' => 'Bearer a-secure-token-with-24-characters'];

        $this->postJson(route('api.tti.ingest', $connector), $payload, $headers)
            ->assertAccepted()
            ->assertJsonPath('duplicate', false);
        $this->postJson(route('api.tti.ingest', $connector), $payload, $headers)
            ->assertAccepted()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, TelemetryEvent::query()->count());
        Queue::assertPushed(ProcessTtiUplink::class, 1);
    }

    public function test_tti_uplink_rejects_invalid_token(): void
    {
        $connector = Connector::query()->create([
            'name' => 'TTI',
            'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::TtiWebhook,
            'status' => ConnectorStatus::Active,
            'credentials' => ['webhook_token' => 'a-secure-token-with-24-characters'],
        ]);

        $this->postJson(route('api.tti.ingest', $connector), [])->assertUnauthorized();
    }
}
