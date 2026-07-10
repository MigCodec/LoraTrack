<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFloorPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'tab_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'width_meters' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:100000'],
            'height_meters' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:100000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('name') && ! $this->exists('tab_color') && ! $this->exists('width_meters') && ! $this->exists('height_meters')) {
                $validator->errors()->add('floor_plan', 'Debes indicar un cambio para el plano.');
            }
            if ($this->exists('width_meters') xor $this->exists('height_meters')) {
                $validator->errors()->add('floor_plan', 'Debes indicar ancho y largo para actualizar la escala del plano.');
            }
        });
    }
}
