<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:18'],
        ];
    }
}
