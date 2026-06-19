<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Connector;
use App\Models\Device;
use App\Models\PositionEstimate;
use App\Models\Product;
use App\Models\TelemetryEvent;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();

        return view('dashboard', [
            'role' => $user->effectiveRole(),
            'metrics' => [
                'products' => Product::query()->count(),
                'assets' => Asset::query()->count(),
                'devices' => Device::query()->count(),
                'activeConnectors' => Connector::query()->where('status', 'active')->count(),
                'eventsToday' => TelemetryEvent::query()->where('received_at', '>=', now()->startOfDay())->count(),
            ],
            'recentPositions' => PositionEstimate::query()->with(['asset', 'zone'])->latest('calculated_at')->limit(8)->get(),
            'connectorsWithErrors' => $user->hasPermission('operations.view')
                ? Connector::query()->whereNotNull('last_error')->latest('updated_at')->limit(5)->get()
                : collect(),
        ]);
    }
}
