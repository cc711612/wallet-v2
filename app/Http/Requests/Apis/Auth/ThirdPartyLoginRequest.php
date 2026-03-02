<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ThirdPartyLoginRequest extends FormRequest
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
            'provider' => ['required', 'string'],
            'token' => ['required', 'string'],
        ];
    }
}
