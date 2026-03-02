<?php

declare(strict_types=1);

namespace App\Http\Resources\Options;

use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'option' => data_get($this->resource, 'option', []),
            'rates' => data_get($this->resource, 'rates', []),
            'updated_at' => data_get($this->resource, 'updated_at'),
        ];
    }
}
