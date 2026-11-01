-- wallet：把 2026-07-02 切到 wallet-v2 後以 UTC 寫入的時間戳轉成台灣時間（+8h）
-- @until = 部署 app.timezone=Asia/Taipei 完成當下的 UTC 時間；之後新程式寫的是台灣時間（必 > @until），不會重複轉換。
-- v2（UTC）判定：值 >= 2026-07-02 08:00:00，排除 13:48～13:55（舊系統最後幾筆台灣時間，明細 3318～3321）。
-- 不處理：exchange_rates（不對使用者顯示）、telescope_entries。

-- 只能執行一次：重跑時 ops_markers 主鍵重複，mysql client 會在任何 UPDATE 前中止
-- @until 設為執行當下的 UTC（須在新程式部署後 8 小時內執行）
CREATE TABLE IF NOT EXISTS ops_markers (name VARCHAR(64) PRIMARY KEY, done_at DATETIME NOT NULL);
START TRANSACTION;
INSERT INTO ops_markers (name, done_at) VALUES ('shift-timestamps-to-taipei-20261101', NOW());
UPDATE wallet_details SET created_at = created_at + INTERVAL 8 HOUR WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until);
UPDATE wallet_details SET updated_at = updated_at + INTERVAL 8 HOUR WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until);
UPDATE wallet_details SET deleted_at = deleted_at + INTERVAL 8 HOUR WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
UPDATE wallet_details SET checkout_at = checkout_at + INTERVAL 8 HOUR WHERE (checkout_at >= '2026-07-02 08:00:00' AND checkout_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND checkout_at <= @until);
UPDATE wallet_detail_splits SET created_at = created_at + INTERVAL 8 HOUR WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until);
UPDATE wallet_detail_splits SET updated_at = updated_at + INTERVAL 8 HOUR WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until);
UPDATE wallet_detail_splits SET deleted_at = deleted_at + INTERVAL 8 HOUR WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
UPDATE wallets SET created_at = created_at + INTERVAL 8 HOUR WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until);
UPDATE wallets SET updated_at = updated_at + INTERVAL 8 HOUR WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until);
UPDATE wallets SET deleted_at = deleted_at + INTERVAL 8 HOUR WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
UPDATE wallet_users SET created_at = created_at + INTERVAL 8 HOUR WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until);
UPDATE wallet_users SET updated_at = updated_at + INTERVAL 8 HOUR WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until);
UPDATE wallet_users SET deleted_at = deleted_at + INTERVAL 8 HOUR WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
UPDATE users SET created_at = created_at + INTERVAL 8 HOUR WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until);
UPDATE users SET updated_at = updated_at + INTERVAL 8 HOUR WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until);
UPDATE users SET deleted_at = deleted_at + INTERVAL 8 HOUR WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
UPDATE users SET verified_at = verified_at + INTERVAL 8 HOUR WHERE (verified_at >= '2026-07-02 08:00:00' AND verified_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND verified_at <= @until);
UPDATE socials SET created_at = created_at + INTERVAL 8 HOUR WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until);
UPDATE socials SET updated_at = updated_at + INTERVAL 8 HOUR WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until);
UPDATE socials SET deleted_at = deleted_at + INTERVAL 8 HOUR WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
UPDATE categories SET created_at = created_at + INTERVAL 8 HOUR WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until);
UPDATE categories SET updated_at = updated_at + INTERVAL 8 HOUR WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until);
UPDATE categories SET deleted_at = deleted_at + INTERVAL 8 HOUR WHERE (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
UPDATE personal_access_tokens SET created_at = created_at + INTERVAL 8 HOUR WHERE (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until);
UPDATE personal_access_tokens SET updated_at = updated_at + INTERVAL 8 HOUR WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until);
UPDATE personal_access_tokens SET last_used_at = last_used_at + INTERVAL 8 HOUR WHERE (last_used_at >= '2026-07-02 08:00:00' AND last_used_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND last_used_at <= @until);
UPDATE failed_jobs SET failed_at = failed_at + INTERVAL 8 HOUR WHERE (failed_at >= '2026-07-02 08:00:00' AND failed_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND failed_at <= @until);
UPDATE devices SET expired_at = IF((updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until), expired_at + INTERVAL 8 HOUR, expired_at), created_at = IF((created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until), created_at + INTERVAL 8 HOUR, created_at), deleted_at = IF((deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until), deleted_at + INTERVAL 8 HOUR, deleted_at), updated_at = IF((updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until), updated_at + INTERVAL 8 HOUR, updated_at) WHERE (updated_at >= '2026-07-02 08:00:00' AND updated_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND updated_at <= @until) OR (created_at >= '2026-07-02 08:00:00' AND created_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND created_at <= @until) OR (deleted_at >= '2026-07-02 08:00:00' AND deleted_at NOT BETWEEN '2026-07-02 13:48:00' AND '2026-07-02 13:55:00' AND deleted_at <= @until);
COMMIT;
