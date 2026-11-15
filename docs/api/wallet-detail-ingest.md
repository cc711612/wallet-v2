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
| `title` | 解析出的商店（寫入前遮罩卡號，最長 255 字） |
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
| 200 | 400 | `無法解析推播內容` | 規則沒命中（含非消費、行銷、分期、外幣）且 AI 關閉；AI 判定非消費、外幣或輸出不合法；原文非法 UTF-8 或清理後為空 | 否 |
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
- 限制見下方「已知限制」。

## 規則解析

`App\Domain\Wallet\Ingest\RuleBasedNotificationParser`，金額與商店都命中才算成功；否則回 `null`，交給 AI 備援（開啟時）或回 400。

### 判定流程（結構性規則）

不再對整段原文掃排除關鍵字（銀行推播結尾常見的「登錄 App 查看優惠」「本訊息僅供通知，非帳單」「可於帳單查詢」會誤殺真實消費），改為：

1. **清理與全形轉半形**：先移除控制字元與格式字元，再把全形英數、空白與常見符號轉半形（見「輸入清理」）。
2. **切子句**：以 `。！？；` 與其半形、換行、`【標題】` 前後、`，`、`、`、非千分位的 `,` 切開。
3. **正向閘門**：第一個「同時含消費語境詞與金額」的子句才是**交易子句**；沒有交易子句 → 回 `null`。
   - 消費語境詞（`CONSUMPTION_PATTERN`）：`消費`、`刷卡`、`授權`、`交易`、`付款`、`支付`、`簽帳`、`扣款`、`使用`，以及 `商店：`、`商店名稱`、`特店名稱` 這類欄位標籤。
   - 金額在累計／額度語境（見「金額」）時不算，所以「本期累計消費金額 NT$50,000」不會被當成交易子句。
4. **排除只看交易子句附近**：
   - **強排除**（`STRONG_EXCLUDE_PATTERNS`）與**外幣標記**：交易子句以及**前後各一個子句**。這樣「…消費NT$100，此筆為預先授權」「…消費NT$30,000，分12期」這類寫法仍會被擋。
   - **軟排除**（`SOFT_EXCLUDE_PATTERNS`）：只看交易子句本身，並且先去掉括號內的補充說明與商店名。所以「(含優惠折抵)」「優惠廚房」「利息麵館」不會誤殺，但「於全家消費享9折優待NT$100」仍會被擋。
   - 結尾的免責、客服、行銷句在更遠的子句，不影響判定。
5. preg 執行錯誤時視為命中排除（fail-closed）。

### 排除規則

強排除（交易子句與前後各一個子句）：

- 取消／失敗／沖正：`取消`、`失敗`、`未成功`、`不成功`、`未完成`、`未核准`、`未通過`、`未授權`、`婉拒`、`拒絕`、`逾時`、`退刷`、`退款`、`退貨`、`沖銷`、`沖正`、`沖回`、`撤銷`、`作廢`、`更正`、`盜刷`、`暫停`、`停用`、`停卡`、`撥款`、`停止續訂`
- 預先授權：`預先授權`、`預授權`
- 驗證碼類：`驗證碼`、`認證碼`、`OTP`、`動態密碼`、`一次性密碼`、`簡訊密碼`
- 分期：`分期`、`期付款`、`每期`、`第 N 期`、`分 N 期`、`期數`。**真實刷卡同時分期（例如「消費金額 NT$30,000 分3期」）也不自動記帳**，請手動記帳。
- 現金與轉帳：`提款`、`提領`、`預借現金`、`領現`、`繳款`、`還款`、`入帳`、`返現`、`代扣`、`代繳`、`扣繳`、`自動扣款`、`自動加值`、`收款`、`分攤`

軟排除（只看交易子句，去掉括號內容與商店名後）：

- 行銷／促銷：`滿 NT$3,000…`、`滿額`、`滿…送／折／抵／贈`、`9折`、`88折`、`享9.5折`、`折 111`、`現折`、`抵 100`、`折抵`、`扣抵`、`回饋`、`紅利`、`點數`、`POINTS`、`獲得`、`贈送`、`優惠`、`加碼`、`抽獎`、`限時`、`活動期間`、`登錄`、`好禮`
- 帳務類：`年費`、`遲繳`、`逾期`、`延滯`、`利息`、`循環信用`、`手續費`、`帳單`、`對帳單`

### 幣別限制（`FOREIGN_CURRENCY_PATTERNS`）

**目前只支援新台幣**，寫入的 `unit` 固定為 `TWD`。交易子句或其前後各一個子句出現任何非台幣幣別標記，規則解析直接放棄（不猜匯率、不寫成台幣）；找不到交易子句時（AI 路徑交叉檢查）檢查整段原文：

