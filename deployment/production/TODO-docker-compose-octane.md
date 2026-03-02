# 正式環境部署 TODO（Docker Compose + 可選 Octane）

## 0）基線盤點（參考舊專案）
- [x] 檢視 `/Users/roy/project/wallet/deployment/production/docker-compose.yml` 的服務拓樸（`php`、`nginx`、`redis`、`mysql`）
- [x] 檢視舊版 `entrypoint.sh`、`supervisor` queue worker、`crontab` 策略（每分鐘 `schedule:run`）
- [x] 確認哪些舊行為要 1:1 保留（queue、cron、port、volume 佈局、nginx 設定）

## 1）目標架構決策
- [x] 執行模式確定：`web (octane) + nginx 反向代理`
- [x] 服務拆分確定：至少拆成 `web` 與 `worker` 兩個容器
- [x] queue backend 確定：使用 `redis`
- [x] 決定資料庫策略：外部託管 DB（compose 不建 mysql）
- [x] scheduler 併入 worker（由 supervisor 同容器管理）

## 2）環境變數與密鑰映射
- [x] 建立 `wallet-v2` 正式環境 env 範本（`PROJECT_NAME`、路徑、port、timezone）
- [ ] 將舊部署 env 映射到 Laravel 12 key（queue、cache、redis、db、app url）
- [x] 補齊 v2 新增 key（`LINE_BOT_*`、`GEMINI_*`、`EXCHANGERATE_*`、通知服務 key）
- [ ] 定義密鑰注入方式（env file、CI secret、docker secrets）

## 3）Docker Compose 初版（wallet-v2）
- [x] 建立 `deployment/production/docker-compose.yml`（基礎服務）
- [x] 加入 `web` service（Laravel Octane）
- [x] 加入 `worker` service（`php artisan queue:work --queue=...`）
- [x] 加入 `nginx` service（Laravel public root vhost）
- [x] 加入 `redis` service（持久化 volume + healthcheck）
- [x] 若使用外部 DB，則移除 compose 內 `mysql`；否則保留並設置 volume
- [x] worker 管理策略確定：使用獨立 worker container
- [x] 補齊核心服務 restart policy 與 healthcheck

## 4）Octane 導入路徑（目前尚未安裝）
- [ ] 安裝 Octane：`composer require laravel/octane`
- [ ] 安裝伺服器（擇一）：Swoole 或 RoadRunner
- [ ] 初始化 Octane：`php artisan octane:install`
- [x] 新增 Octane service 定義並由 nginx 反向代理
- [ ] 補齊 Octane 調校參數（`OCTANE_SERVER`、worker 數、max requests）
- [x] 驗證 Octane 與 queue/scheduler 分離（避免職責混跑）

## 5）Entrypoint / 行程管理
- [x] 將舊版 `entrypoint.sh` 按 Laravel 12 需求移植（權限、optimize、migration 策略）
- [ ] 僅保留可重入（idempotent）啟動步驟，避免容器啟動時做高風險操作
- [x] `web` 容器啟動 Octane（不跑 queue worker）
- [x] `worker` 容器啟動 queue worker（連到 redis）
- [x] 確保 scheduler trigger 存在（已併入 worker supervisor）

## 6）Nginx 與 TLS
- [x] 建立 `wallet-v2` 網域對應的 nginx 設定與 upstream（`php-fpm` 或 `octane`）
- [ ] 加入靜態資源快取與基礎安全標頭
- [x] 規劃 TLS 憑證掛載結構（憑證檔不入版控）
- [ ] 驗證 API 所需的 body size / timeout 設定

## 7）資料與 Queue 安全檢查
- [ ] 確認正式環境 queue driver（建議 `redis`）
- [ ] 確認 failed jobs table 與 retry 策略
- [ ] 驗證排程指令是否符合 production-only 條件
- [ ] 確認已無舊專案執行期依賴（runtime relay）

## 8）驗證計畫
- [ ] 執行 `docker compose config` 檢查配置正確性
- [ ] 執行冷啟動（全新 volumes）與熱重啟測試
- [ ] 執行應用健康檢查與關鍵 API smoke tests
- [ ] 執行 queue 投遞/消費 smoke tests
- [ ] 執行 scheduler smoke test（`schedule:run`）
- [ ] 確認 log 路徑與輪替策略

## 9）上線計畫
- [ ] 先建立 staging compose，使用接近 production 的環境驗證
- [ ] 制定切換步驟與 rollback 計畫
- [ ] 產出維運 runbook（重啟、queue drain、worker scale）
- [ ] 文件化最終 deploy/upgrade 指令
