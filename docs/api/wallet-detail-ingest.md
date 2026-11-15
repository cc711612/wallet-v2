# API 文件 - 推播自動記帳（ingest）

## 概述

手機自動化（例如 MacroDroid）收到銀行刷卡推播時，把**推播原文**直接送到後端；後端解析出金額、商店、日期，替呼叫者建立一筆**個人消費**明細。

**預設是「模板白名單」模式**：只有整段原文完全符合某個已註冊模板的推播才會記帳，其餘一律回 400「無法解析推播內容」。寧可漏記（使用者手動補）也不錯記。

解析順序：

1. **模板白名單**（`NotificationTemplateRegistry`，`config/ingest_templates.php`）— 預設唯一啟用的方式。**目前預設不內建任何模板**（還沒有真實銀行樣本，不編造格式），所以不加模板時任何推播都會回 400。
2. **啟發式關鍵字規則**（`RuleBasedNotificationParser`）— 選用，`INGEST_HEURISTIC_ENABLED`，**預設關閉，不建議在正式環境開啟**（見「啟發式模式」）。
3. **AI 備援**（Gemini，`AiNotificationParser`）— 選用，`INGEST_AI_ENABLED`，預設關閉，附保守的交叉檢查。

同一則推播重送不會重複建立（見「去重行為」）。

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
| `text` | string | `required, string, max:2000` | 推播原文（標題＋內文） |
| `received_at` | string | `required, date` | 收到推播的時間，建議 ISO 8601 含時區，例如 `2026-11-15T13:15:00+08:00` |
| `source` | string\|null | `nullable, string, max:100` | 推播來源 App 的 package（例如 `com.example.bank`）。**模板若設定了 `source`，請求的 `source` 必須以它開頭（相等也算）才會嘗試該模板**；沒帶 `source` 時只會嘗試沒有限定 `source` 的模板 |

```json
{
  "text": "【範例銀行】您的信用卡於11/14 19:20在鼎泰豐 信義店消費NT$1,350。",
  "received_at": "2026-11-15T13:15:00+08:00",
  "source": "com.example.bank"
}
```

（上例是虛構的「範例銀行」格式，見「範例模板」。）

## 寫入內容（固定，不受 client 影響）

