<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Option\Entities\CategoryEntity;
use App\Domain\Social\Entities\SocialEntity;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Webhook\Repositories\LineWebhookJobRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class LineWebhookJobRepository implements LineWebhookJobRepositoryInterface
{
    private const LINE_SOCIAL_TYPE = 1;

    public function startLoading(string $lineUserId): void
    {
        $token = (string) config('bot.line.access_token', '');
        if ($token === '' || $lineUserId === '') {
            return;
        }

        Http::timeout(5)
            ->withToken($token)
            ->post('https://api.line.me/v2/bot/chat/loading/start', [
                'chatId' => $lineUserId,
                'loadingSeconds' => 5,
            ]);
    }

    public function replyText(string $replyToken, string $message): void
    {
        $token = (string) config('bot.line.access_token', '');
        if ($token === '' || $replyToken === '') {
            return;
        }

        Http::timeout(10)
            ->withToken($token)
            ->post('https://api.line.me/v2/bot/message/reply', [
                'replyToken' => $replyToken,
                'messages' => [[
                    'type' => 'text',
                    'text' => $message,
                ]],
            ]);
    }

    public function pushText(string $lineUserId, string $message): void
    {
        $token = (string) config('bot.line.access_token', '');
        if ($token === '' || $lineUserId === '') {
            return;
        }

        Http::timeout(10)
            ->withToken($token)
            ->post('https://api.line.me/v2/bot/message/push', [
                'to' => $lineUserId,
                'messages' => [[
                    'type' => 'text',
                    'text' => $message,
                ]],
            ]);
    }

    public function findUserIdByLineUserId(string $lineUserId): ?int
    {
        $social = SocialEntity::query()
            ->where('social_type', self::LINE_SOCIAL_TYPE)
            ->where('social_type_value', $lineUserId)
            ->first(['id']);
        if ($social === null) {
            return null;
        }

        $userId = DB::table('user_social')
            ->where('social_id', $social->id)
            ->value('user_id');

        return $userId === null ? null : (int) $userId;
    }

    public function listWalletsByUserId(int $userId): array
    {
        $guestWalletIds = WalletUserEntity::query()
            ->where('user_id', $userId)
            ->pluck('wallet_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return WalletEntity::query()
            ->where(function ($query) use ($guestWalletIds, $userId): void {
                $query->where('user_id', $userId)
                    ->orWhereIn('id', $guestWalletIds ?: [0]);
            })
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'code'])
            ->map(static fn (WalletEntity $wallet): array => $wallet->toArray())
            ->values()
            ->all();
    }

    public function findWalletByCodeForUser(int $userId, string $code): ?array
    {
        $wallet = WalletEntity::query()->where('code', $code)->first(['id', 'title', 'code', 'user_id']);
        if ($wallet === null) {
            return null;
        }

        $belongsToUser = (int) $wallet->user_id === $userId
            || WalletUserEntity::query()->where('wallet_id', (int) $wallet->id)->where('user_id', $userId)->exists();

        return $belongsToUser ? $wallet->toArray() : null;
    }

    public function updateSocialWalletIdByLineUserId(string $lineUserId, int $walletId): void
    {
        SocialEntity::query()
            ->where('social_type', self::LINE_SOCIAL_TYPE)
            ->where('social_type_value', $lineUserId)
            ->update(['wallet_id' => $walletId]);
    }

    public function findSocialWalletIdByLineUserId(string $lineUserId): ?int
    {
        $walletId = SocialEntity::query()
            ->where('social_type', self::LINE_SOCIAL_TYPE)
            ->where('social_type_value', $lineUserId)
            ->value('wallet_id');

        return $walletId === null ? null : (int) $walletId;
    }

    public function listLineUserIdsByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('socials')
            ->join('user_social', 'user_social.social_id', '=', 'socials.id')
            ->whereIn('user_social.user_id', $userIds)
            ->where('socials.social_type', self::LINE_SOCIAL_TYPE)
            ->pluck('socials.social_type_value')
            ->map(static fn ($id): string => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '')
            ->values()
            ->all();
    }

    public function firstCategoryId(): ?int
    {
        $id = CategoryEntity::query()->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
