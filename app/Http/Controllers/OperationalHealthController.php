<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Connector;
use App\Models\Device;
use App\Models\FloorPlan;
use App\Models\TelemetryEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OperationalHealthController extends Controller
{
    public function __invoke(): View
    {
        $failedTelemetry = TelemetryEvent::query()->where('processing_status', 'failed')->where('received_at', '>=', now()->subDay())->count();
        $stuckTelemetry = TelemetryEvent::query()->where('processing_status', 'pending')->where('received_at', '<', now()->subMinutes(5))->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $pendingJobs = DB::table('jobs')->count();

        $plans = FloorPlan::query()->with(['location'])->where('is_active', true)->get()->map(function (FloorPlan $plan): array {
            $base = DB::table('device_installations')->join('devices', 'devices.id', '=', 'device_installations.device_id')->where('device_installations.floor_plan_id', $plan->id)->whereNull('device_installations.ended_at');

            return [
                'plan' => $plan,
                'beacons' => (clone $base)->where('devices.type', 'beacon')->count(),
                'scanners' => (clone $base)->where('devices.type', 'scanner')->count(),
                'file_ok' => $plan->isThreeDimensional()
                    ? Storage::disk($plan->disk)->exists($plan->file_path)
                    : $plan->drawablePath() !== null && Storage::disk($plan->disk)->exists($plan->drawablePath()),
            ];
        });

        return view('operations.health', [
            'checks' => [
                ['name' => 'Telemetría fallida (24 h)', 'value' => $failedTelemetry, 'ok' => $failedTelemetry === 0, 'detail' => 'Eventos que agotaron el procesamiento.'],
                ['name' => 'Telemetría pendiente > 5 min', 'value' => $stuckTelemetry, 'ok' => $stuckTelemetry === 0, 'detail' => 'Indica que el worker no está avanzando.'],
                ['name' => 'Trabajos en cola', 'value' => $pendingJobs, 'ok' => $pendingJobs < 100, 'detail' => 'Pendientes en la cola de Laravel.'],
                ['name' => 'Trabajos fallidos', 'value' => $failedJobs, 'ok' => $failedJobs === 0, 'detail' => 'Requieren inspección y reintento.'],
            ],
            'plans' => $plans,
            'connectors' => Connector::query()->orderBy('name')->get(),
            'lastTelemetryAt' => TelemetryEvent::query()->max('received_at'),
            'auditLogs' => request()->user()->isAdmin() ? AuditLog::query()->with('user')->latest()->limit(30)->get() : collect(),
            'pendingScanners' => Device::query()
                ->where('type', 'scanner')
                ->whereDoesntHave('installations', fn ($query) => $query->whereNull('ended_at'))
                ->latest('last_seen_at')
                ->limit(100)
                ->get(),
        ]);
    }
}