| 欄位 | 值 |
|------|----|
| `type` | `2`（一般支出） |
| `symbol_operation_type_id` | `2`（扣款） |
| `is_personal` | `true`（個人消費，**不會**推播 FCM 給其他成員） |
| `select_all` | `false` |
| `users` | `[建立者 wallet_user id]` |
| `payment_wallet_user_id` | 建立者 |
| `title` | 解析出的商店（單行化、遮罩卡號，最長 255 字） |
| `value` | 解析出的金額（去千分位，四捨五入到 2 位小數） |
| `unit` | `TWD`（本版只支援台幣） |
| `date` | 解析出的日期；模板沒有 `date` 群組時用 `received_at` 轉台北時間（`Asia/Taipei`）的 `Y-m-d` |
| `note` | `[auto-ingest] ` + 單行化、遮罩後的原文（截前 1000 字） |
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
    "title": "鼎泰豐 信義店",
    "value": 1350,
    "date": "2026-11-14",
    "category_id": null,
    "parser": "template:example-bank-purchase",
    "duplicate": false
  }
}
```

`parser` 表示解析來源：

| 值 | 來源 |
|----|------|
| `template:<模板名稱>` | 模板白名單（預設） |
| `rule` | 啟發式規則（需開啟 `INGEST_HEURISTIC_ENABLED`） |
| `ai` | Gemini 備援（需開啟 `INGEST_AI_ENABLED`） |
| `null` | 重複推播（回傳既有那筆） |

### 成功（重複推播）

`duplicate: true`，回傳先前建立的那筆（`parser: null`），不新增、不推播。若該明細已被刪除，只有 `id`，其餘欄位為 `null`。

### 錯誤

| HTTP | body `code` | message | 說明 | 是否寫入 |
|------|-------------|---------|------|----------|
| 200 | 400 | `無法解析推播內容` | 沒有模板整段命中（且選用模式未開或也失敗）；原文非法 UTF-8 或清理後為空；AI 路徑被交叉檢查擋下 | 否 |
| 500 | 500 | `AI 服務暫時無法使用，請稍後再試` | Gemini 逾時或失敗 | 否 |
| 500 | 500 | `相同推播正在處理中，請稍後再試` | 同一則推播的另一個請求正在建立中 | 否 |
| 500 | 500 | `系統錯誤，請稍後再試` | 其他未預期錯誤（已 report），例如 `config/ingest_templates.php` 模板定義不合法 | 否 |
| 401 | 401 | `請重新登入`／`請帶入 member_token` | 認證失敗 | 否 |
| 403 | 403 | `無此帳本權限` | 不是該帳本成員 | 否 |
| 422 | - | Laravel 驗證錯誤格式 | `text`／`received_at`／`source` 不合法 | 否 |
| 429 | - | Too Many Attempts | 超過每分鐘 20 次 | 否 |

任何失敗都**不會**留下去重 key，修正後重送即可正常建立。

## 模板白名單（預設模式）

### 比對規則

- 比對對象是「清理控制字元＋全形轉半形」後的整段原文（見「輸入清理」）。
- 模板的 `pattern` 會被自動包成 `\A\s*(?:pattern)\s*\z` 並以 `/u` 比對：**整段全匹配**，只容許首尾空白。原文只要多出（例如結尾多一句「如有疑問請洽客服」）或少了模板之外的任何內容就不命中 → 400。這是刻意的嚴格：新的、沒見過的寫法（取消、退款、預先授權、分期、OTP、行銷、外幣…）天然不會命中。
- 依 `source` 篩選候選模板後依序嘗試，第一個命中且通過後置驗證者勝出。
- 後置驗證（不通過視為不命中）：
  - `amount`：去千分位後必須是數字，四捨五入到 2 位，0 < amount ≤ 1,000,000。
  - `merchant`：單行化後 1～255 字。
  - `date`（選填群組）：支援 `YYYY/MM/DD`、`YYYY-MM-DD`、`YYYY.MM.DD`、民國 `YYY/MM/DD`、`MM/DD`、`M月D日`；沒有年份時取 `received_at` 的年份，若因此落在未來（1 月收到 12/31 的通知）改用前一年；日期不合法 → 不命中。
  - `time`（選填群組）：目前只用來幫模板吃掉時間欄位，不寫入。

### 模板欄位（`config/ingest_templates.php` → `templates`）

| 欄位 | 必填 | 說明 |
|------|------|------|
| `name` | 是 | 唯一、kebab-case，例如 `esun-credit-purchase`；回應的 `parser` 會是 `template:<name>` |
| `pattern` | 是 | 正規表示式本體（**不含**分隔符號與 `^` `$`，系統自動錨定）。必須含 `(?<amount>…)` 與 `(?<merchant>…)`，可選 `(?<date>…)`、`(?<time>…)` |
| `currency` | 否 | 預設 `TWD`；**本版只允許 `TWD`**，其他值會讓設定載入失敗 |
| `source` | 否 | 限定 App package 前綴，例如 `com.esunbank`；建議每個模板都設定 |

模板定義不合法（缺具名群組、名稱重複、非 TWD、regex 錯誤、`source` 為空字串）時，第一次載入就丟 `InvalidArgumentException`，端點回 500，且單元測試會失敗。

### 如何新增模板

1. **收集真實樣本**：從使用者手機收集同一家銀行、同一種卡的推播原文。至少要有 2 則成功消費；另外收集同一家銀行**不應記帳**的通知：取消、退款／退刷、預先授權、分期、OTP／驗證碼、行銷、海外刷卡（外幣）、繳款／帳單提醒等。樣本中的卡號、姓名先換成假資料。
2. **寫 pattern**：
   - 把固定文字照抄（`$`、`.`、`(` 等 regex 特殊字元要跳脫），變動部分用具名群組。
   - `merchant` 群組要盡量窄，例如 `[^，。\n$]{1,60}?`，**不得能吞進金額或幣別**，否則「在全家消費NT$100消費NT$200」這種惡意或異常文字可能被誤解析。
   - `amount` 群組用 `\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?`，不要用 `.+`。
   - **固定尾句要寫進模板**：如果該銀行每則推播都固定以「如有疑問請洽客服。」結尾，就把這句照抄進 pattern（必要時用 `(?:如有疑問請洽客服。)?` 表示可有可無）；不要用 `.*` 吃掉尾巴，否則「已取消」「已退款」之類的尾句也會被吃掉而誤記。
   - 原文已經過全形轉半形：數字、英文、空白、`$*-/#+@%&_=` 都是半形；中文標點（，：；（）。【】）維持全形。
   - 設定 `source`，把模板綁定到該銀行 App。
3. **加樣本檔** `tests/Fixtures/ingest/<name>.json`：

   ```json
   {
     "template": "esun-credit-purchase",
     "positive": [
       {"text": "…", "source": "com.esunbank", "amount": 1350, "merchant": "…", "date": "2026-11-14"},
       {"text": "…", "source": "com.esunbank", "amount": 85, "merchant": "…"}
     ],
     "negative": [
       {"text": "…已取消…", "source": "com.esunbank", "reason": "取消"},
       {"text": "…退款…", "source": "com.esunbank", "reason": "退款"},
       {"text": "…預先授權…", "source": "com.esunbank", "reason": "預先授權"}
     ]
   }
   ```

   - `positive` ≥ 2 則：必須命中**這個**模板，且 `amount`、`merchant`（以及有填的 `date`）完全相符。`received_at` 固定以 `2026-11-15 13:00:00`（台北）計算。
   - `negative` ≥ 3 則：不得命中**任何**模板。至少涵蓋取消／退款／預先授權／分期／OTP／行銷中的三種，建議全部都放。
   - 樣本的 `source` 省略時沿用模板的 `source`。
4. **在 `config/ingest_templates.php` 加模板**，跑 `vendor/bin/phpunit`。`tests/Unit/Domain/Wallet/Ingest/TemplateFixtureCoverageTest` 會遍歷 config 內所有模板：缺樣本檔、成功樣本少於 2 則、負樣本少於 3 則、成功樣本沒命中或欄位不符、負樣本命中，任一情況都會讓測試失敗。
5. 部署後，請使用者在手機上送一則真實推播確認回應的 `parser` 為 `template:<name>`。

### 範例模板（example，虛構格式）

以下是測試用的虛構「範例銀行」模板，**不是真實銀行格式，不要放進正式設定**。它與樣本檔 `tests/Fixtures/ingest/examples/example-bank-purchase.json`（3 則成功、13 則負樣本）一起用在測試中。

```php
// config/ingest_templates.php
return [
    'templates' => [
        [
            'name' => 'example-bank-purchase',
            'source' => 'com.example.bank',
            'pattern' => '【範例銀行】您的信用卡於(?<date>\d{1,2}/\d{1,2}) (?<time>\d{1,2}:\d{2})在(?<merchant>[^，。\n$]{1,60}?)消費NT\$(?<amount>\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?)。',
        ],
    ],
];
```

| 原文 | 結果 |
|------|------|
| `【範例銀行】您的信用卡於11/14 19:20在鼎泰豐 信義店消費NT$1,350。` | 1350／鼎泰豐 信義店／2026-11-14 |
| 同上，但結尾多「如有疑問請洽客服。」 | 400（全匹配失敗） |
| `…消費NT$1,350已取消。` | 400 |
| `…在AMAZON消費USD 25.99。` | 400 |
| 同成功原文，但 `source` 是 `com.other.bank` 或沒帶 | 400 |

## 啟發式模式（選用，不建議在正式環境開啟）

`services.ingest.heuristic_enabled`（env `INGEST_HEURISTIC_ENABLED`，預設 `false`）。開啟後，模板都不命中時改用 `RuleBasedNotificationParser` 的關鍵字／子句規則。

> ⚠️ **警告**：啟發式模式在 held-out（未參與規則調整的全新案例）驗收中，**非消費被誤記成支出約 33%（17/50）**、**外幣被記成台幣 6/34**。黑名單式規則每補一批字就會冒出新漏洞與新誤殺，屬結構問題。**不建議在正式環境開啟**，只適合開發或個人實驗。

規則概要（細節見程式碼註解與 `tests/Unit/Domain/Wallet/Ingest/RuleBasedNotificationParserTest.php`、`IngestRegressionDatasetTest.php`）：

- 原文切子句後，需同一子句同時有消費語境詞（消費／刷卡／授權／交易／付款…）與金額，才視為交易子句。
- 強排除（取消／失敗／退款／分期／提款／預先授權／OTP…）與外幣標記看交易子句與前後各一子句；軟排除（行銷／帳務字樣）只看交易子句（去括號內容與商店名）。
- 裸 `$` 沒有其他幣別線索時視為台幣。

啟發式模式的已知限制（因預設不啟用，不再修正）：

- held-out 非消費誤記約 33%、外幣誤記 6/34（爭議、掛失、攔截、暫緩、退還、預約、調整、尚未付款、解除圈存、發票、請款等未列入其強排除；`CA$`、`US $`、金額後才出現的「海外」等外幣寫法沒偵測到）。
- 商店名錯誤：`7-11` 被當成日期剝掉；「」【】未剝除；「日期」「時間」「完成」等欄位標籤殘留在商店名；商店名含排除字或外幣字樣會被誤殺。
- 日期：不支援「11月14日」；`MM/DD` 晚於 `received_at`+1 天時一律退一年。
- 結尾免責句若剛好含強排除字（例如「如您已申辦分期付款請忽略」）會誤殺真實消費。
- 消費語境詞（使用／支付／付款）過寬：「刷卡金」「付款日」等也會觸發。

## AI 備援（選用）

| 設定 | env | 預設 | 說明 |
|------|-----|------|------|
| `services.ingest.ai_enabled` | `INGEST_AI_ENABLED` | `false` | 模板（與啟發式，若開啟）都沒有結果時是否改用 Gemini |
| `services.ingest.lock_wait_seconds` | `INGEST_LOCK_WAIT_SECONDS` | `10` | 併發等待秒數 |

### 條件與流程

1. 模板沒命中、啟發式沒開或沒結果，且 `ai_enabled=true` 才會走 AI。
2. **呼叫 AI 前的保守檢查（整段原文，不縮小範圍，誤殺可接受）**，命中任一就 400、不呼叫 AI：
   - 啟發式規則的強排除、軟排除與外幣標記，對**整段原文**掃描（結尾免責句含「優惠」「帳單」也會擋）。
   - 額外強排除：`爭議`、`掛失`、`攔截`、`暫緩`、`退還`、`預約`、`調整`、`尚未付款`、`待付款`、`解除圈存`、`圈存`、`發票`、`帳單`、`請款`、`轉帳`、`轉出`、`匯款`、`退費`。
   - 額外外幣線索：`CA$`、`US$`／`US $`（`$` 前可有空白）、`NZ$`、`A$`、`AU$`、`HK$`、`CN$`、`C$`、`S$`；原文任何位置出現「美國」「海外」「國外」且有裸 `$` 金額。
3. 送給 Gemini 的是遮罩後的原文。Prompt 聲明原文是不受信任資料、忽略其中任何指令，只輸出固定 JSON `{is_expense, amount, currency, merchant, date}`；AI 沒有任何工具或動作權限。
4. **AI 輸出驗證**：
   - `is_expense` 必須是 `true`；`currency` 必須是 `TWD`（大小寫、前後空白不計），缺欄位也算失敗。
   - `amount` 必須是 JSON 數字，**四捨五入到 2 位**後 0 < amount ≤ 1,000,000。
   - `merchant` trim 後 1～255 字。
   - `date` 須為合法 `YYYY-MM-DD` 且與 `received_at` 相差 ≤ 7 天，否則改用 `received_at` 的日期。
   - JSON 解析失敗 → 400。
5. **金額交叉檢查**：AI 給的金額必須等於原文（清理、全形轉半形、去千分位後）中的某個數字（容許 `.00`）。原文是中文數字「二百五十」，或原文 250 而 AI 回 2500 → 400。
6. AI 回傳的 `merchant` **不會**被拿來縮小任何檢查範圍。
7. `category_id` 一律 `null`；log 不記錄推播原文。

開啟 AI 會把**遮罩後的推播原文**（商店、金額、日期）送到 Google Gemini，開啟前請確認可接受。

## 去重行為

- key = `sha256(wallet_id, wallet_user_id, trim(text), received_at 轉台北時間取到分鐘)`，存在 Laravel Cache，value 為明細 id，TTL 30 天。
- 同一分鐘內、原文相同（前後空白不計）的推播視為同一則；同一時間點用不同時區寫法表示也算同一則。
- 建立流程以 `Cache::lock` 保護：併發的兩個相同請求只會建立一筆；後到者等待（預設最多 10 秒）後回傳 `duplicate: true`，等不到回 `相同推播正在處理中`。

## 輸入清理

依序處理；模板比對、啟發式規則、遮罩、送 AI 都使用清理後的文字：

1. 原文不是合法 UTF-8 → 直接回 400。
2. 移除控制字元 `\p{Cc}`（保留換行與 tab）、格式字元 `\p{Cf}`（零寬、bidi 控制 U+202A–202E／U+2066–2069、WJ U+2060、SHY U+00AD、ALM U+061C、BOM…）與 U+2028／2029。**ZWJ（U+200D）只在兩側都是 emoji 時保留**（👨‍👩‍👧、🏳️‍🌈 不會被拆開）；夾在數字或文字間的 ZWJ 一律移除，無法用來繞過卡號遮罩。
3. 全形轉半形（`mb_convert_kana` 的 `rns`：英文字母、數字、空白），再把 `＄＊－／＃＋＠％＆＿＝` 轉半形；夾在數字間的全形逗號、句點轉成千分位與小數點（`１，２３４` → `1,234`）。其他中文標點保留。不依賴 intl 擴充。
4. 清理後沒有內容 → 回 400。
5. 寫入 `title`、`note` 前，換行與 tab 轉成空白，再遮罩。

## 遮罩範圍（`NotificationTextMasker`）

送 AI 前、寫入 `note` 與 `title` 前都會遮罩成 `****`（遮罩前先做上面的清理與全形轉半形）：

| 寫法 | 範例 → 結果 |
|------|-------------|
| 4 碼一組的卡號 | `1234-5678-9012-3456`、`4311-****-****-1234`、全形卡號 → `****` |
| AMEX 4-6-5 | `3782 822463 10005`、`3782-822463-10005` → `****` |
| 10～19 位連續數字 | `378282246310005`、`00123456789012` → `****` |
| 手機 | `0912345678`、`0912-345-678`、`09-1234-5678`、`+886912345678` → `****` |
| 身分證／居留證 | `A123456789`、全形 `Ａ１２３４５６７８９`、舊式居留證 `AB12345678` → `****` |
| 護照（護照／Passport 語境） | `護照號碼 312345678`、`Passport No. 312345678` → 號碼改 `****` |
| 末四碼類關鍵字後號碼 | `末四碼 1234`、`末四碼 (1234)`、`卡號末四碼(1234)`、`信用卡末4碼1234`、`卡末四碼1234`、`卡號後4碼1234`、`尾號1234`、`末四碼 1 2 3 4`、`卡號末四碼一二三四` → 號碼改 `****`（括號保留） |
| 英文 | `Card ending 1234`、`ending in 1234`、`card no. 1234` → `****` |
| 卡組織／卡片字樣後接 4 碼 | `Visa 1234`、`MasterCard 8765`、`AMEX 3782`、`JCB卡(4321)`、`卡片 5678`、`您的卡 1234` → `****` |
| 星號／X 末碼 | `*1234`、`VISA*1234`、`xx1234`、`XXXX-1234` → `****` |
| 統一編號 | `統編 12345678` → `統編 ****` |

不會遮的：金額（`NT$1,234`、`1234元`、`卡片 1500 NTD`、`信用卡 1234 TWD`）、日期時間（`2026/11/15`、`11/15 12:30`）、店名（`XX12 Burger`、`85度C`、`Room 1234 民宿`），以及**訂單編號／優惠碼語境**（前方 6 字內有 `訂單`、`單號`、`編號`、`優惠碼`、`折扣碼`、`序號`、`兌換碼`、`order`、`coupon`、`promo`、`ref`）的英數或長數字。

遮罩遇到 preg 錯誤（例如回溯上限）時回傳空字串（呼叫端視為無內容），並 `Log::warning('NotificationTextMasker preg error')`，只記錯誤訊息與原文長度，**不記原文**。

遮罩是 heuristic，無法保證涵蓋所有寫法。

## 已知限制

- **預設沒有任何模板**：在加入真實銀行樣本與模板之前，端點對所有推播都回 400。
- **模板非常嚴格**：銀行推播文字稍有改版（多一個空白、換一個標點、加一句尾句），模板就不命中而回 400，需要更新模板與樣本。這是刻意的取捨（漏記可以手動補，錯記難以察覺）。
- **只支援新台幣**：模板不允許註冊非 TWD；外幣通知回 400。之後可擴充寫入實際 `unit` 與 `rates`。
- **去重只靠 Cache**，沒有 DB unique 欄位：Cache 被清空或超過 30 天後，重送同一則會再建立一筆；明細建立成功但 `Cache::put` 失敗時，重送也會重複建立。
- **併發請求在拿鎖前各自會解析一次**（AI 開啟時可能各呼叫一次 Gemini），但只會建立一筆明細。
- **正式環境的 `CACHE_STORE` 必須支援 atomic lock**：redis、file 可以；database 需要 `cache_locks` 資料表（目前 repo 沒有 cache／cache_locks migration，上線前要確認）。
- **AI 路徑非常保守**：整段原文掃排除字，結尾免責句含「優惠」「帳單」「登錄」等也會擋；中文數字金額一律 400。
- 啟發式模式的限制見上方「啟發式模式」。
- 本端點沒有專用長效 token，沿用使用者 JWT；JWT 過期需重新設定 MacroDroid。

## MacroDroid 設定範例

1. **觸發條件**：Notification Received → 選銀行 App（只選已有模板的 App）。
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
       "text": "[notification_title][notification]",
       "received_at": "[year]-[month_digit]-[dayofmonth]T[hour]:[minute]:[second]+08:00",
       "source": "[app_package]"
     }
     ```

     - 方括號為 MacroDroid 的 magic text，名稱以實際版本的選單為準；月份、日期、時分秒需補零成兩位數。
     - **`source` 一定要帶 App package**（例如 `[app_package]` 會展開成 `com.example.bank`），模板靠它決定要不要嘗試；沒帶 `source` 就只會嘗試沒有限定 `source` 的模板。
     - `text` 的組法（標題與內文之間是否有空白或換行）必須和模板一致；新增模板前先用實際送出的 `text` 收樣本。
3. **（選用）結果處理**：把 response 存到變數，`status` 為 `false` 時發一則本機通知提醒手動記帳。HTTP 500 可稍後重送，去重機制會避免重複建立。
4. **測試**：

   ```bash
   curl -X POST "https://wallet.usongrat.tw/api/wallet/<帳本 ID>/detail/ingest" \
     -H "Authorization: Bearer <JWT>" -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"text":"<實際推播原文>","received_at":"2026-11-15T13:15:00+08:00","source":"<App package>"}'
   ```

   成功時 `data.parser` 為 `template:<模板名稱>`；回 400 代表沒有模板整段命中。

## 相關檔案

- `config/ingest_templates.php`（模板白名單，預設為空）
- `config/services.php`（`services.ingest.heuristic_enabled`、`ai_enabled`、`lock_wait_seconds`）
- `routes/api.php`（`detail/ingest` 必須註冊在 `detail/{detail}` 之前）
- `app/Http/Controllers/Apis/Wallets/WalletDetailController.php`（`ingest`）
- `app/Http/Requests/WalletDetails/IngestWalletDetailRequest.php`
- `app/Domain/Wallet/Services/WalletDetailIngestService.php`
- `app/Domain/Wallet/Ingest/NotificationTemplateRegistry.php`（模板白名單）
- `app/Domain/Wallet/Ingest/RuleBasedNotificationParser.php`（啟發式，選用）
- `app/Domain/Wallet/Ingest/AiNotificationParser.php`、`AiExpenseGuard.php`（AI 備援與交叉檢查）
- `app/Domain/Wallet/Ingest/NotificationTextMasker.php`（清理、全形轉半形、遮罩）
- `app/Providers/AppServiceProvider.php`（`RateLimiter::for('ingest')`）
- `bootstrap/app.php`（`VerifyWalletMemberApi` 排在 `ThrottleRequests` 之前）
- 測試：
  - `tests/Feature/Api/WalletDetailIngestTemplateTest.php`（預設模板模式）
  - `tests/Feature/Api/WalletDetailIngestTest.php`（啟發式模式、AI 備援、共用流程）
  - `tests/Unit/Domain/Wallet/Ingest/`（模板、樣本覆蓋、遮罩、AI 把關、啟發式回歸資料集）
  - `tests/Fixtures/ingest/<name>.json`（每個模板的樣本）、`tests/Fixtures/ingest/examples/`（範例模板樣本）
