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
            'checkout_id' => ['required', 'array'],
            'checkout_id.*' => ['integer'],
        ];
    }
}
