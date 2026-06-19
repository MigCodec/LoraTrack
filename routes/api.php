<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TtiWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/ingest/tti/{connector}', TtiWebhookController::class)
    ->middleware('throttle:telemetry')
    ->name('api.tti.ingest');
