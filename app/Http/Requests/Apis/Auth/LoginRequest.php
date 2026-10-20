<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * 允許所有請求者進行登入驗證。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 登入驗證規則。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:6', 'max:18'],
            // 不使用 exists:users,account，避免透過不同錯誤訊息列舉帳號；由 AuthService 統一判斷
            'account' => ['required', 'string'],
        ];
    }
}
