<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Social\Entities\SocialEntity;
use App\Domain\Social\Repositories\SocialServiceRepositoryInterface;

class SocialServiceRepository implements SocialServiceRepositoryInterface
{
    public function existsByTypeAndValue(string $socialType, string $socialTypeValue): bool
    {
        return SocialEntity::query()
            ->where('social_type', $socialType)
            ->where('social_type_value', $socialTypeValue)
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSocialsByUserId(int $userId): array
    {
        return SocialEntity::query()
            ->join('user_social', 'user_social.social_id', '=', 'socials.id')
            ->where('user_social.user_id', $userId)
            ->orderBy('socials.id')
            ->get(['socials.id', 'socials.social_type', 'socials.social_type_value', 'socials.name', 'socials.email', 'socials.image'])
            ->map(static fn (SocialEntity $item): array => [
                'id' => (int) $item->id,
                'social_type' => (int) $item->social_type,
                'social_type_value' => (string) $item->social_type_value,
                'name' => (string) ($item->name ?? ''),
                'email' => (string) ($item->email ?? ''),
                'image' => (string) ($item->image ?? ''),
            ])
            ->all();
    }
}
