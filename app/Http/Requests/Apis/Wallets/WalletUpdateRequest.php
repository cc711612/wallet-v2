<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Wallets;

use Illuminate\Foundation\Http\FormRequest;

class WalletUpdateRequest extends FormRequest
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
            'status' => ['sometimes', 'boolean'],
            'unit' => ['sometimes', 'string', 'size:3'],
            'unitConfigurable' => ['sometimes', 'boolean'],
            'decimalPlaces' => ['sometimes', 'integer', 'min:0'],
            'user' => ['required', 'array'],
            'user.id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->user) {
            $payload['user'] = $this->user;
        }

        if ($this->has('unit')) {
            $payload['unit'] = strtoupper((string) $this->input('unit'));
        }

        if ($this->has('unitConfigurable')) {
            $payload['unitConfigurable'] = (bool) $this->input('unitConfigurable');
        }

        if ($this->has('decimalPlaces')) {
            $payload['decimalPlaces'] = (int) $this->input('decimalPlaces');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
