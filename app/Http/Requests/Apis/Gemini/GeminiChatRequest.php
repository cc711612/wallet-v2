<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Gemini;

use Illuminate\Foundation\Http\FormRequest;

class GeminiChatRequest extends FormRequest
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
            'messages' => ['required', 'array'],
            'messages.*.role' => ['required', 'in:user,model'],
            'messages.*.content' => ['required', 'string'],
            'temperature' => ['sometimes', 'numeric', 'between:0,1'],
            'max_tokens' => ['sometimes', 'integer', 'min:1', 'max:8192'],
        ];
    }
}
