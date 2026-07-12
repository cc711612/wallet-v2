# wallet-v2 優化計畫

> 產生日期：2026-07-11。來源：程式碼審查 + 部署設定審查（唯讀，未改任何檔案）。
> 完整審查報告：scratchpad `audit-code.md`、`audit-deploy.md`（session 暫存，本檔為正式彙整版）。
> 每項附影響程度與出處（檔案:行號），建議依 Phase 順序執行。

---

## Phase 1：安全與資料正確性（建議最優先，皆為高/中風險）

### 1.1 LINE Webhook 缺簽章驗證 【✅ 已修（2026-07-12）】
- 修法：新增 `app/Http/Middleware/VerifyLineSignature.php`（HMAC-SHA256 驗 `X-Line-Signature`，用 `hash_equals` 比對；channel_secret 未設定時一律 401 + log），掛上 `/webhook/line` 與 `/webhook/line/notify` 兩條路由。
- 測試：`tests/Feature/Api/LineWebhookSignatureTest.php` 6 案例（無 header、錯簽章、對簽章、無 secret、notify 路由 ×2）全綠。
- Codex 複查：簽章演算法、hash_equals、路由掛載、無旁路皆確認 OK。

### 1.2 FCM 推播網域/圖示/bot id 寫死正式環境字串 【✅ 已修（2026-07-12）】
- 修法：新增 `config/services.php` easysplit 區塊（`EASYSPLIT_APP_URL` 等 4 個 env key，default 為現行正式值，行為不變），`NotificationJobService` 改讀 config；`.env.example` 同步。
- Codex 複查：config 化後與原寫死值完全等價（含尾斜線處理）。
- 遺留：`app/Support/JwtTokenService.php:18,39` 的 JWT `aud` claim 也寫死同網域——屬 JWT 簽發邏輯、改動影響 token 驗證，**未動**，列入待辦評估（改 config 需同步確認發出去的 token 相容性）。

### 1.3 明細 update 未包 DB transaction 【✅ 已修（2026-07-12）】
- 修法：`WalletDetailService::update()` 三步寫入包進 `DB::transaction()`，回傳值與例外語意不變（Codex 確認）。
- 測試：`tests/Unit/Domain/Wallet/Services/WalletDetailServiceTest.php` 新增 2 案例（同一 transaction 內執行、中途拋例外中止後續寫入）。註：受限 fake repository，未做真實 DB rollback 斷言（要 sqlite migration 才驗得到，暫緩）。

### 1.4 補齊核心流程測試 【中｜品質防線】
- 出處：`tests/` 僅 7 個測試檔。
- 缺口：分攤拆帳的 `update` / `checkout` / `uncheckout` / `destroy` 完全無測試；FCM 發送失敗情境無測試。
- 做法：優先補上述兩塊（正好對應 1.3 與 2.3 的風險），再視情況擴及 `WalletService`。

---

## Phase 2：可靠性（queue / 外部 API）【✅ 全部完成 2026-07-12，Codex 複查通過】

### 2.1 Gemini 呼叫同步阻塞 【✅ 已修（保守修法）】
- 修法：timeout/retry 抽成 config（`config/services.php` + `.env.example`），default 降為 timeout 15s / retry 2 次；429 特殊處理保留（Codex 確認 retry 條件仍限定 rate-limited）。改非同步屬產品決策，未做。

### 2.2 5 個 Job 顯式宣告 `$tries` / `$backoff` 【✅ 已修】
- 修法：五個 Job 皆宣告 `$tries = 3` + `$backoff`，不再依賴 worker CLI 參數。附 `tests/Unit/Jobs/JobRetryConfigurationTest.php`。

### 2.3 FCM 發送失敗處理 【✅ 已修】
- 修法：比照 GeminiService 加 retry、log 帶 HTTP status/body 摘要；FCM 內部 retry 失敗只 log 不拋（Codex 確認不會與 job $tries 相乘重試）。
- firebase key 快取：Codex 一次複查抓到「靜態快取無失效條件，key rotation 後長駐 worker 用舊 key」→ 已改以 filemtime 當失效條件（`NotificationJobService::loadFirebaseParams()`）。

### 2.4 移除 job 內 HTTP loopback 【✅ 已修】
- 修法：`CreateWalletDetailJobService` 改直接呼叫 `WalletDetailService::create()`。Codex 確認新舊路徑殊途同歸（原 API 路徑最終也是進同一 service），無 middleware/validation 被跳過造成的行為差異。
- 已知限制（Codex 低風險發現）：`CreateWalletDetailJobServiceTest` 用 recording fake，未執行真實 repository 的 select_all 展開/pivot 寫入，回歸保護有限；補真實 DB 整合測試（sqlite migration）列入待辦。

