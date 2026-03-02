<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Wallets;

use Illuminate\Foundation\Http\FormRequest;

class WalletStoreRequest extends FormRequest
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
            'title' => ['required', 'string'],
            'mode' => ['sometimes', 'in:single,multi,couple'],
        ];
    }
}
