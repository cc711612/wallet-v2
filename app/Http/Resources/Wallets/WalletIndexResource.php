<?php

declare(strict_types=1);

namespace App\Http\Resources\Wallets;

use Illuminate\Http\Resources\Json\JsonResource;

class WalletIndexResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'paginate' => data_get($this->resource, 'paginate', []),
            'wallets' => data_get($this->resource, 'wallets', []),
        ];
    }
}
