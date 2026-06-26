<?php

declare(strict_types=1);

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertRuleController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetDeviceAssignmentController;
use App\Http\Controllers\AssetPhotoController;
use App\Http\Controllers\AssetTrackController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\Auth\RegisteredOrganizationController;
use App\Http\Controllers\CalibrationController;
use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\FloorPlanController;
use App\Http\Controllers\FloorPlanFileController;
use App\Http\Controllers\FloorPlanModelController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MerakiFloorPlanMappingController;
use App\Http\Controllers\OperationalHealthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PayloadDecoderProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInvitationController;
use App\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

Route::get('/favicon.ico', FaviconController::class)->name('favicon');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisteredOrganizationController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredOrganizationController::class, 'store'])->middleware('throttle:registration')->name('registration.store');
    Route::get('/auth/microsoft', [MicrosoftController::class, 'redirect'])->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftController::class, 'callback'])->name('auth.microsoft.callback');
});
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.store');

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::view('/help/devices', 'help.devices')->name('help.devices');
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/{asset}/photo', AssetPhotoController::class)->name('assets.photo');
    Route::get('/assets/{asset}/track', [AssetTrackController::class, 'show'])->name('assets.track');
    Route::get('/assets/{asset}/track/data', [AssetTrackController::class, 'data'])->name('assets.track.data');
    Route::middleware('permission:assets.manage')->group(function (): void {
        Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::get('/assets/device-options', [AssetController::class, 'deviceOptions'])->name('assets.device-options');
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::post('/assets/{asset}/refresh-position', [AssetController::class, 'refreshPosition'])->name('assets.position.refresh');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
        Route::post('/assets/{asset}/assignments', [AssetDeviceAssignmentController::class, 'store'])->name('asset-assignments.store');
        Route::delete('/asset-assignments/{assignment}', [AssetDeviceAssignmentController::class, 'destroy'])->name('asset-assignments.destroy');
    });
    Route::get('/floor-plans', [FloorPlanController::class, 'index'])->name('floor-plans.index');
    Route::get('/floor-plans/{floorPlan}/file', FloorPlanFileController::class)->name('floor-plans.file');
    Route::get('/floor-plans/{floorPlan}/model', FloorPlanModelController::class)->name('floor-plans.model');
    Route::get('/map', [MapController::class, 'index'])->middleware('permission:maps.view')->name('map.index');
    Route::get('/map/{floorPlan}/data', [MapController::class, 'data'])->middleware('permission:maps.view')->name('map.data');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organization/logo', [OrganizationController::class, 'logo'])->name('organizations.logo');
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    Route::middleware('permission:plans.manage')->group(function (): void {
        Route::post('/locations', [FloorPlanController::class, 'storeLocation'])->name('locations.store');
        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::post('/floor-plans', [FloorPlanController::class, 'store'])->name('floor-plans.store');
        Route::put('/floor-plans/{floorPlan}', [FloorPlanController::class, 'update'])->name('floor-plans.update');
        Route::delete('/floor-plans/{floorPlan}', [FloorPlanController::class, 'destroy'])->name('floor-plans.destroy');
        Route::get('/floor-plans/{floorPlan}/installation-device-options', [DeviceController::class, 'installationDeviceOptions'])->name('floor-plans.installation-device-options');
        Route::get('/floor-plans/{floorPlan}/observed-mac-options', [DeviceController::class, 'observedMacOptions'])->name('floor-plans.observed-mac-options');
        Route::post('/floor-plans/{floorPlan}/zones', [ZoneController::class, 'store'])->name('zones.store');
        Route::put('/zones/{zone}', [ZoneController::class, 'update'])->name('zones.update');
        Route::post('/floor-plans/{floorPlan}/installations', [DeviceController::class, 'install'])->name('installations.store');
        Route::put('/installations/{deviceInstallation}', [DeviceController::class, 'updateInstallation'])->name('installations.update');
        Route::get('/floor-plans/{floorPlan}/calibration', [CalibrationController::class, 'index'])->name('calibration.index');
        Route::post('/floor-plans/{floorPlan}/calibration/preview', [CalibrationController::class, 'preview'])->name('calibration.preview');
        Route::post('/calibration-runs/{calibrationRun}/apply', [CalibrationController::class, 'apply'])->name('calibration.apply');
        Route::delete('/installations/{deviceInstallation}', [DeviceController::class, 'removeInstallation'])->name('installations.destroy');
        Route::delete('/zones/{zone}', [ZoneController::class, 'destroy'])->name('zones.destroy');
    });

    Route::middleware('permission:payload_profiles.manage')->group(function (): void {
        Route::get('/payload-profiles', [PayloadDecoderProfileController::class, 'index'])->name('payload-profiles.index');
        Route::post('/payload-profiles', [PayloadDecoderProfileController::class, 'store'])->name('payload-profiles.store');
        Route::put('/payload-profiles/{payloadProfile}', [PayloadDecoderProfileController::class, 'update'])->name('payload-profiles.update');
        Route::delete('/payload-profiles/{payloadProfile}', [PayloadDecoderProfileController::class, 'destroy'])->name('payload-profiles.destroy');
        Route::post('/payload-profiles/{payloadProfile}/preview', [PayloadDecoderProfileController::class, 'preview'])->name('payload-profiles.preview');
    });

    Route::middleware('permission:alerts.manage')->group(function (): void {
        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::put('/alerts', [AlertController::class, 'update'])->name('alerts.update');
        Route::post('/alerts/rules', [AlertRuleController::class, 'store'])->name('alert-rules.store');
        Route::put('/alerts/rules/{alertRule}', [AlertRuleController::class, 'update'])->name('alert-rules.update');
        Route::delete('/alerts/rules/{alertRule}', [AlertRuleController::class, 'destroy'])->name('alert-rules.destroy');
    });
    Route::get('/operations/health', OperationalHealthController::class)->middleware('permission:operations.view')->name('operations.health');

    Route::middleware('admin')->group(function (): void {
        Route::put('/organization', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('/connectors', [ConnectorController::class, 'index'])->name('connectors.index');
        Route::get('/connectors/{connector}', [ConnectorController::class, 'show'])->name('connectors.show');
        Route::get('/connectors/{connector}/events/{telemetryEvent}', [ConnectorController::class, 'showEvent'])->name('connectors.events.show');
        Route::get('/connectors/create/{provider}', [ConnectorController::class, 'create'])->name('connectors.create');
        Route::post('/connectors', [ConnectorController::class, 'store'])->name('connectors.store');
        Route::post('/connectors/{connector}/test', [ConnectorController::class, 'test'])->name('connectors.test');
        Route::post('/connectors/{connector}/toggle', [ConnectorController::class, 'toggle'])->name('connectors.toggle');
        Route::post('/connectors/{connector}/rotate-webhook-token', [ConnectorController::class, 'rotateWebhookToken'])->name('connectors.rotate-webhook-token');
        Route::post('/connectors/{connector}/sync', [ConnectorController::class, 'sync'])->name('connectors.sync');
        Route::post('/connectors/{connector}/csv', [ConnectorController::class, 'importCsv'])->name('connectors.csv');
        Route::post('/connectors/{connector}/meraki-floor-plans', [MerakiFloorPlanMappingController::class, 'store'])->name('connectors.meraki-floor-plans.store');
        Route::delete('/connectors/{connector}/meraki-floor-plans/{mapping}', [MerakiFloorPlanMappingController::class, 'destroy'])->name('connectors.meraki-floor-plans.destroy');
        Route::put('/connectors/{connector}/meraki-credentials', [ConnectorController::class, 'updateMerakiCredentials'])->name('connectors.meraki-credentials.update');
        Route::delete('/connectors/{connector}', [ConnectorController::class, 'destroy'])->name('connectors.destroy');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/invitations', [UserInvitationController::class, 'store'])->middleware('throttle:10,1')->name('user-invitations.store');
        Route::post('/users/invitations/{organizationInvitation}/resend', [UserInvitationController::class, 'resend'])->middleware('throttle:10,1')->name('user-invitations.resend');
        Route::delete('/users/invitations/{organizationInvitation}', [UserInvitationController::class, 'destroy'])->name('user-invitations.destroy');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/memberships', [UserController::class, 'bulkUpdate'])->name('users.memberships.bulk-update');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
