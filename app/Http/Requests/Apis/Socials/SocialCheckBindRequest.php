<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Socials;

use Illuminate\Foundation\Http\FormRequest;

class SocialCheckBindRequest extends FormRequest
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
            'socialType' => ['required', 'string'],
            'socialTypeValue' => ['required', 'string'],
        ];
    }
}
