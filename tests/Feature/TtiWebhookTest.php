<?php

namespace Tests\Feature;

use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Jobs\ProcessTtiUplink;
use App\Models\Connector;
use App\Models\Device;
use App\Models\TelemetryEvent;
use App\Positioning\BleObservationExtractor;
use App\Positioning\PayloadProfileDecoder;
use App\Positioning\TelemetryPositioningService;
use App\Telemetry\AssetLastSeenUpdater;
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
        $this->assertSame('2026-06-18 16:00:00', TelemetryEvent::query()->firstOrFail()->observed_at->format('Y-m-d H:i:s'));
        Queue::assertNotPushed(ProcessTtiUplink::class);
        $this->assertSame('pending', TelemetryEvent::query()->firstOrFail()->processing_status);
        $this->artisan('loratrack:process-tti-uplinks')->assertSuccessful();
        $this->assertSame('processed', TelemetryEvent::query()->firstOrFail()->processing_status);
    }

    public function test_tti_uplink_identity_uses_nested_received_at(): void
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
            'uplink_message' => [
                'f_cnt' => 42,
                'f_port' => 1,
                'received_at' => '2026-07-10T11:18:02Z',
                'decoded_payload' => ['battery' => 89],
            ],
        ];
        $headers = ['Authorization' => 'Bearer a-secure-token-with-24-characters'];

        $this->postJson(route('api.tti.ingest', $connector), $payload, $headers)
            ->assertAccepted()
            ->assertJsonPath('duplicate', false);

        $payload['uplink_message']['received_at'] = '2026-07-10T11:24:49Z';
        $this->postJson(route('api.tti.ingest', $connector), $payload, $headers)
            ->assertAccepted()
            ->assertJsonPath('duplicate', false);

        $this->assertSame(2, TelemetryEvent::query()->count());
        Queue::assertNotPushed(ProcessTtiUplink::class);
        $this->artisan('loratrack:process-tti-uplinks')->assertSuccessful();
        $this->assertSame(2, TelemetryEvent::query()->where('processing_status', 'processed')->count());
    }

    public function test_tti_scheduler_processes_at_most_three_uplinks_per_execution(): void
    {
        Queue::fake();
        $connector = Connector::query()->create([
            'name' => 'TTI',
            'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::TtiWebhook,
            'status' => ConnectorStatus::Active,
            'credentials' => ['webhook_token' => 'a-secure-token-with-24-characters'],
        ]);
        $headers = ['Authorization' => 'Bearer a-secure-token-with-24-characters'];

        foreach (range(1, 4) as $counter) {
            $this->postJson(route('api.tti.ingest', $connector), [
                'end_device_ids' => ['device_id' => 'tracker-'.$counter, 'dev_eui' => str_pad((string) $counter, 16, '0', STR_PAD_LEFT)],
                'received_at' => "2026-07-20T18:00:0{$counter}Z",
                'uplink_message' => [
                    'f_cnt' => $counter,
                    'f_port' => 1,
                    'decoded_payload' => ['battery' => 90],
                ],
            ], $headers)->assertAccepted();
        }

        $this->artisan('loratrack:process-tti-uplinks')->assertSuccessful();

        $this->assertSame(3, TelemetryEvent::query()->where('processing_status', 'processed')->count());
        $this->assertSame(1, TelemetryEvent::query()->where('processing_status', 'pending')->count());
        Queue::assertNotPushed(ProcessTtiUplink::class);
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

    public function test_disabled_tti_connector_does_not_process_queued_telemetry(): void
    {
        $connector = Connector::query()->create([
            'name' => 'TTI desactivado',
            'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::TtiWebhook,
            'status' => ConnectorStatus::Disabled,
        ]);
        $event = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', 'disabled-tti-event'),
            'event_type' => 'uplink',
            'received_at' => now(),
            'raw_payload' => [
                'end_device_ids' => ['device_id' => 'tracker-disabled', 'dev_eui' => '0011223344556677'],
                'uplink_message' => ['decoded_payload' => ['battery' => 90]],
            ],
            'processing_status' => 'pending',
        ]);

        (new ProcessTtiUplink($event->id))->handle(
            app(BleObservationExtractor::class),
            app(PayloadProfileDecoder::class),
            app(TelemetryPositioningService::class),
            app(AssetLastSeenUpdater::class),
        );

        $this->assertSame('ignored', $event->fresh()->processing_status);
        $this->assertSame(0, Device::query()->where('identifier', '0011223344556677')->count());
        $this->assertSame(0, $event->fresh()->signalObservations()->count());
    }
}
