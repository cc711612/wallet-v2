<?php

declare(strict_types=1);

namespace App\Http\Resources\Wallets;

use Illuminate\Http\Resources\Json\JsonResource;

class WalletStoreResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'wallet' => data_get($this->resource, 'wallet', (object) []),
        ];
    }
}
