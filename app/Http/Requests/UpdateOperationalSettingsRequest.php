<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'meraki_retention_days' => ['required', 'integer', 'between:1,3650'],
            'meraki_webhook_batch_limit' => ['required', 'integer', 'between:1,100'],
            'meraki_webhook_max_attempts' => ['required', 'integer', 'between:1,10'],
            'meraki_observation_limit' => ['required', 'integer', 'between:1,100000'],
            'tti_uplink_limit' => ['required', 'integer', 'between:1,1000'],
            'mqtt_message_limit' => ['required', 'integer', 'between:1,1000'],
            'catalog_sync_limit' => ['required', 'integer', 'between:1,10'],
            'storage_cleanup_enabled' => ['nullable', 'boolean'],
            'telemetry_retention_days' => ['required', 'integer', 'between:7,3650'],
            'storage_cleanup_threshold_percent' => ['required', 'numeric', 'between:1,99'],
            'storage_cleanup_max_events' => ['required', 'integer', 'between:1,100000'],
        ];
    }
}
