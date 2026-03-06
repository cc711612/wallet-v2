<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Option\Entities\CategoryEntity;
use App\Domain\Social\Entities\SocialEntity;
use App\Domain\Social\Enums\SocialTypeEnum;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Webhook\Repositories\LineWebhookJobRepositoryInterface;
use Illuminate\Support\Facades\Http;

class LineWebhookJobRepository implements LineWebhookJobRepositoryInterface
{
    /**
     * 啟動 LINE loading 動畫。
     *
     * @param  string  $lineUserId
     * @return void
     */
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

    /**
     * 回覆純文字訊息。
     *
     * @param  string  $replyToken
     * @param  string  $message
     * @return void
     */
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

    /**
     * 回覆 Confirm Template 訊息。
     *
     * @param  string  $replyToken
     * @param  string  $altText
     * @param  string  $promptText
     * @param  string  $confirmLabel
     * @param  string  $confirmText
     * @param  string  $rejectLabel
     * @param  string  $rejectText
     * @return void
     */
    public function replyConfirmTemplate(
        string $replyToken,
        string $altText,
        string $promptText,
        string $confirmLabel,
        string $confirmText,
        string $rejectLabel,
        string $rejectText
    ): void {
        $token = (string) config('bot.line.access_token', '');
        if ($token === '' || $replyToken === '') {
            return;
        }

        Http::timeout(10)
            ->withToken($token)
            ->post('https://api.line.me/v2/bot/message/reply', [
                'replyToken' => $replyToken,
                'messages' => [[
                    'type' => 'template',
                    'altText' => $altText,
                    'template' => [
                        'type' => 'confirm',
                        'text' => $promptText,
                        'actions' => [
                            [
                                'type' => 'message',
                                'label' => $confirmLabel,
                                'text' => $confirmText,
                            ],
                            [
                                'type' => 'message',
                                'label' => $rejectLabel,
                                'text' => $rejectText,
                            ],
                        ],
                    ],
                ]],
            ]);
    }

    /**
     * 主動推播純文字訊息。
     *
     * @param  string  $lineUserId
     * @param  string  $message
     * @return void
     */
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

    /**
     * 依 LINE userId 查詢系統 userId。
     *
     * @param  string  $lineUserId
     * @return int|null
     */
    public function findUserIdByLineUserId(string $lineUserId): ?int
    {
        $social = SocialEntity::query()
            ->where('social_type', SocialTypeEnum::SOCIAL_TYPE_LINE->value)
            ->where('social_type_value', $lineUserId)
            ->with(['users' => function ($query) {
                $query->select('users.id');
            }])
            ->first(['id']);

        if ($social === null || $social->users->isEmpty()) {
            return null;
        }

        return $social->users->first()->id;
    }

    /**
     * 取得使用者可存取帳本清單。
     *
     * @param  int  $userId
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * 以帳本代碼查詢使用者可選擇帳本。
     *
     * @param  int  $userId
     * @param  string  $code
     * @return array<string, mixed>|null
     */
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

    /**
     * 更新 LINE social 綁定的帳本 ID。
     *
     * @param  string  $lineUserId
     * @param  int  $walletId
     * @return void
     */
    public function updateSocialWalletIdByLineUserId(string $lineUserId, int $walletId): void
    {
        SocialEntity::query()
            ->where('social_type', SocialTypeEnum::SOCIAL_TYPE_LINE->value)
            ->where('social_type_value', $lineUserId)
            ->update(['wallet_id' => $walletId]);
    }

    /**
     * 查詢 LINE social 綁定帳本 ID。
     *
     * @param  string  $lineUserId
     * @return int|null
     */
    public function findSocialWalletIdByLineUserId(string $lineUserId): ?int
    {
        $walletId = SocialEntity::query()
            ->where('social_type', SocialTypeEnum::SOCIAL_TYPE_LINE->value)
            ->where('social_type_value', $lineUserId)
            ->value('wallet_id');

        return $walletId === null ? null : (int) $walletId;
    }

    /**
     * 依 userIds 查詢已綁定 LINE userIds。
     *
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    public function listLineUserIdsByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return SocialEntity::query()
            ->where('social_type', SocialTypeEnum::SOCIAL_TYPE_LINE->value)
            ->whereHas('users', function ($query) use ($userIds) {
                $query->whereIn('users.id', $userIds);
            })
            ->with(['users' => function ($query) use ($userIds) {
                $query->whereIn('users.id', $userIds)->select('users.id');
            }])
            ->get(['social_type_value'])
            ->map(static fn (SocialEntity $social): string => $social->social_type_value)
            ->filter(static fn (string $id): bool => $id !== '')
            ->values()
            ->all();
    }

    /**
     * 取得第一筆分類 ID。
     *
     * @return int|null
     */
    public function firstCategoryId(): ?int
    {
        $id = CategoryEntity::query()->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
