<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOperationalSettingsRequest;
use App\Scheduling\OrganizationTaskScheduler;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(OrganizationContext $context, OrganizationTaskScheduler $scheduler): View
    {
        $organization = $context->organization();
        abort_unless($organization, 404);
        $scheduledTasks = $scheduler->synchronize($organization)
            ->map(function ($scheduledTask): array {
                $definition = config("scheduled-tasks.{$scheduledTask->task}");
                $running = $scheduledTask->last_started_at
                    && (! $scheduledTask->last_finished_at || $scheduledTask->last_started_at->gt($scheduledTask->last_finished_at));
                $failed = $scheduledTask->last_failed_at
                    && (! $scheduledTask->last_succeeded_at || $scheduledTask->last_failed_at->gt($scheduledTask->last_succeeded_at));

                return [
                    'record' => $scheduledTask,
                    'definition' => $definition,
                    'state' => ! $scheduledTask->enabled ? 'disabled' : ($running ? 'running' : ($failed ? 'failed' : ($scheduledTask->run_count > 0 ? 'healthy' : 'never'))),
                ];
            });

        return view('settings.index', compact('organization', 'scheduledTasks'));
    }

    public function update(UpdateOperationalSettingsRequest $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        abort_unless($organization, 404);
        $data = $request->validated();
        $data['storage_cleanup_enabled'] = $request->boolean('storage_cleanup_enabled');
        $organization->update($data);

        return back()->with('status', 'Configuracion operativa actualizada.');
    }
}
