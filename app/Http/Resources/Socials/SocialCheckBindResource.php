<?php

declare(strict_types=1);

namespace App\Http\Resources\Socials;

use Illuminate\Http\Resources\Json\JsonResource;

class SocialCheckBindResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'action' => data_get($this->resource, 'action'),
            'token' => data_get($this->resource, 'token'),
        ];
    }
}
