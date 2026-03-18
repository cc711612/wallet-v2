<?php

declare(strict_types=1);

namespace App\Http\Requests\Apis\Socials;

use App\Domain\Social\Enums\SocialTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SocialCheckBindRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 檢查第三方綁定參數。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'socialType' => ['required', 'integer', new Enum(SocialTypeEnum::class)],
            'socialTypeValue' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('socialType')) {
            $this->merge([
                'socialType' => (int) $this->input('socialType'),
            ]);
        }
    }
}
