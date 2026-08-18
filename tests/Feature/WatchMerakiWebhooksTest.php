<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchMerakiWebhooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_watch_command_can_run_one_visible_processing_cycle(): void
    {
        $this->artisan('loratrack:watch-meraki-webhooks', [
            '--limit' => 7,
            '--once' => true,
        ])
            ->expectsOutputToContain('Monitor de webhooks Meraki iniciado')
            ->expectsOutputToContain('loratrack:process-meraki-webhooks --limit=7')
            ->expectsOutputToContain('Lotes Meraki procesados: 0')
            ->expectsOutputToContain('Ciclo finalizado correctamente')
            ->assertSuccessful();
    }

    public function test_watch_command_rejects_an_unsafe_interval(): void
    {
        $this->artisan('loratrack:watch-meraki-webhooks', ['--interval' => 0, '--once' => true])
            ->expectsOutputToContain('--interval debe ser un entero entre 1 y 3600')
            ->assertExitCode(2);
    }
}
