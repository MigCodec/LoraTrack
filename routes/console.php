<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('loratrack:evaluate-alerts')->everyTenMinutes()->withoutOverlapping();
Schedule::command('loratrack:process-meraki-webhooks --limit=3')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(10)
    ->runInBackground();
Schedule::command('loratrack:process-meraki-observations')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:process-tti-uplinks')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:process-mqtt-telemetry')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:process-catalog-syncs')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:sync-telemetry-counters')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:manage-telemetry-storage')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:prune-meraki-history')->hourly()->onOneServer()->withoutOverlapping();
