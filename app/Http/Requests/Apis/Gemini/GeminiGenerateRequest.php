<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Gemini;

use Illuminate\Foundation\Http\FormRequest;

class GeminiGenerateRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:4000'],
            'temperature' => ['sometimes', 'numeric', 'between:0,1'],
            'max_tokens' => ['sometimes', 'integer', 'min:1', 'max:8192'],
        ];
    }
}
