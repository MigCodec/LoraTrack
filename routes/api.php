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
Route::get('/v1/ingest/meraki/custom/{webhookPath}', [MerakiLocationWebhookController::class, 'validateCustomReceiver'])
    ->where('webhookPath', '[A-Za-z0-9._-]+')
    ->middleware('throttle:telemetry')
    ->name('api.meraki.validate.custom');
Route::post('/v1/ingest/meraki/custom/{webhookPath}', [MerakiLocationWebhookController::class, 'receiveCustom'])
    ->where('webhookPath', '[A-Za-z0-9._-]+')
    ->middleware('throttle:telemetry')
    ->name('api.meraki.ingest.custom');
