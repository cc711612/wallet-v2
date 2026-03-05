<?php

declare(strict_types=1);

namespace App\Http\Requests\WalletDetails;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutWalletDetailRequest extends FormRequest
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
            'wallet' => ['required', 'integer', 'min:1'],
            'wallet_user_id' => ['required', 'integer', 'min:1'],
            'checkout_id' => ['required', 'array'],
            'checkout_id.*' => ['integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var int $walletId */
        $walletId = (int) $this->route('wallet');
        /** @var array<string, mixed> $walletUser */
        $walletUser = (array) data_get($this->input('wallet_user', []), (string) $walletId, []);

        $this->merge([
            'wallet' => $walletId,
            'wallet_user_id' => (int) data_get($walletUser, 'id', 0),
            'checkout_id' => (array) $this->input('checkout_id', []),
        ]);
    }
}
