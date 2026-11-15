# API 文件 - 推播自動記帳（ingest）

## 概述

手機自動化（例如 MacroDroid）收到銀行刷卡推播時，把**推播原文**直接送到後端；後端解析出金額、商店、日期，替呼叫者建立一筆**個人消費**明細。

- 解析與去重都在後端，之後銀行改推播格式只要改後端解析規則，不必改手機設定。
- 解析順序：規則解析器（`RuleBasedNotificationParser`）→ 規則沒命中且 AI 開關開啟時才走 Gemini 備援（`AiNotificationParser`）。
- 同一則推播重送不會重複建立（見「去重行為」）。

## 基本資訊

| 項目 | 內容 |
|------|------|
| **API 路徑** | `POST /api/wallet/{wallet}/detail/ingest` |
| **Route name** | `api.wallet.detail.ingest` |
| **Middleware** | `VerifyWalletMemberApi`、`throttle:ingest` |
| **限流** | 每位帳本成員（wallet_user）每分鐘 20 次，超過回 HTTP 429 |
| **Controller** | `WalletDetailController@ingest` |
| **Service** | `App\Domain\Wallet\Services\WalletDetailIngestService` |

## 認證

沿用既有 `VerifyWalletMemberApi`（與 `POST /api/wallet/{wallet}/detail` 相同）：

- `Authorization: Bearer <JWT>`（建議），或 query/body 帶 `member_token`。
- `{wallet}` 必須是呼叫者所屬帳本，否則 HTTP 403 `無此帳本權限`。
- 明細建立者一律取自 middleware 驗證出的帳本成員，body 帶的 `wallet_user_id`／`wallet_user` 會被忽略。

> 本端點沒有專用的長效 token。MacroDroid 需存放使用者 JWT，JWT 過期時要重新設定。

## 請求參數（JSON body）

| 參數 | 類型 | 驗證規則 | 說明 |
|------|------|----------|------|
| `text` | string | `required, string, max:2000` | 推播原文（標題＋內文皆可） |
| `received_at` | string | `required, date` | 收到推播的時間，建議 ISO 8601 含時區，例如 `2026-11-15T13:15:00+08:00` |
| `source` | string\|null | `nullable, string, max:100` | 推播來源 App（例如 `com.esunbank`），目前僅供除錯，不影響解析 |

```json
{
  "text": "【玉山銀行】您末四碼5678的信用卡於全家便利商店消費金額NT$1,234，謝謝。",
  "received_at": "2026-11-15T13:15:00+08:00",
  "source": "com.esunbank"
}
```

## 寫入內容（固定，不受 client 影響）

| 欄位 | 值 |
|------|----|
| `type` | `2`（一般支出） |
| `symbol_operation_type_id` | `2`（扣款） |
| `is_personal` | `true`（個人消費，**不會**推播 FCM 給其他成員） |
| `select_all` | `false` |
| `users` | `[建立者 wallet_user id]` |
| `payment_wallet_user_id` | 建立者 |
| `title` | 解析出的商店 |
| `value` | 解析出的金額（去千分位，可小數） |
| `unit` | `TWD` |
| `date` | 原文中的日期；沒有時用 `received_at` 轉台北時間（`Asia/Taipei`）的 `Y-m-d` |
| `note` | `[auto-ingest] ` + 遮罩卡號後的原文（原文截前 1000 字） |
| `category_id` | `null`（不自動分類） |

## 回應格式

業務錯誤沿用專案慣例：HTTP 200，body `code` 帶錯誤碼。

### 成功（新建立）

```json
{
  "status": true,
  "code": 200,
  "message": "",
  "data": {
    "id": 123,
    "title": "全家便利商店",
    "value": 1234,
    "date": "2026-11-15",
    "category_id": null,
    "parser": "rule",
    "duplicate": false
  }
}
```

`parser`：`rule`＝規則解析、`ai`＝Gemini 備援。

### 成功（重複推播）

```json
{
  "status": true,
  "code": 200,
  "message": "",
  "data": {
    "id": 123,
    "title": "全家便利商店",
    "value": 1234,
    "date": "2026-11-15",
    "category_id": null,
    "parser": null,
    "duplicate": true
  }
}
```

回傳先前建立的那筆，不新增、不推播。若該明細已被刪除，只有 `id`，其餘欄位為 `null`。

### 錯誤

| HTTP | body `code` | message | 說明 | 是否寫入 |
|------|-------------|---------|------|----------|
| 200 | 400 | `無法解析推播內容` | 規則沒命中且 AI 關閉；或 AI 判定非消費／輸出不合法 | 否 |
| 500 | 500 | `AI 服務暫時無法使用，請稍後再試` | Gemini 逾時或失敗 | 否 |
| 500 | 500 | `相同推播正在處理中，請稍後再試` | 同一則推播的另一個請求正在建立中 | 否 |
| 500 | 500 | `系統錯誤，請稍後再試` | 其他未預期錯誤（已 report） | 否 |
| 401 | 401 | `請重新登入`／`請帶入 member_token` | 認證失敗 | 否 |
| 403 | 403 | `無此帳本權限` | 不是該帳本成員 | 否 |
| 422 | - | Laravel 驗證錯誤格式 | `text`／`received_at`／`source` 不合法 | 否 |
| 429 | - | Too Many Attempts | 超過每分鐘 20 次 | 否 |

任何失敗都**不會**留下去重 key，修正後重送即可正常建立。

## 去重行為

