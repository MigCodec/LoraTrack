<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'use_system_recommended' => ['nullable', 'boolean'],
            'intervals' => ['required_unless:use_system_recommended,1', 'array'],
        ];

        foreach (array_keys(config('scheduled-tasks', [])) as $task) {
            $rules["intervals.{$task}"] = ['required_unless:use_system_recommended,1', 'nullable', 'integer', 'min:1', 'max:525600'];
        }

        return $rules;
    }
}
