<?php

declare(strict_types=1);

namespace App\Http\Requests\WalletDetails;

use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

class StoreWalletDetailRequest extends FormRequest
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
            'type' => [
                'required',
                'integer',
                Rule::in(WalletDetailType::values()),
            ],
            'symbol_operation_type_id' => [
                'required',
                'integer',
                Rule::in(SymbolOperationType::values()),
            ],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'select_all' => ['required', 'boolean'],
            'is_personal' => ['sometimes', 'boolean'],
            'payment_wallet_user_id' => ['nullable', 'integer', 'min:1'],
            'users' => ['sometimes', 'array'],
            'users.*' => ['integer', 'min:1'],
            'splits' => ['sometimes', 'array'],
            'splits.*.user_id' => ['required_with:splits', 'integer', 'min:1'],
            'splits.*.value' => ['required_with:splits', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'size:3'],
            'rates' => ['nullable', 'numeric', 'min:0'],
            'date' => ['sometimes', 'date'],
            'note' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var int $walletId */
        $walletId = (int) $this->route('wallet');

        /** @var array<string, mixed> $walletUser */
        $walletUser = (array) data_get($this->input('wallet_user', []), (string) $walletId, []);

        $normalizedDate = (string) ($this->input('date') ?: now()->toDateString());
        try {
            $normalizedDate = Carbon::parse($normalizedDate)->toDateString();
        } catch (Throwable) {
            $normalizedDate = now()->toDateString();
        }

        $this->merge([
            'wallet' => $walletId,
            'wallet_user_id' => (int) ($this->input('wallet_user_id') ?: data_get($walletUser, 'id', 0)),
            'is_personal' => (bool) $this->input('is_personal', false),
            'select_all' => (bool) $this->input('select_all', false),
            'date' => $normalizedDate,
            'unit' => (string) $this->input('unit', 'TWD'),
            'splits' => (array) $this->input('splits', []),
            'users' => (array) $this->input('users', []),
        ]);
    }
}
