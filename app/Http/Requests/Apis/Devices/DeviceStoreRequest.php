<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Devices;

use Illuminate\Foundation\Http\FormRequest;

class DeviceStoreRequest extends FormRequest
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
            'wallet_user_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'platform' => ['required', 'string'],
            'device_name' => ['required', 'string'],
            'device_type' => ['required', 'string'],
            'fcm_token' => ['required', 'string'],
            'expired_at' => ['sometimes', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var array<int, array<string, mixed>> $walletUsers */
        $walletUsers = (array) $this->input('wallet_user', []);

        $walletId = (int) $this->route('wallet', 0);

        /** @var array<string, mixed> $walletUser */
        $walletUser = $walletId > 0
            ? (array) data_get($walletUsers, (string) $walletId, [])
            : [];

        if ($walletUser === []) {
            $walletUser = (array) (array_values($walletUsers)[0] ?? []);
        }

        $this->merge([
            'wallet_user_id' => (int) data_get($walletUser, 'id', 0),
            'user_id' => (int) data_get($walletUser, 'user_id', 0) ?: null,
        ]);
    }
}
