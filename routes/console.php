<?php

use App\Scheduling\ScheduledTaskSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$scheduleTask = function (string $task, int $overlapMinutes = 10, bool $background = false): void {
    $event = Schedule::command("loratrack:run-scheduled {$task}")
        ->everyMinute()
        ->when(fn (): bool => app(ScheduledTaskSchedule::class)->isDue($task))
        ->onOneServer()
        ->withoutOverlapping($overlapMinutes);

    if ($background) {
        $event->runInBackground();
    }
};

$scheduleTask('evaluate-alerts');
$scheduleTask('process-meraki-webhooks', 10, true);
$scheduleTask('process-meraki-observations');
$scheduleTask('process-tti-uplinks');
$scheduleTask('process-mqtt-telemetry');
$scheduleTask('process-catalog-syncs');
$scheduleTask('sync-telemetry-counters');
$scheduleTask('manage-telemetry-storage');
