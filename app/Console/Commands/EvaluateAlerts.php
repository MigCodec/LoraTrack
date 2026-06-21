<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\AlertSetting;
use App\Models\Asset;
use App\Models\Connector;
use App\Models\Device;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PositionEstimate;
use App\Models\Zone;
use App\Models\ZonePresenceState;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EvaluateAlerts extends Command
{
    protected $signature = 'loratrack:evaluate-alerts';

    protected $description = 'Evalúa condiciones y envía el resumen de alertas';

    public function handle(): int
    {
        $context = app(OrganizationContext::class);
        $organizations = Organization::query()->where('active', true)->get();
        if ($organizations->isEmpty()) {
            return $this->evaluateOrganization();
        }
        foreach ($organizations as $organization) {
            $context->set($organization);
            $this->evaluateOrganization();
        }
        $context->set(null);

        return self::SUCCESS;
    }

    private function evaluateOrganization(): int
    {
        $settings = AlertSetting::current();
        if (! $settings->enabled) {
            return self::SUCCESS;
        }

        $active = [];
        $types = $settings->enabled_types ?? [];
        if (in_array('device_offline', $types, true)) {
            foreach (Device::query()->where('status', 'active')->whereIn('type', ['scanner', 'lorawan_tracker'])->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes($settings->offline_minutes)))->get() as $device) {
                $active[] = $this->open('device:'.$device->id, 'device_offline', 'Dispositivo sin señal', "{$device->name} no reporta dentro de {$settings->offline_minutes} minutos.", ['device_id' => $device->id]);
            }
        }
        if (in_array('connector_error', $types, true)) {
            foreach (Connector::query()->whereNotNull('last_error')->get() as $connector) {
                $active[] = $this->open('connector:'.$connector->id, 'connector_error', 'Conector con error', "{$connector->name}: {$connector->last_error}", ['connector_id' => $connector->id]);
            }
        }
        if (in_array('low_confidence', $types, true)) {
            foreach (PositionEstimate::query()->with('asset')->where('calculated_at', '>=', now()->subMinutes(10))->where('confidence', '<', $settings->minimum_confidence)->get() as $position) {
                $active[] = $this->open('confidence:'.$position->asset_id, 'low_confidence', 'Posición con baja confianza', "{$position->asset->name}: ".round((float) $position->confidence * 100).'%', ['asset_id' => $position->asset_id]);
            }
        }
        $active = [...$active, ...$this->evaluateFlexibleRules()];
        $active = [...$active, ...$this->evaluateZoneRules()];

        Alert::query()->whereNull('resolved_at')->whereNotIn('fingerprint', $active)->update(['resolved_at' => now()]);
        $pending = Alert::query()->whereNull('resolved_at')->whereNull('notified_at')->get();
        foreach ($pending->groupBy(function (Alert $alert) use ($settings): string {
            $ruleRecipients = $alert->context['recipients'] ?? [];

            return implode(',', ! empty($ruleRecipients) ? $ruleRecipients : ($settings->recipients ?? []));
        }) as $recipientList => $alerts) {
            $recipients = array_values(array_filter(explode(',', $recipientList)));
            if (empty($recipients)) {
                continue;
            }
            $body = "Alertas LoraTrack\n\n".$alerts->map(fn (Alert $alert) => "[{$alert->severity}] {$alert->title}\n{$alert->message}")->join("\n\n");
            Mail::raw($body, fn ($message) => $message->to($recipients)->subject('Alertas LoraTrack · '.$alerts->count()));
            Alert::query()->whereKey($alerts->pluck('id'))->update(['notified_at' => now()]);
        }
        $this->info($pending->count().' alertas nuevas evaluadas.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function evaluateZoneRules(): array
    {
        $active = [];
        $assets = Asset::query()->with('latestPosition')->get();

        foreach (Zone::query()->with('alertRules')->whereHas('alertRules', fn ($query) => $query->where('enabled', true))->get() as $zone) {
            foreach ($assets as $asset) {
                $position = $asset->latestPosition;
                if (! $position) {
                    continue;
                }
                $state = ZonePresenceState::query()->firstOrNew(['zone_id' => $zone->id, 'asset_id' => $asset->id]);
                $wasInside = $state->exists && $state->is_inside;
                $isInside = $position->floor_plan_id === $zone->floor_plan_id && $position->zone_id === $zone->id;
                $isNewPosition = $state->last_position_estimate_id !== $position->id;

                foreach ($zone->alertRules->where('enabled', true) as $rule) {
                    $triggered = ($rule->event_type === 'entry' && $isNewPosition && ! $wasInside && $isInside)
                        || ($rule->event_type === 'exit' && $isNewPosition && $wasInside && ! $isInside)
                        || ($rule->event_type === 'dwell' && $isInside && $wasInside && $state->entered_at?->lte(now()->subMinutes($rule->dwell_minutes)) && ! $state->dwell_notified_at);
                    if (! $triggered) {
                        continue;
                    }

                    $labels = ['entry' => 'Entrada a zona', 'exit' => 'Salida de zona', 'dwell' => 'Permanencia excedida'];
                    $fingerprint = "zone:{$rule->event_type}:{$rule->id}:{$asset->id}:".($rule->event_type === 'dwell' ? $state->entered_at?->timestamp : $position->id);
                    $active[] = $this->open($fingerprint, 'zone_'.$rule->event_type, $labels[$rule->event_type], "{$asset->name} · {$zone->name}".($rule->event_type === 'dwell' ? " · más de {$rule->dwell_minutes} minutos" : ''), [
                        'asset_id' => $asset->id, 'zone_id' => $zone->id, 'rule_id' => $rule->id, 'recipients' => $rule->recipients,
                    ]);
                    if ($rule->event_type === 'dwell') {
                        $state->dwell_notified_at = now();
                    }
                }

                if (! $wasInside && $isInside) {
                    $state->entered_at = $position->calculated_at ?? now();
                    $state->dwell_notified_at = null;
                } elseif ($wasInside && ! $isInside) {
                    $state->entered_at = null;
                    $state->dwell_notified_at = null;
                }
                $state->fill(['is_inside' => $isInside, 'last_evaluated_at' => now(), 'last_position_estimate_id' => $position->id])->save();
            }
        }

        return $active;
    }

    /** @return list<string> */
    private function evaluateFlexibleRules(): array
    {
        $active = [];
        $rules = AlertRule::query()->where('enabled', true)->get();
        $assets = Asset::query()->with('latestPosition')->get();

        foreach ($rules->whereIn('trigger_type', ['speed_above', 'speed_below']) as $rule) {
            foreach ($this->subjects($assets, $rule) as $asset) {
                $position = $asset->latestPosition;
                $state = $position?->filter_state ?? [];
                $speed = hypot((float) ($state['vx'] ?? 0), (float) ($state['vy'] ?? 0)) * 3.6;
                $triggered = $position && ($rule->trigger_type === 'speed_above' ? $speed > $rule->threshold : $speed < $rule->threshold);
                if ($triggered) {
                    $active[] = $this->openRule($rule, $asset, 'Velocidad: '.round($speed, 1).' km/h', $position->id);
                }
            }
        }

        foreach ($rules->whereIn('trigger_type', ['zone_entry', 'zone_exit', 'zone_inside', 'zone_outside'])->groupBy('zone_id') as $zoneId => $zoneRules) {
            $zone = Zone::query()->find($zoneId);
            if (! $zone) {
                continue;
            }
            foreach ($assets as $asset) {
                $position = $asset->latestPosition;
                if (! $position) {
                    continue;
                }
                $state = ZonePresenceState::query()->firstOrNew(['zone_id' => $zone->id, 'asset_id' => $asset->id]);
                $wasInside = $state->exists && $state->is_inside;
                $isInside = $position->floor_plan_id === $zone->floor_plan_id && $position->zone_id === $zone->id;
                $isNewPosition = $state->last_position_estimate_id !== $position->id;
                foreach ($zoneRules as $rule) {
                    if ($rule->subject_type === 'asset' && $rule->subject_id !== $asset->id) {
                        continue;
                    }
                    $triggered = match ($rule->trigger_type) {
                        'zone_entry' => $isNewPosition && ! $wasInside && $isInside,
                        'zone_exit' => $isNewPosition && $wasInside && ! $isInside,
                        'zone_inside' => $isInside && $state->entered_at?->lte(now()->subMinutes($rule->duration_minutes)),
                        'zone_outside' => ! $isInside && $state->exited_at?->lte(now()->subMinutes($rule->duration_minutes)),
                        default => false,
                    };
                    if ($triggered) {
                        $active[] = $this->openRule($rule, $asset, $zone->name, $position->id);
                    }
                }
                if (! $wasInside && $isInside) {
                    $state->entered_at = $position->calculated_at ?? now();
                    $state->exited_at = null;
                } elseif ($wasInside && ! $isInside) {
                    $state->entered_at = null;
                    $state->exited_at = $position->calculated_at ?? now();
                } elseif (! $state->exists && ! $isInside) {
                    $state->exited_at = $position->calculated_at ?? now();
                }
                $state->fill(['is_inside' => $isInside, 'last_evaluated_at' => now(), 'last_position_estimate_id' => $position->id])->save();
            }
        }

        return $active;
    }

    private function subjects($assets, AlertRule $rule)
    {
        return $rule->subject_type === 'asset' ? $assets->where('id', $rule->subject_id) : $assets;
    }

    private function openRule(AlertRule $rule, Asset $asset, string $detail, string $evidenceId): string
    {
        $bucket = now()->timestamp - (now()->timestamp % max(60, $rule->cooldown_minutes * 60));
        $fingerprint = "rule:{$rule->id}:{$asset->id}:{$bucket}";
        $recipients = OrganizationMembership::query()
            ->with('user')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($query) use ($rule): void {
                $query->whereIn('role', $rule->recipient_roles ?? [])
                    ->orWhereIn('user_id', $rule->recipient_user_ids ?? []);
            })->get()->pluck('user.email')->filter()->unique()->values()->all();
        $context = ['rule_id' => $rule->id, 'asset_id' => $asset->id, 'evidence_id' => $evidenceId];
        if (in_array('send_email', $rule->actions ?? [], true)) {
            $context['recipients'] = $recipients;
        }
        $rule->update(['last_triggered_at' => now()]);

        return $this->open($fingerprint, 'custom_rule', $rule->name, "{$asset->name} · {$detail}", $context);
    }

    private function open(string $fingerprint, string $type, string $title, string $message, array $context): string
    {
        $alert = Alert::query()->firstOrNew(['fingerprint' => $fingerprint]);
        $reopened = $alert->exists && $alert->resolved_at !== null;
        $alert->fill(['type' => $type, 'title' => $title, 'message' => $message, 'context' => $context, 'resolved_at' => null]);
        if (! $alert->exists || $reopened) {
            $alert->detected_at = now();
            $alert->notified_at = null;
        }
        $alert->save();

        return $fingerprint;
    }
}
