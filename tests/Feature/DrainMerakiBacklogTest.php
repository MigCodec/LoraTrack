<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\MerakiWebhookBatch;
use App\Models\Organization;
use App\Models\TelemetryEvent;
use App\Support\ArtisanProcessResult;
use App\Support\IsolatedArtisanCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class DrainMerakiBacklogTest extends TestCase
{
    use RefreshDatabase;

    public function test_vm_command_drains_batches_then_observations_and_synchronizes_counters(): void
    {
        $organization = Organization::query()->create(['name' => 'VM Drain', 'slug' => 'vm-drain']);
        $connector = Connector::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Meraki VM',
            'kind' => 'telemetry',
            'provider' => 'meraki_location',
            'status' => 'active',
        ]);
        $batch = MerakiWebhookBatch::query()->create([
            'organization_id' => $organization->id,
            'connector_id' => $connector->id,
            'request_hash' => hash('sha256', 'vm-drain-batch'),
            'payload' => ['version' => '3.0'],
            'processing_status' => 'pending',
            'attempts' => 0,
            'received_at' => now(),
        ]);
        $event = TelemetryEvent::query()->create([
            'organization_id' => $organization->id,
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', 'vm-drain-event'),
            'event_type' => 'meraki_location',
            'received_at' => now(),
            'raw_payload' => [],
            'processing_status' => 'processing',
        ]);

        $commands = [];
        $this->mock(IsolatedArtisanCommandRunner::class, function (MockInterface $mock) use (&$commands, $batch, $event): void {
            $mock->shouldReceive('run')->times(3)->andReturnUsing(
                function (string $command, array $arguments, string $memory, int $timeout, ?callable $outputCallback) use (&$commands, $batch, $event): ArtisanProcessResult {
                    $commands[] = [$command, $arguments, $memory, $timeout];
                    if ($command === 'loratrack:process-meraki-webhooks') {
                        $batch->delete();
                    }
                    if ($command === 'loratrack:process-meraki-observations') {
                        $event->forceFill(['processing_status' => 'processed', 'processed_at' => now()])->saveQuietly();
                    }

                    return new ArtisanProcessResult(0, "{$command} finalizado");
                },
            );
        });

        $this->artisan('loratrack:drain-meraki-backlog', [
            '--observation-batch' => 1000,
            '--memory' => '512M',
            '--child-timeout' => 900,
        ])->assertSuccessful();

        $this->assertSame([
            'loratrack:process-meraki-webhooks',
            'loratrack:process-meraki-observations',
            'loratrack:sync-telemetry-counters',
        ], array_column($commands, 0));
        $this->assertSame(['--limit' => 1], $commands[0][1]);
        $this->assertSame(['--limit' => 1000], $commands[1][1]);
        $this->assertSame('512M', $commands[0][2]);
        $this->assertSame(900, $commands[0][3]);
        $this->assertDatabaseMissing('meraki_webhook_batches', ['id' => $batch->id]);
        $this->assertDatabaseHas('telemetry_events', ['id' => $event->id, 'processing_status' => 'processed']);
    }

    public function test_vm_command_rejects_unsafe_memory_limit(): void
    {
        $this->artisan('loratrack:drain-meraki-backlog', ['--memory' => '-1'])
            ->expectsOutputToContain('--memory debe usar un valor permitido')
            ->assertExitCode(2);
    }

    public function test_vm_command_can_stream_child_output_to_the_console(): void
    {
        $this->mock(IsolatedArtisanCommandRunner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')->once()->andReturnUsing(
                function (string $command, array $arguments, string $memory, int $timeout, ?callable $outputCallback): ArtisanProcessResult {
                    $this->assertNotNull($outputCallback);
                    $outputCallback('out', "Contadores sincronizados en vivo.\n");

                    return new ArtisanProcessResult(0, 'Contadores sincronizados en vivo.');
                },
            );
        });

        $this->artisan('loratrack:drain-meraki-backlog', ['--live-output' => true])
            ->expectsOutputToContain('Contadores sincronizados en vivo.')
            ->assertSuccessful();
    }
}
