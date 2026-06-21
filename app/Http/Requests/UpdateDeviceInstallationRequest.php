<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'x_meters' => ['required', 'numeric', 'min:0'],
            'y_meters' => ['required', 'numeric', 'min:0'],
            'reference_rssi' => ['required', 'integer', 'between:-127,-1'],
            'path_loss_exponent' => ['required', 'numeric', 'between:0.5,8'],
        ];
    }
}