- key = `sha256(wallet_id, wallet_user_id, trim(text), received_at 轉台北時間取到分鐘)`，存在 Laravel Cache，value 為明細 id，TTL 30 天。
- 同一分鐘內、原文相同（前後空白不計）的推播視為同一則；同一時間點用不同時區寫法表示也算同一則。
- 建立流程以 `Cache::lock` 保護：併發的兩個相同請求只會建立一筆；後到者等待（預設最多 10 秒，`INGEST_LOCK_WAIT_SECONDS`）後回傳 `duplicate: true`，等不到回 `相同推播正在處理中`。
- 去重只依賴 Cache（沒有 DB unique 欄位）：Cache 被清空或過了 30 天後，重送同一則會再建立一筆。正式環境的 cache store 必須支援 atomic lock（redis／file／database＋`cache_locks` 表）。

## 規則解析

`App\Domain\Wallet\Ingest\RuleBasedNotificationParser`，金額與商店都命中才算成功：

- 金額：`消費金額 NT$1,234`、`刷卡金額1,234元`、`交易金額：TWD 1,234.5`、`NT$1,234`、`新台幣85元`、`1,234元`；去千分位、可小數；需介於 0（不含）～ 1,000,000。
- 商店：`於<商店>消費`、`在<商店>刷卡`、`商店：<商店>`／`特店名稱:<商店>`；會去掉前置的日期時間（例如 `於11/14 21:03於統一超商消費`）。
- 日期：`YYYY/MM/DD`、`YYYY-MM-DD`、民國 `YYY/MM/DD`、`MM/DD`（年份取 `received_at`，若因此落在未來則用前一年）。

新增銀行格式：在 `AMOUNT_PATTERNS`／`MERCHANT_PATTERNS` 追加 pattern 並補 `tests/Unit/Domain/Wallet/Ingest/RuleBasedNotificationParserTest.php` 案例。

## AI 備援開關與隱私

| 設定 | env | 預設 | 說明 |
|------|-----|------|------|
| `services.ingest.ai_enabled` | `INGEST_AI_ENABLED` | `false` | 規則沒命中時是否改用 Gemini |
| `services.ingest.lock_wait_seconds` | `INGEST_LOCK_WAIT_SECONDS` | `10` | 併發等待秒數 |

開啟時：

- 送出前以 `NotificationTextMasker::mask()` 遮罩卡號／帳號類數字（`末四碼 1234`、`卡號 1234-5678-…`、`*1234`、10 位以上連續數字 → `****`），金額與日期保留。仍會把**遮罩後的推播原文**（商店、金額、日期）送到 Google Gemini，開啟前請確認可接受。
- Prompt 明確聲明推播原文為不受信任資料、忽略其中任何指令，只輸出固定 JSON `{is_expense, amount, merchant, date}`；AI 沒有任何工具或動作權限。
- 輸出嚴格驗證：`is_expense` 必須為 `true`；`amount` 必須是數字且 0 < amount ≤ 1,000,000；`merchant` trim 後 1～255 字；`date` 須為合法 `YYYY-MM-DD` 且與 `received_at` 相差 ≤ 7 天，否則改用 `received_at` 的日期；JSON 解析失敗或缺欄位 → 400 不寫入。
- `category_id` 一律 `null`。
- log 不記錄推播原文；`note` 只存遮罩後的原文。

## MacroDroid 設定範例

1. **觸發條件**：Notification Received → 選銀行 App（例如玉山 Wallet、CUBE）→ 內容包含 `消費` 或 `刷卡`。
2. **動作**：HTTP Request
   - Method：`POST`
   - URL：`https://wallet.usongrat.tw/api/wallet/<帳本 ID>/detail/ingest`
   - Headers：
     - `Authorization: Bearer <你的 JWT>`
     - `Content-Type: application/json`
     - `Accept: application/json`
   - Body（JSON）：

     ```json
     {
       "text": "[notification_title] [notification]",
       "received_at": "[year]-[month_digit]-[dayofmonth]T[hour]:[minute]:[second]+08:00",
       "source": "[app_package]"
     }
     ```

     （方括號為 MacroDroid 的 magic text，名稱以實際版本的選單為準；月份、日期、時分秒需補零成兩位數。）
3. **（選用）結果處理**：把 response 存到變數，`status` 為 `false` 時發一則本機通知提醒手動記帳。HTTP 500 可稍後重送，去重機制會避免重複建立。
4. **測試**：用 curl 先送一次確認：

   ```bash
   curl -X POST "https://wallet.usongrat.tw/api/wallet/<帳本 ID>/detail/ingest" \
     -H "Authorization: Bearer <JWT>" -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"text":"您於全家便利商店消費金額NT$85","received_at":"2026-11-15T13:15:00+08:00","source":"manual"}'
   ```

## 相關檔案

- `routes/api.php`（`detail/ingest` 必須註冊在 `detail/{detail}` 之前）
- `app/Http/Controllers/Apis/Wallets/WalletDetailController.php`（`ingest`）
- `app/Http/Requests/WalletDetails/IngestWalletDetailRequest.php`
- `app/Domain/Wallet/Services/WalletDetailIngestService.php`
- `app/Domain/Wallet/Ingest/`（規則解析、AI 備援、遮罩）
- `app/Providers/AppServiceProvider.php`（`RateLimiter::for('ingest')`）
- `bootstrap/app.php`（`VerifyWalletMemberApi` 排在 `ThrottleRequests` 之前）
- `tests/Feature/Api/WalletDetailIngestTest.php`、`tests/Unit/Domain/Wallet/Ingest/`