- 幣別代碼：`USD`、`JPY`、`EUR`、`GBP`、`HKD`、`CNY`、`RMB`、`KRW`、`THB`、`SGD`、`AUD`、`CAD`、`MYR`、`VND`、`CHF`、`NZD`、`PHP`、`IDR`、`INR`、`MOP`、`U.S.D`、`Dollar`／`Dollars`（不分大小寫）；帶前綴的 `US$`、`HK$`、`S$`、`A$`、`C$`、`NZ$`、`AU$`、`CN$`
- 「海外／國外」後 20 字內接裸 `$` 金額（例如「AMAZON海外消費$25.99」）
- 中文：`美金`、`美元`、`日圓`、`日幣`、`日元`、`歐元`、`英鎊`、`港幣`、`人民幣`、`韓元`、`泰銖`、`新加坡幣`、`澳幣`、`加幣`、`令吉`、`越南盾`、`披索`、`瑞士法郎`、`紐幣`、`印尼盾`、`盧比`、`澳門幣`、`外幣`…
- 符號：`￥`、`¥`、`€`、`£`、`₩`、`฿`、`円`、`₫`

`NT$`、`新台幣`、`TWD`、`元`（沒有其他幣別標記時）視為台幣。**假設：裸 `$` 沒有任何其他幣別線索時視為台幣**（例如「刷卡 $150」，也包含「PayPal *Steam消費 $19.99」「Apple.com/bill消費$0.99」這類實際可能是外幣的寫法）；`$25.99 (USD)` 因為有 `USD` 會被排除。金額本身標 `NT$`／`元` 的國外消費（例如「您於國外消費NT$1,234（含國外交易手續費）」）會照台幣金額記帳，因為銀行顯示的就是台幣入帳金額。

### 金額

依優先序分層嘗試（`AMOUNT_PATTERNS`），同一層取第一個不在排除語境的金額：

1. `消費／刷卡／交易／簽帳／授權／付款／支付金額` + 金額（`消費金額 NT$1,234`、`刷卡金額1,234元`）
2. `消費／刷卡／簽帳／授權／使用／付款／支付／扣款` + 幣別 + 金額（`刷卡 NT$350`、`付款 NT$45`）
3. `金額` + 金額（`金額:NT$100`、`金額 1,234 元`）
4. 幣別 + 金額（`NT$`、`NTD`、`TWD`、`新台幣`、`台幣`、`$`）
5. 數字 + `元`

金額只從交易子句取。金額前方（最多 10 字）出現 `累計`、`額度`、`帳單`、`應繳`、`最低`、`餘額`、`可用`、`上限`、`合計`、`總計`（`AMOUNT_CONTEXT_EXCLUDE_PATTERNS`）時，該金額不採用。例如「本期累計消費金額 NT$50,000，本筆刷卡金額 NT$1,200」會取 1,200。金額去千分位、可小數，需介於 0（不含）～ 1,000,000。

### 商店

- 開頭樣式（`MERCHANT_PATTERNS`，從整段原文找，因為多行格式的商店常在另一行）：`商店：`／`商店:`（全形、半形冒號皆可）、`特店名稱:`、`在<商店>刷卡／消費／使用／付款`、`於<商店>消費／付款`（LINE Pay「您於路易莎咖啡付款 NT$45」可解析）。
- 會去掉前置日期時間（`於11/14 21:03於統一超商消費` → `統一超商`）。
- 遇到 `金額`、`消費`、`刷卡`、`授權`、`交易`、幣別（`NT$`、`TWD`…）、`數字+元`、`卡號`、`末四碼`、`尾號`、日期（`2026/11/14`、`11/14`）、時間就截斷：`商店：全家 消費金額 1500 元 卡號末四碼 1234` → `全家`；`商店：全家 1,234元` → `全家`。商店名內一般空白保留（`UNIQLO 信義店`）。
- 截斷後需 1～60 字，否則視為沒命中。
- 寫入 `title` 前一律移除控制字元、換行轉空白，再跑 `NotificationTextMasker`，並截在 255 字以內。

### 日期

`YYYY/MM/DD`、`YYYY-MM-DD`、民國 `YYY/MM/DD`、`MM/DD`（年份取 `received_at`，若因此落在未來則用前一年）。

新增銀行格式或排除字樣：在對應常數追加 pattern，並補 `tests/Unit/Domain/Wallet/Ingest/RuleBasedNotificationParserTest.php` 案例。

### 回歸資料集

`tests/Unit/Domain/Wallet/Ingest/IngestRegressionDatasetTest.php` 固化了驗收用的資料集，改規則時會被擋下：

