<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MerakiLocationWebhookController;
use App\Http\Controllers\Api\TtiWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/ingest/tti/{connector}', TtiWebhookController::class)
    ->middleware('throttle:telemetry')
    ->name('api.tti.ingest');

Route::get('/v1/ingest/meraki/{connector}', [MerakiLocationWebhookController::class, 'validateReceiver'])
    ->middleware('throttle:telemetry')
    ->name('api.meraki.validate');
Route::post('/v1/ingest/meraki/{connector}', [MerakiLocationWebhookController::class, 'receive'])
    ->middleware('throttle:telemetry')
    ->name('api.meraki.ingest');
