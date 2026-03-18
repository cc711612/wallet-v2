<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Repositories;

interface LineWebhookJobRepositoryInterface
{
    /**
     * 啟動 LINE loading 動畫。
     */
    public function startLoading(string $lineUserId): void;

    /**
     * 回覆純文字訊息。
     */
    public function replyText(string $replyToken, string $message): void;

    /**
     * 回覆確認模板訊息。
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
     * @param  array<int, array<string, mixed>>  $wallets
     */
    public function replyWalletSelectionTemplate(string $replyToken, array $wallets): void;

    /**
     * 主動推播純文字訊息。
     */
    public function pushText(string $lineUserId, string $message): void;

    /**
     * 依 LINE userId 查詢系統 userId。
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
     */
    public function updateSocialWalletIdByLineUserId(string $lineUserId, int $walletId): void;

    /**
     * 查詢 LINE social 綁定的預設帳本。
     */
    public function findSocialWalletIdByLineUserId(string $lineUserId): ?int;

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    public function listLineUserIdsByUserIds(array $userIds): array;

    /**
     * 取得第一筆可用分類 ID。
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
     * @return array<string, mixed>|null
     */
    public function getWalletCalculateSummary(int $walletId): ?array;
}
