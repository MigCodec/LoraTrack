<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteOrganizationUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'access_type' => ['required', Rule::in(['permanent', 'until'])],
            'membership_expires_at' => ['nullable', 'required_if:access_type,until', 'date', 'after_or_equal:today'],
        ];
    }
}
