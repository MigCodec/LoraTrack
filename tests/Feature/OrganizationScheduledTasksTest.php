<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\OrganizationScheduledTask;
use App\Models\User;
use App\Scheduling\OrganizationTaskScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_only_the_active_organization_schedule(): void
    {
        [$admin, $first, $second] = $this->organizations();
        $scheduler = app(OrganizationTaskScheduler::class);
        $scheduler->synchronize($first);
        $scheduler->synchronize($second);

        $this->actingAs($admin)->withSession(['organization_id' => $first->id])
            ->put(route('settings.scheduled-tasks.update', 'process-meraki-webhooks'), [
                'enabled' => 1,
                'interval_minutes' => 4,
            ])->assertRedirect();

        $this->assertDatabaseHas('organization_scheduled_tasks', [
            'organization_id' => $first->id,
            'task' => 'process-meraki-webhooks',
            'enabled' => 1,
            'interval_minutes' => 4,
        ]);
        $this->assertDatabaseHas('organization_scheduled_tasks', [
            'organization_id' => $second->id,
            'task' => 'process-meraki-webhooks',
            'interval_minutes' => 1,
        ]);
    }

    public function test_execute_now_runs_whitelisted_task_and_records_result(): void
    {
        [$admin, $organization] = $this->organizations();

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->post(route('settings.scheduled-tasks.run', 'sync-telemetry-counters'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $task = OrganizationScheduledTask::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('task', 'sync-telemetry-counters')
            ->firstOrFail();
        $this->assertSame(1, $task->run_count);
        $this->assertSame(0, $task->last_exit_code);
        $this->assertNotNull($task->last_finished_at);
    }

    public function test_settings_displays_registered_commands_and_controls(): void
    {
        [$admin, $organization] = $this->organizations();

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Centro de automatización')
            ->assertSee('loratrack:process-meraki-webhooks')
            ->assertSee('Ejecutar ahora');
    }

    /** @return array{User, Organization, Organization} */
    private function organizations(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $first = Organization::query()->create(['name' => 'Primera', 'slug' => 'scheduler-primera']);
        $second = Organization::query()->create(['name' => 'Segunda', 'slug' => 'scheduler-segunda']);
        $first->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $second->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);

        return [$admin, $first, $second];
    }
}
