<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ScheduledCommandStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledCommandStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_wrapper_records_execution_and_connectors_display_it(): void
    {
        $this->artisan('loratrack:run-scheduled', ['task' => 'sync-telemetry-counters'])
            ->assertSuccessful();

        $status = ScheduledCommandStatus::query()->findOrFail('sync-telemetry-counters');
        $this->assertNotNull($status->last_started_at);
        $this->assertNotNull($status->last_finished_at);
        $this->assertNotNull($status->last_succeeded_at);
        $this->assertSame(0, $status->last_exit_code);
        $this->assertSame(1, $status->run_count);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('connectors.index'))
            ->assertOk()
            ->assertSee('Tareas programadas')
            ->assertSee('Contadores de telemetría')
            ->assertSee('loratrack:sync-telemetry-counters')
            ->assertSee('1 ejecuciones');
    }

    public function test_unknown_scheduled_task_is_rejected(): void
    {
        $this->artisan('loratrack:run-scheduled', ['task' => 'unknown-task'])
            ->assertExitCode(2);

        $this->assertDatabaseCount('scheduled_command_statuses', 0);
    }
}
