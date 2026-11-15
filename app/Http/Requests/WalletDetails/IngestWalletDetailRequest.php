<?php

declare(strict_types=1);

namespace App\Http\Requests\WalletDetails;

use Illuminate\Foundation\Http\FormRequest;

class IngestWalletDetailRequest extends FormRequest
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
            'text' => ['required', 'string', 'max:2000'],
            'received_at' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * 建立者一律取自 middleware 驗證過的帳本成員。
     */
    public function walletUserId(): int
    {
        $walletId = (int) $this->route('wallet');

        return (int) data_get((array) $this->input('wallet_user', []), $walletId.'.id', 0);
    }
}
