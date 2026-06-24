<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('loratrack:evaluate-alerts')->everyTenMinutes()->withoutOverlapping();
Schedule::command('loratrack:manage-telemetry-storage')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('loratrack:prune-meraki-history')->hourly()->onOneServer()->withoutOverlapping();
