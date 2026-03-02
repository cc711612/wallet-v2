<?php

declare(strict_types=1);

namespace App\Http\Resources\WalletDetails;

use Illuminate\Http\Resources\Json\JsonResource;

class WalletDetailCreatedResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'wallet_detail' => [
                'id' => data_get($this->resource, 'id'),
                'wallet_id' => data_get($this->resource, 'wallet_id'),
                'title' => data_get($this->resource, 'title'),
                'value' => data_get($this->resource, 'value'),
                'type' => data_get($this->resource, 'type'),
                'symbol_operation_type_id' => data_get($this->resource, 'symbol_operation_type_id'),
                'created_at' => data_get($this->resource, 'created_at'),
            ],
        ];
    }
}