### 2.5 成員全選明細同步 N+1 【✅ 已修】
- 修法：改 `insertOrIgnore` 批次寫入 pivot（Codex 確認唯一鍵 `wallet_user_id + wallet_detail_id`、表無 timestamps，無覆蓋風險；without-detaching 語意維持）。

### Phase 2 驗證證據
- 新增測試 5 檔 21 tests 全綠；與 Phase 1 及既有測試合跑 38 tests / 91 assertions 全綠。
- 主對話另修一個實作 agent 引入的問題：兩個測試檔重複宣告 `RecordingWalletDetailRepository`（單跑各綠、合跑 fatal），已改名解衝突。

---

## Phase 3：部署——版本化與正確性（高風險）

### 3.1 前端建置沒進部署流程 【✅ 確認不需要（2026-07-11）】
- 查證結果：本專案為純 API，唯一用到 `@vite` 的 `welcome.blade.php:14` 有 `file_exists(public_path('build/manifest.json'))` 保護，manifest 不存在時走內嵌 CSS fallback，不會拋例外。不需把 npm build 加進部署流程。

### 3.2 Image 是空殼，程式碼靠 bind mount 覆蓋 【維持現狀（使用者決策 2026-07-11）】
- 決策：使用者部署習慣是「手動 git pull → 跑 deploy.sh」，維持 bind mount 模式，不改 image-based。
- 已做的配套：`deploy.sh` 標準部署流程加入 `composer install` 步驟（composer.lock 更新後不再需要手動裝依賴）；opcache 改 `validate_timestamps=1`（見 4.5，否則 git pull + octane:reload 會吃到舊 bytecode）。

### 3.3 全清空重部署會刪 Redis 資料 【✅ 已修（2026-07-11）】
- 修法：`deploy.sh` 的 `clean`（選項 7）改為 `down --rmi local`，**不再刪 volume**，redis-data 保留；刪 volume 拆成獨立的危險選項 8（`wipe-volumes`），需要 y/N 確認 + 輸入專案名稱雙重確認。
- 未做：image 版本 tag / rollback runbook（bind mount 模式下 image tag 意義有限，rollback 靠 git；runbook 留待 TODO 第 9 節）。

### 3.4 修正 TODO-docker-compose-octane.md 【✅ 已修（2026-07-11）】
- 第 4 節改為「已完成，採用 FrankenPHP」，並同步勾銷第 6 節（靜態資源/安全 header、TLS 架構確認）與第 8 節 log 輪替。

---

## Phase 4：部署——效能與加固（多數在 TODO 文件已規劃、未完成）

### 4.1 Nginx 靜態資源與壓縮 【✅ 已修（2026-07-11）】
- 修法：`nginx.conf` 開 gzip（JSON/JS/CSS 等類型，comp_level 5）；compose 把 `public/` 唯讀掛進 nginx（`/var/www/public`），`wallet-v2.conf` 靜態副檔名 location 由 nginx 直出 + `expires 7d`。

### 4.2 Nginx TLS 與安全 header 【✅ 已修（2026-07-11）】
- 架構確認：TLS 由 VM 上的外層 nginx 終結，容器內不做 443。已補 `X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy` header（HSTS 建議設在外層 TLS nginx）。

### 4.3 Queue worker 強化 【✅ 部分已修（2026-07-11）】
- 已修：三個 queue:work 指令加 `--memory=256`（`laravel-worker.ini:3,15,27`）；`deploy.sh` worker reload 改為 `queue:restart`（graceful，job 跑完才退）+ 單獨 restart scheduler。
- 未做（需上機驗證再動）：supervisord 改非 root 執行——bind mount 的 `storage/` 權限屬 host 使用者，貿然改 user 可能讓 worker 寫不了 log，需在主機上驗證權限後再改。`numprocs` 維持 1，出現佇列堆積再調。

### 4.4 docker-compose 資源限制與 log rotation 【✅ 已修（2026-07-11）】
- 修法：四個 service 統一 `logging`（json-file、max-size 10m、max-file 3，用 YAML anchor）；加 `mem_limit`（web/worker 1g、nginx 256m、redis 512m，皆可用 env 覆蓋：`WEB_MEM_LIMIT` 等）；nginx 補 healthcheck（`wget --spider /up`）。
- 注意：mem_limit 預設值是保守估，請依 VM 實際記憶體調整 env。

### 4.5 opcache / JIT 與 Dockerfile 收尾 【✅ 已修（2026-07-11）】
- **重要修正**：`validate_timestamps=0` 在 bind mount + `octane:reload` 部署流程下會吃到舊 bytecode（opcache SHM 不因 reload 失效），已改 `validate_timestamps=1` + `revalidate_freq=2`（Octane worker 模式 stat 開銷可忽略）。
- 開 JIT：`opcache.jit=tracing` + `jit_buffer_size=64M`（PHP 8.4 實測 ini 生效）。
- Dockerfile `CMD` 從 `/bin/bash` 改為 `entrypoint-web.sh`。
- 未做：base image 鎖 patch 版/digest（收益低，暫緩）。

