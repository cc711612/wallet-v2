<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Wallets;

use Illuminate\Foundation\Http\FormRequest;

class WalletUserUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'string'],
            'notify_enable' => ['sometimes', 'boolean'],
        ];
    }
}
