<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ScheduledCommandStatus;
use App\Models\SchedulerSetting;
use App\Models\User;
use App\Scheduling\ScheduledTaskSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScheduledCommandStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_wrapper_records_execution_and_settings_display_it(): void
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
        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Automatizaciones del sistema')
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

    public function test_admin_can_update_the_real_task_interval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $intervals = $this->defaultIntervals();
        $intervals['process-meraki-observations'] = 7;

        $this->actingAs($admin)->put(route('settings.automation.update'), [
            'intervals' => $intervals,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('scheduled_command_statuses', [
            'task' => 'process-meraki-observations',
            'interval_minutes' => 7,
        ]);

        $this->assertDatabaseHas('scheduler_settings', ['id' => 1, 'use_system_recommended' => false]);

        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('value="7"', false)
            ->assertSee('Cada 7 minutos');
    }

    public function test_task_is_only_due_after_its_configured_interval(): void
    {
        Carbon::setTestNow('2026-08-19 12:00:00');
        ScheduledCommandStatus::query()->create([
            'task' => 'sync-telemetry-counters',
            'interval_minutes' => 15,
            'last_started_at' => now()->subMinutes(14),
        ]);
        SchedulerSetting::query()->create(['id' => 1, 'use_system_recommended' => false]);

        $schedule = app(ScheduledTaskSchedule::class);
        $this->assertFalse($schedule->isDue('sync-telemetry-counters'));

        Carbon::setTestNow(now()->addMinute());
        $this->assertTrue($schedule->isDue('sync-telemetry-counters'));
        Carbon::setTestNow();
    }

    public function test_recommended_mode_overrides_and_locks_manual_intervals(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        ScheduledCommandStatus::query()->create([
            'task' => 'sync-telemetry-counters',
            'interval_minutes' => 99,
        ]);

        $this->actingAs($admin)->put(route('settings.automation.update'), [
            'use_system_recommended' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(5, app(ScheduledTaskSchedule::class)->intervalMinutes('sync-telemetry-counters'));
        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('recomendado por el sistema')
            ->assertSee('name="use_system_recommended" value="1" data-recommended-schedule checked', false)
            ->assertSee('value="5"', false)
            ->assertSee('disabled', false);
    }

    public function test_ajax_force_runs_immediately_without_waiting_for_scheduler(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->postJson(route('settings.automation.run', 'sync-telemetry-counters'))
            ->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('successful', true)
            ->assertJsonPath('run_count', 1)
            ->assertJsonPath('exit_code', 0);

        $status = ScheduledCommandStatus::query()->findOrFail('sync-telemetry-counters');
        $this->assertSame(1, $status->run_count);
        $this->assertNotNull($status->last_finished_at);
    }

    public function test_force_execution_does_not_overlap_an_already_running_task(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $lock = Cache::lock('loratrack:scheduled-task:sync-telemetry-counters', 60);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($admin)->postJson(route('settings.automation.run', 'sync-telemetry-counters'))
                ->assertConflict()
                ->assertJsonPath('completed', false);
            $this->assertDatabaseMissing('scheduled_command_statuses', ['task' => 'sync-telemetry-counters']);
        } finally {
            $lock->release();
        }
    }

    public function test_invalid_frequency_and_unknown_forced_task_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $intervals = $this->defaultIntervals();
        $intervals['process-meraki-observations'] = 0;

        $this->actingAs($admin)->from(route('settings.index'))
            ->put(route('settings.automation.update'), ['intervals' => $intervals])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('intervals.process-meraki-observations');

        $this->actingAs($admin)
            ->post(route('settings.automation.run', 'unknown-task'))
            ->assertNotFound();
    }

    public function test_non_admin_cannot_view_or_change_automation_settings(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);

        $this->actingAs($operator)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($operator)->put(route('settings.automation.update'), [
            'intervals' => $this->defaultIntervals(),
        ])->assertForbidden();
        $this->actingAs($operator)->post(route('settings.automation.run', 'sync-telemetry-counters'))->assertForbidden();
    }

    private function defaultIntervals(): array
    {
        return collect(config('scheduled-tasks'))
            ->mapWithKeys(fn (array $definition, string $task): array => [$task => $definition['interval_minutes']])
            ->all();
    }
}
