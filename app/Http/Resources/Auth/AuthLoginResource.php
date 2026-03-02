<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthLoginResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'member_token' => data_get($this->resource, 'member_token'),
            'jwt' => data_get($this->resource, 'jwt'),
            'wallet' => data_get($this->resource, 'wallet', (object) []),
            'walletUsers' => data_get($this->resource, 'walletUsers', []),
            'devices' => data_get($this->resource, 'devices', []),
            'notifies' => data_get($this->resource, 'notifies', []),
        ];
    }
}
