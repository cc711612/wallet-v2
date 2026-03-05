<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Wallets;

use Illuminate\Foundation\Http\FormRequest;

class WalletStoreRequest extends FormRequest
{
    /**
     * @return bool
     */
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
            'user' => ['required', 'array'],
            'user.id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->user) {
            $this->merge([
                'user' => $this->user,
            ]);
        }
    }
}
