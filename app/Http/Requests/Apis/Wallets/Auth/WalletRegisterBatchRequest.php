<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Wallets\Auth;

use Illuminate\Foundation\Http\FormRequest;

class WalletRegisterBatchRequest extends FormRequest
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
            'code' => ['required', 'string'],
            'name' => ['required', 'array'],
            'name.*' => ['required', 'string'],
        ];
    }
}
