<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Models\ZoneAlertRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZoneAlertRuleController extends Controller
{
    public function store(Request $request, Zone $zone): RedirectResponse
    {
        $data = $request->validate([
            'event_type' => ['required', Rule::in(['entry', 'exit', 'dwell'])],
            'dwell_minutes' => ['nullable', 'required_if:event_type,dwell', 'integer', 'between:10,10080'],
            'recipients' => ['nullable', 'string', 'max:4000'],
        ]);
        $recipients = $this->recipients($data['recipients'] ?? '');

        $zone->alertRules()->updateOrCreate(['event_type' => $data['event_type']], [
            'dwell_minutes' => $data['event_type'] === 'dwell' ? $data['dwell_minutes'] : null,
            'recipients' => $recipients,
            'enabled' => true,
        ]);

        return back()->with('status', 'Regla de zona guardada.');
    }

    public function destroy(ZoneAlertRule $zoneAlertRule): RedirectResponse
    {
        $zoneAlertRule->delete();

        return back()->with('status', 'Regla de zona eliminada.');
    }

    /** @return list<string> */
    public static function recipients(string $value): array
    {
        return collect(preg_split('/[,;\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email) => mb_strtolower($email))->unique()->values()
            ->each(fn (string $email) => abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 422, "Correo inválido: {$email}"))
            ->all();
    }
}
