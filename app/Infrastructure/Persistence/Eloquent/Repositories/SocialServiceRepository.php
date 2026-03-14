<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Social\Entities\SocialEntity;
use App\Domain\Social\Repositories\SocialServiceRepositoryInterface;
use Illuminate\Support\Facades\DB;

class SocialServiceRepository implements SocialServiceRepositoryInterface
{
    /**
     * 依社群類型與值查詢，並帶出綁定 user_id。
     *
     * @return array<string, mixed>|null
     */
    public function findByTypeAndValue(int $socialType, string $socialTypeValue): ?array
    {
        $social = SocialEntity::query()
            ->with(['users:id'])
            ->where('social_type', $socialType)
            ->where('social_type_value', $socialTypeValue)
            ->first([
                'id',
                'social_type',
                'social_type_value',
                'name',
                'email',
                'image',
                'token',
            ]);

        if ($social === null) {
            return null;
        }

        $socialData = $social->toArray();
        $socialData['user_id'] = $social->users->first()?->id;

        return $socialData;
    }

    /**
     * 建立或更新社群資料。
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function updateOrCreateByTypeAndValue(int $socialType, string $socialTypeValue, array $attributes): array
    {
        $social = SocialEntity::query()->updateOrCreate(
            [
                'social_type' => $socialType,
                'social_type_value' => $socialTypeValue,
            ],
            [
                'social_type' => $socialType,
                'social_type_value' => $socialTypeValue,
                'name' => (string) ($attributes['name'] ?? ''),
                'email' => (string) ($attributes['email'] ?? ''),
                'image' => (string) ($attributes['image'] ?? ''),
                'token' => (string) ($attributes['token'] ?? ''),
            ]
        );

        return $social->toArray();
    }

    /**
     * 綁定社群到使用者。
     */
    public function bindSocialToUser(int $socialId, int $userId): void
    {
        DB::table('user_social')->updateOrInsert(
            ['social_id' => $socialId],
            ['user_id' => $userId]
        );
    }

    /**
     * 解除使用者指定社群綁定。
     */
    public function unbindSocialByTypeAndUser(int $socialType, int $userId): void
    {
        DB::table('user_social')
            ->where('user_id', $userId)
            ->whereIn('social_id', function ($query) use ($socialType): void {
                $query->select('id')
                    ->from('socials')
                    ->where('social_type', $socialType);
            })
            ->delete();
    }

    /**
     * 檢查社群資料是否存在。
     */
    public function existsByTypeAndValue(int $socialType, string $socialTypeValue): bool
    {
        return SocialEntity::query()
            ->where('social_type', $socialType)
            ->where('social_type_value', $socialTypeValue)
            ->exists();
    }

    /**
     * 取得使用者綁定社群列表。
     *
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
