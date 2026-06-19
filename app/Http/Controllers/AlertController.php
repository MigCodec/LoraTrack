<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(): View
    {
        return view('alerts.index', ['settings' => AlertSetting::current(), 'alerts' => Alert::query()->latest('detected_at')->limit(100)->get()]);
    }

    public function update(Request $r): RedirectResponse
    {
        $d = $r->validate(['enabled' => ['nullable', 'boolean'], 'recipients' => ['nullable', 'string'], 'offline_minutes' => ['required', 'integer', 'between:5,10080'], 'minimum_confidence' => ['required', 'numeric', 'between:0,1'], 'enabled_types' => ['array'], 'enabled_types.*' => ['in:device_offline,connector_error,low_confidence']]);
        $emails = collect(preg_split('/[,;\s]+/', $d['recipients'] ?? '', -1, PREG_SPLIT_NO_EMPTY))->map(fn ($e) => mb_strtolower($e))->unique()->values();
        foreach ($emails as $e) {
            abort_unless(filter_var($e, FILTER_VALIDATE_EMAIL), 422, "Correo inválido: {$e}");
        }AlertSetting::current()->update(['enabled' => $r->boolean('enabled'), 'recipients' => $emails->all(), 'offline_minutes' => $d['offline_minutes'], 'minimum_confidence' => $d['minimum_confidence'], 'enabled_types' => $d['enabled_types'] ?? []]);

        return back()->with('status', 'Configuración actualizada.');
    }
}
