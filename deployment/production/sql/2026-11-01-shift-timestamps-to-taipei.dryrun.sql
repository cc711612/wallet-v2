-- wallet：把 2026-07-02 切到 wallet-v2 後以 UTC 寫入的時間戳轉成台灣時間（+8h）
-- @until = 部署 app.timezone=Asia/Taipei 完成當下的 UTC 時間；之後新程式寫的是台灣時間（必 > @until），不會重複轉換。
-- v2（UTC）判定：值 >= 2026-07-02 08:00:00，排除 13:48～13:55（舊系統最後幾筆台灣時間，明細 3318～3321）。
-- 不處理：exchange_rates（不對使用者顯示）、telescope_entries。
SELECT 'wallet_details.created_at' col, COUNT(*) n FROM wallet_details WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until)
UNION ALL
SELECT 'wallet_details.updated_at' col, COUNT(*) n FROM wallet_details WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until)
UNION ALL
SELECT 'wallet_details.deleted_at' col, COUNT(*) n FROM wallet_details WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until)
UNION ALL
SELECT 'wallet_details.checkout_at' col, COUNT(*) n FROM wallet_details WHERE (checkout_at >= '2026-07-02 08:00:00' AND checkout_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND checkout_at <= @until)
UNION ALL
SELECT 'wallet_detail_splits.created_at' col, COUNT(*) n FROM wallet_detail_splits WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until)
UNION ALL
SELECT 'wallet_detail_splits.updated_at' col, COUNT(*) n FROM wallet_detail_splits WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until)
UNION ALL
SELECT 'wallet_detail_splits.deleted_at' col, COUNT(*) n FROM wallet_detail_splits WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until)
UNION ALL
SELECT 'wallets.created_at' col, COUNT(*) n FROM wallets WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until)
UNION ALL
SELECT 'wallets.updated_at' col, COUNT(*) n FROM wallets WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until)
UNION ALL
SELECT 'wallets.deleted_at' col, COUNT(*) n FROM wallets WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until)
UNION ALL
SELECT 'wallet_users.created_at' col, COUNT(*) n FROM wallet_users WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until)
UNION ALL
SELECT 'wallet_users.updated_at' col, COUNT(*) n FROM wallet_users WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until)
UNION ALL
SELECT 'wallet_users.deleted_at' col, COUNT(*) n FROM wallet_users WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until)
UNION ALL
SELECT 'users.created_at' col, COUNT(*) n FROM users WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until)
UNION ALL
SELECT 'users.updated_at' col, COUNT(*) n FROM users WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until)
UNION ALL
SELECT 'users.deleted_at' col, COUNT(*) n FROM users WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until)
UNION ALL
SELECT 'users.verified_at' col, COUNT(*) n FROM users WHERE (verified_at >= '2026-07-02 08:00:00' AND verified_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND verified_at <= @until)
UNION ALL
SELECT 'socials.created_at' col, COUNT(*) n FROM socials WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until)
UNION ALL
SELECT 'socials.updated_at' col, COUNT(*) n FROM socials WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until)
UNION ALL
SELECT 'socials.deleted_at' col, COUNT(*) n FROM socials WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until)
UNION ALL
SELECT 'categories.created_at' col, COUNT(*) n FROM categories WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until)
UNION ALL
SELECT 'categories.updated_at' col, COUNT(*) n FROM categories WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until)
UNION ALL
SELECT 'categories.deleted_at' col, COUNT(*) n FROM categories WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until)
UNION ALL
SELECT 'personal_access_tokens.created_at' col, COUNT(*) n FROM personal_access_tokens WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until)
UNION ALL
SELECT 'personal_access_tokens.updated_at' col, COUNT(*) n FROM personal_access_tokens WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until)
UNION ALL
SELECT 'personal_access_tokens.last_used_at' col, COUNT(*) n FROM personal_access_tokens WHERE (last_used_at >= '2026-07-02 08:00:00' AND last_used_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND last_used_at <= @until)
UNION ALL
SELECT 'failed_jobs.failed_at' col, COUNT(*) n FROM failed_jobs WHERE (failed_at >= '2026-07-02 08:00:00' AND failed_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND failed_at <= @until)
UNION ALL
SELECT 'devices(rows touched)' col, COUNT(*) n FROM devices WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until) OR (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until) OR (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
