<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Socials;

use App\Domain\Social\Enums\SocialTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SocialUnBindRequest extends FormRequest
{
    /**
     * 允許已通過 middleware 的請求。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 第三方解除綁定驗證規則。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'socialType' => ['required', 'integer', new Enum(SocialTypeEnum::class)],
            'user' => ['required', 'array'],
        ];
    }

    /**
     * 將 middleware 注入的 user 合併至驗證資料。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('socialType')) {
            $payload['socialType'] = (int) $this->input('socialType');
        }

        if ($this->user) {
            $payload['user'] = $this->user;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