### 4.6 低影響備忘
- ✅ `nginx.conf` log_format 已加 `$request_time`、`$upstream_response_time`（2026-07-11）。
- ✅ composer.lock 更新問題已解：`deploy.sh` 標準部署與 `composer` 子指令會跑 `composer install`（2026-07-11）。
- 未做：nginx rate limiting（`limit_req`）——外層 VM nginx 做更合適。
- 未做：`wallet_details` 索引（`(wallet_id, date)`、`(wallet_id, checkout_at)`），量大後評估。
- 未做：`NotificationJobService.php:38-42` firebase key 檔案快取（歸入 Phase 2 一起做）。
- 未做：`laravel/telescope` 正式環境曝露確認。

---

## 檢查過、無明顯問題的部分（不用花時間）

- 架構分層：Domain-Driven 分層乾淨，Controller 皆薄，無胖 Controller。
- Eager loading：明細列表/查詢皆有 `with()`，請求路徑無 N+1。
- 佇列設計：LINE webhook、FCM、成員註冊、明細建立皆已非同步化。
- `GeminiService::requestGemini()` 的 retry/429/timeout 處理完善，可當其他外部 API 呼叫的範本。
- Composer 依賴：PHP 8.2+ / Laravel 12，無過期或棄用套件。

## 建議執行順序摘要

| 順序 | 內容 | 狀態 |
| --- | --- | --- |
| 1 | 1.1 LINE 簽章驗證、1.2 FCM 寫死網域、1.3 update transaction | ✅ 完成 2026-07-12（Codex 複查通過） |
| 2 | 3.3 移除 `down --volumes` 誤觸、3.4 修 TODO 文件 | ✅ 完成 2026-07-11 |
| 3 | 3.1 前端建置（確認不需要）、3.2 部署模式（決策維持 bind mount + 配套） | ✅ 結案 2026-07-11 |
| 4 | Phase 2 queue/外部 API 可靠性 | ✅ 完成 2026-07-12（Codex 複查通過）；1.4 既有測試修復另開背景任務 |
| 5 | Phase 4 nginx/opcache/compose 加固 | ✅ 完成 2026-07-11（除 4.3 非 root、4.5 鎖版等標註「未做」項） |

## Codex 二次審查修復（2026-07-12）

Codex reviewer 對上述改動找出 3 個真問題，皆已修復並重新驗證（`nginx -t`、`php -l`、PHP 8.4 容器實測 ini）：

1. ✅ `wallet-v2.conf` 靜態 location 內用了 `add_header` 會使 server 層安全 header 不被繼承 → location 內重複列出三個安全 header。
2. ✅ `X-Forwarded-Proto $scheme` 會把外層 nginx 的 https 覆蓋成 http → 改用 map：優先沿用外層傳入的 `$http_x_forwarded_proto`，空值才 fallback `$scheme`。同時發現 `bootstrap/app.php` 沒設 TrustProxies（Laravel 會直接忽略 X-Forwarded-*），已補 `trustProxies`（僅信任私有網段）。
3. ✅ `opcache.revalidate_freq=2` 在間隔內仍可能吃舊 bytecode → 改 `revalidate_freq=0`。

## 既有測試套件紅燈（2026-07-12 發現，待修——與本次改動無關）

- `tests/Feature/Api/WalletDetailStoreContractTest.php:44` 的 `ApiFakeWalletDetailRepository` 未實作 commit `7e39982` 新增的 `replaceSplits()` 介面方法，**整個測試套件 fatal error 無法啟動**。
- 排除該檔後：Unit 17 tests 中 8 errors、Feature 10 tests 中 3 failures——在乾淨 HEAD 上結果相同，屬 `7e39982` 分攤拆帳功能上線時未同步修測試。
- 建議併入 Phase 1.4 一起處理（先讓套件能跑，才有辦法給後續改動當驗證基準）。

## 部署注意事項（2026-07-11 改動後首次上線前必讀）

1. 這批改動涉及 compose 設定與 opcache ini，需要 `./deploy.sh restart`（或 rebuild）讓新 mount/設定生效，光跑 `deploy` 不夠。
2. `mem_limit` 預設值（web/worker 1g）請先確認 VM 記憶體餘裕，不夠就用 env（`WEB_MEM_LIMIT` 等）調整，避免 OOM kill。
3. nginx 新增了 `${PROJECT_PATH}/public` 唯讀掛載，`docker compose config` 已驗證，但首次啟動請確認 nginx container 起得來（`nginx -t` 已在本機以同版本 image 驗證通過）。
4. opcache 改 `validate_timestamps=1` 後，git pull + `octane:reload` 即可生效新程式碼，不再需要重啟容器。
