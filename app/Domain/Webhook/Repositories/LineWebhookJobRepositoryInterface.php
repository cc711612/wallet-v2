<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Repositories;

interface LineWebhookJobRepositoryInterface
{
    /**
     * 啟動 LINE loading 動畫。
     *
     * @param  string  $lineUserId
     * @return void
     */
    public function startLoading(string $lineUserId): void;

    /**
     * 回覆純文字訊息。
     *
     * @param  string  $replyToken
     * @param  string  $message
     * @return void
     */
    public function replyText(string $replyToken, string $message): void;

    /**
     * 回覆確認模板訊息。
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
    ): void;

    /**
     * 回覆帳本選擇的 Carousel Template。
     *
     * @param  string  $replyToken
     * @param  array<int, array<string, mixed>>  $wallets
     * @return void
     */
    public function replyWalletSelectionTemplate(string $replyToken, array $wallets): void;

    /**
     * 主動推播純文字訊息。
     *
     * @param  string  $lineUserId
     * @param  string  $message
     * @return void
     */
    public function pushText(string $lineUserId, string $message): void;

    /**
     * 依 LINE userId 查詢系統 userId。
     *
     * @param  string  $lineUserId
     * @return int|null
     */
    public function findUserIdByLineUserId(string $lineUserId): ?int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWalletsByUserId(int $userId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCodeForUser(int $userId, string $code): ?array;

    /**
     * 更新 LINE social 綁定的預設帳本。
     *
     * @param  string  $lineUserId
     * @param  int  $walletId
     * @return void
     */
    public function updateSocialWalletIdByLineUserId(string $lineUserId, int $walletId): void;

    /**
     * 查詢 LINE social 綁定的預設帳本。
     *
     * @param  string  $lineUserId
     * @return int|null
     */
    public function findSocialWalletIdByLineUserId(string $lineUserId): ?int;

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    public function listLineUserIdsByUserIds(array $userIds): array;

    /**
     * 取得第一筆可用分類 ID。
     *
     * @return int|null
     */
    public function firstCategoryId(): ?int;

    /**
     * 取得分類清單（供 AI 提示詞使用）。
     *
     * @return array<int, array{id:int,name:string}>
     */
    public function listCategories(): array;

    /**
     * 取得帳本結算摘要（供 LINE 結算訊息使用）。
     *
     * @param  int  $walletId
     * @return array<string, mixed>|null
     */
    public function getWalletCalculateSummary(int $walletId): ?array;
}
