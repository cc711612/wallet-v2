<?php

declare(strict_types=1);

namespace App\Http\Resources\Gemini;

use Illuminate\Http\Resources\Json\JsonResource;

class GeminiResponseResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'success' => (bool) data_get($this->resource, 'success', true),
            'text' => data_get($this->resource, 'text', ''),
            'raw_response' => data_get($this->resource, 'raw_response', (object) []),
        ];
    }
}
