<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Connectors\ConnectorRegistry;
use App\Enums\ConnectorProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $base = [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::enum(ConnectorProvider::class)],
            'configuration' => ['array'],
            'credentials' => ['array'],
        ];

        $provider = ConnectorProvider::tryFrom((string) $this->input('provider'));
        if (! $provider) {
            return $base;
        }

        $definition = app(ConnectorRegistry::class)->get($provider);
        foreach (['configuration', 'credentials'] as $group) {
            $allowedKeys = array_keys($definition[$group]);
            $base[$group] = $allowedKeys === [] ? ['array', 'max:0'] : ['array:'.implode(',', $allowedKeys)];
            foreach ($definition[$group] as $key => $field) {
                $rules = [$field['required'] ? 'required' : 'nullable'];
                $rules[] = match ($field['type']) {
                    'url' => 'url:http,https',
                    'number' => 'integer',
                    'checkbox' => 'boolean',
                    default => 'string',
                };
                if (isset($field['min'])) {
                    $rules[] = 'min:'.$field['min'];
                }
                if (isset($field['options'])) {
                    $rules[] = Rule::in(array_keys($field['options']));
                }
                $base["{$group}.{$key}"] = $rules;
            }
        }

        if ($provider === ConnectorProvider::SapS4Hana) {
            $authType = $this->input('configuration.auth_type');
            $base['credentials.username'][] = Rule::requiredIf($authType === 'basic');
            $base['credentials.password'][] = Rule::requiredIf($authType === 'basic');
            $base['credentials.token'][] = Rule::requiredIf($authType === 'bearer');
        }

        return $base;
    }
}
