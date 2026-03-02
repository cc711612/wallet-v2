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
            'platform' => ['required', 'string'],
            'device_name' => ['required', 'string'],
            'device_type' => ['required', 'string'],
            'fcm_token' => ['required', 'string'],
            'expired_at' => ['sometimes', 'date'],
        ];
    }
}