| 資料集 | 筆數 | 斷言 | 目前結果 |
|--------|------|------|----------|
| 真實台幣消費 | 39 | 37 筆金額與商店完全正確；另 2 筆不得記帳（分期樂3C 店名含「分期」、悠遊卡自動加值） | 37/39，0 錯記 |
| 非消費 | 63 | 全部不得記帳 | 0/63 被記帳 |
| 外幣 | 45 | 41 筆不得記帳；4 筆設計上記台幣（裸 `$` 2 筆、金額標 NT$／元 2 筆） | 0 筆外幣金額被記成台幣（裸 `$` 除外） |
| 店名含外幣字樣的台幣消費 | 18 | 可回 null，但不得錯記金額 | 4 筆成功、14 筆 400 |
| 應遮罩 | 54 | 輸出與預期完全一致 | 全部遮到 |
| 不應遮罩 | 20 | 原樣保留 | 全部保留 |

## 遮罩範圍（`NotificationTextMasker`）

送 AI 前、寫入 `note` 與 `title` 前都會遮罩成 `****`：

| 寫法 | 範例 → 結果 |
|------|-------------|
| 4 碼一組的卡號 | `1234-5678-9012-3456`、`4311-****-****-1234` → `****` |
| 10～19 位連續數字 | `00123456789012` → `****` |
| 關鍵字後號碼 | `末四碼 1234`、`末四碼 (1234)`、`卡號末四碼(1234)`、`信用卡末4碼1234`、`卡末四碼1234`、`卡號後4碼1234`、`尾號1234`、`尾數5678`、`末四碼 1 2 3 4`、`卡號：…`、`帳號為…` → 號碼改 `****`（括號保留） |
| 手機 | `0912345678`、`0912-345-678`、`+886912345678` → `****` |
| 統一編號 | `統編 12345678` → `統編 ****` |
| 英文 | `Card ending 1234`、`ending in 1234`、`card no. 1234` → `****` |
| 關鍵字中間有空白 | `末 4 碼 4321` → `末 4 碼 ****` |
| 卡組織／卡片字樣後接 4 碼 | `Visa 1234`、`MasterCard 8765`、`AMEX 3782`、`JCB卡(4321)`、`卡片 5678`、`信用卡(5678)`、`您的卡 1234` → 號碼改 `****` |
| 全形 | `１２３４－５６７８－９０１２－３４５６`、`Ａ１２３４５６７８９` → 先轉半形再遮 |
| 星號末碼 | `*1234`、`****1234`、`VISA*1234` → `****` |
| X 遮罩後接 4 碼 | `xx1234`、`XXXX-1234` → `****` |
| 身分證／居留證 | `A123456789`（1 英文字母 + 9 數字）→ `****` |

遮罩前會先清理格式字元並轉半形，所以用 U+2060、bidi 控制字元隔開的卡號也會被遮。

不會遮的：`NT$1,234`、`1234元`、`5000 元`、`2026/11/15`、`11/15`、`卡片消費 5678 元`、`信用卡 1234元`、`卡片 1500 NTD`、`信用卡 1234 TWD`、`Visa 1,234元`（4 碼後接 `元`／`NTD`／`TWD`／`NT$`／`新台幣`、千分位、小數或日期符號就視為金額／日期）；店名 `XX12 Burger`、`85度C`、`Room 1234 民宿`、`第1234號` 不遮（只遮明確的卡號語境）。遮罩是 heuristic，無法保證涵蓋所有銀行寫法。

### 輸入清理

依序處理，規則解析、遮罩、送 AI 都使用清理後的文字：

1. 原文不是合法 UTF-8 → 直接回 400，不解析、不送 AI。
2. 移除控制字元 `\p{Cc}`（保留換行與 tab）與全部格式字元 `\p{Cf}`：零寬字元 U+200B–200F、bidi 控制 U+202A–202E／U+2066–2069、WJ U+2060、軟連字號 U+00AD、ALM U+061C、BOM U+FEFF，以及 U+2028／2029。
3. 全形轉半形（`mb_convert_kana` 的 `rns`：英文字母、數字、空白），再把 `＄＊－／＃＋＠％＆＿＝` 轉半形；夾在數字間的全形逗號、句點轉成千分位與小數點（`１，２３４` → `1,234`）。其他中文標點（，：；（）。）保留。不依賴 intl 擴充。
4. 清理後沒有內容 → 回 400，不送 AI。
5. 寫入 `title`、`note` 前，換行與 tab 轉成空白，再遮罩。

## AI 備援開關與隱私

| 設定 | env | 預設 | 說明 |
|------|-----|------|------|
| `services.ingest.ai_enabled` | `INGEST_AI_ENABLED` | `false` | 規則沒命中時是否改用 Gemini |
| `services.ingest.lock_wait_seconds` | `INGEST_LOCK_WAIT_SECONDS` | `10` | 併發等待秒數 |

