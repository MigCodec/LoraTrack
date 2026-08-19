<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('loratrack:run-scheduled evaluate-alerts')->everyTenMinutes()->withoutOverlapping();
Schedule::command('loratrack:run-scheduled process-meraki-webhooks')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(10)
    ->runInBackground();
Schedule::command('loratrack:run-scheduled process-meraki-observations')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:run-scheduled process-tti-uplinks')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:run-scheduled process-mqtt-telemetry')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:run-scheduled process-catalog-syncs')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:run-scheduled sync-telemetry-counters')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:run-scheduled manage-telemetry-storage')->everyTenMinutes()->onOneServer()->withoutOverlapping();
