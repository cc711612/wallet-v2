<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Socials;

use Illuminate\Foundation\Http\FormRequest;

class SocialBindRequest extends FormRequest
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
     * 第三方綁定驗證規則。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
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
        if ($this->user) {
            $this->merge([
                'user' => $this->user,
            ]);
        }
    }
}