開啟時：

- 送出前以 `NotificationTextMasker::mask()` 遮罩卡號／帳號類數字（`末四碼 1234`、`卡號 1234-5678-…`、`*1234`、10 位以上連續數字 → `****`），金額與日期保留。仍會把**遮罩後的推播原文**（商店、金額、日期）送到 Google Gemini，開啟前請確認可接受。
- Prompt 明確聲明推播原文為不受信任資料、忽略其中任何指令，只輸出固定 JSON `{is_expense, amount, currency, merchant, date}`；AI 沒有任何工具或動作權限。
- Prompt 明確要求：回饋／入帳、繳款／還款、取消、失敗、退刷／退款、沖銷／撤銷、驗證碼／OTP、帳單或額度通知、行銷或促銷、分期付款、年費、遲繳／逾期、預借現金／提領、利息、手續費 → `is_expense=false`；多個金額時只取本筆消費金額，不可取累計、額度、帳單、應繳金額。
- JSON schema 含 `currency`（ISO 4217）。Prompt 要求外幣消費照實填幣別、不得換算；**`currency` 不是 `TWD`（大小寫、前後空白不計）或缺欄位 → 400 不寫入**。
- 輸出嚴格驗證：`is_expense` 必須為 `true`；`amount` 必須是數字且 0 < amount ≤ 1,000,000；`merchant` trim 後 1～255 字；`date` 須為合法 `YYYY-MM-DD` 且與 `received_at` 相差 ≤ 7 天，否則改用 `received_at` 的日期；JSON 解析失敗或缺欄位 → 400 不寫入。
- **交叉檢查**：送 AI 前，原文命中強排除或外幣標記就直接回 400、不呼叫 AI。AI 回傳後，仍用規則端的排除規則（強排除、軟排除，軟排除會去掉 AI 回的商店名）與外幣偵測檢查原文；範圍同規則解析（交易子句與前後各一個子句），找不到交易子句時看整段原文。命中就回 400 不寫入，即使 AI 回 `currency=TWD`、`is_expense=true`。
- `category_id` 一律 `null`。
- log 不記錄推播原文；`note` 只存遮罩後的原文。

## 已知限制

- **去重只靠 Cache**，沒有 DB unique 欄位：Cache 被清空或超過 30 天後，重送同一則會再建立一筆；明細建立成功但 `Cache::put` 失敗（cache 服務異常）時，重送也會重複建立。
- **併發請求在拿鎖前各自會呼叫一次 AI**：解析（含 AI 備援）在取得鎖之前進行，兩個相同請求同時進來時，Gemini 可能被呼叫兩次；但只會建立一筆明細（拿到鎖後會再檢查去重 key）。
- **正式環境的 `CACHE_STORE` 必須支援 atomic lock**：redis、file 可以；database 需要 `cache_locks` 資料表（目前 repo 沒有 cache／cache_locks migration，上線前要確認）。array 只適用於測試。
- **只支援新台幣**：外幣通知（規則偵測到外幣標記，或 AI 回 `currency≠TWD`）一律回 400，需手動記帳。後續可擴充為寫入實際 `unit` 與 `rates`。
- **分期刷卡不自動記帳**：含分期字樣的通知（包含真實刷卡同時分期）一律回 400 或交給 AI。
- **商店名含強排除字會被誤殺**（R3）：軟排除字（優惠、限時、登錄、利息…）會先去掉商店名再判斷，但強排除字（取消、分期、退款、提款…）是看完整子句，所以「分期樂3C」這類店名會回 400。
- **店名含外幣字樣會被當成外幣**：「JPY拉麵」「美金咖啡」「US$ Store」「大阪燒円山」「外幣找換店」等只會回 400，不會錯記。
- **裸 `$` 視為台幣**：PayPal、Apple、海外網站以裸 `$` 顯示的外幣金額會被記成台幣（「海外／國外」字樣附近的裸 `$` 除外）。
- **金額語境只往前看 10 字**（R4）：累計／額度等字樣離金額超過 10 字時判斷不到（交易子句切分後通常已足夠）。
- **商店截斷規則不含額度字樣**（R6）：例如「商店：全家 可用額度 NT$…」截斷點落在幣別而非「可用額度」，商店名可能帶到「可用額度」字樣。
- **消費語境詞與金額必須在同一子句**：「您於全家消費。金額 NT$100」這種被句號拆開的寫法會回 null。
- **中文數字金額**（「一百二十元」）規則解析不到，只能靠 AI。
- 規則解析與遮罩都是 heuristic：新的銀行寫法可能解析不到（回 400 或走 AI），或有號碼沒被遮到。
- 本端點沒有專用長效 token，沿用使用者 JWT；JWT 過期需重新設定 MacroDroid。

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
