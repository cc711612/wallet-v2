#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "[deploy] 找不到 $ENV_FILE，請先複製 .env.example -> .env" >&2
    exit 1
fi

# 讀 env 檔的單一 key，解析規則對齊 docker compose：
#   KEY=x / KEY="x" / KEY='x' / 前後空白 / 行尾註解（未加引號時以「空白+#」開頭）/ CRLF / export 前綴
# 同一 key 出現多次取最後一筆（compose 也是後者覆蓋前者）。找不到時輸出空字串。
read_env_value() {
    local file="$1" key="$2" line val
    line=$(grep -E "^[[:space:]]*(export[[:space:]]+)?${key}[[:space:]]*=" "$file" 2>/dev/null | tail -n 1) || true
    line="${line%$'\r'}"
    val="${line#*=}"
    val="${val#"${val%%[![:space:]]*}"}"
    case "$val" in
        \"*) val="${val#\"}"; val="${val%%\"*}" ;;
        \'*) val="${val#\'}"; val="${val%%\'*}" ;;
        \#*) val="" ;;
        *)
            val="${val%%[[:space:]]#*}"
            val="${val%"${val##*[![:space:]]}"}"
            ;;
    esac
    printf '%s' "$val"
}

# 從 .env 讀取 PROJECT_NAME，fallback 到 wallet-v2
PROJECT_NAME="$(read_env_value "$ENV_FILE" PROJECT_NAME)"
PROJECT_NAME="${PROJECT_NAME:-wallet-v2}"

WEB_CONTAINER="${PROJECT_NAME}-web"
WORKER_CONTAINER="${PROJECT_NAME}-worker"
COMPOSE_CMD="docker compose --env-file $ENV_FILE -f $SCRIPT_DIR/docker-compose.yml"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()    { printf "%b\n" "${GREEN}[deploy]${NC} $*"; }
warning() { printf "%b\n" "${YELLOW}[deploy]${NC} $*"; }
error()   { printf "%b\n" "${RED}[deploy]${NC} $*"; exit 1; }

confirm() {
    local prompt="$1"
    echo -n "$prompt [y/N]: "
    read -r answer
    case "$answer" in
        y|Y|yes|YES) return 0 ;;
        *) return 1 ;;
    esac
}

ensure_container_running() {
    if ! docker ps --format '{{.Names}}' | grep -q "^${WEB_CONTAINER}$"; then
        error "容器 ${WEB_CONTAINER} 未啟動，請先執行 rebuild 或 restart"
    fi
}

ensure_worker_running() {
    if ! docker ps --format '{{.Names}}' | grep -q "^${WORKER_CONTAINER}$"; then
        error "容器 ${WORKER_CONTAINER} 未啟動，請先執行 rebuild 或 restart"
    fi
}

# ─── 動作定義 ────────────────────────────────────────────────

do_config_cache() {
    ensure_container_running
    info "重新建立 config cache..."
    docker exec "$WEB_CONTAINER" php artisan config:cache
}

do_cache() {
    do_config_cache
    info "重新建立 route / view cache..."
    docker exec "$WEB_CONTAINER" php artisan route:cache
    docker exec "$WEB_CONTAINER" php artisan view:cache
    info "Cache 完成"
}

do_migrate() {
    ensure_container_running
    info "執行 migrate..."
    docker exec "$WEB_CONTAINER" php artisan migrate --force
    info "Migrate 完成"
}

do_octane_reload() {
    ensure_container_running
    info "Reload Octane workers (graceful)..."
    docker exec "$WEB_CONTAINER" php artisan octane:reload
    info "Octane reload 完成"
}

do_worker_restart() {
    ensure_worker_running
    info "重啟 worker supervisor programs（queue + scheduler）..."
    docker exec "$WORKER_CONTAINER" supervisorctl restart all
}

do_worker_supervisor_reload() {
    ensure_worker_running
    info "Reload worker supervisor programs..."
    docker exec "$WORKER_CONTAINER" supervisorctl reread
    docker exec "$WORKER_CONTAINER" supervisorctl update
    do_worker_restart
    info "Worker supervisor reload 完成"
}

do_restart_containers() {
    warning "重開所有 container..."
    $COMPOSE_CMD restart
    info "所有 container 重開完成"
}

do_rebuild_image() {
    warning "重建 Docker image..."
    $COMPOSE_CMD up -d --build --remove-orphans
    info "Image 重建完成"
}

do_full_redeploy() {
    warning "準備執行全清空重部署（會刪除 compose services、匿名 volume、舊 image）"
    if ! confirm "確定要繼續嗎？"; then
        warning "已取消"
        return
    fi

    warning "停止並移除容器/網路/volume..."
    $COMPOSE_CMD down --volumes --remove-orphans

    warning "刪除專案 image..."
    $COMPOSE_CMD down --rmi local --remove-orphans || true

    info "重新 build 並啟動..."
    $COMPOSE_CMD up -d --build --force-recreate --remove-orphans

    info "全清空重部署完成"
}

do_deploy() {
    info "執行標準部署流程 (cache + octane reload + worker supervisor reload)..."
    do_cache
    do_octane_reload
    do_worker_supervisor_reload
    info "部署完成"
}

# 等容器 healthcheck 轉 healthy（compose 重建後用）；逾時回非 0
wait_container_healthy() {
    local name="$1" tries="${APPLY_ENV_WAIT_TRIES:-30}" sleep_s="${APPLY_ENV_WAIT_SLEEP:-2}" i status
    for ((i = 1; i <= tries; i++)); do
        status=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$name" 2>/dev/null || true)
        case "$status" in healthy|running) return 0 ;; esac
        sleep "$sleep_s"
    done
    return 1
}

# 在 web 容器內打 /api/health（走容器內 Octane port，不依賴 nginx / 對外 port / Host header）
# 重試 APPLY_ENV_HEALTH_TRIES 次、間隔 APPLY_ENV_HEALTH_SLEEP 秒；最後仍非 200 回非 0
do_health_check() {
    local tries="${APPLY_ENV_HEALTH_TRIES:-10}" sleep_s="${APPLY_ENV_HEALTH_SLEEP:-3}" i code="000"
    info "健康檢查 /api/health（最多 ${tries} 次）..."
    for ((i = 1; i <= tries; i++)); do
        code=$(docker exec "$WEB_CONTAINER" sh -c 'curl -s -o /dev/null -m 10 -w "%{http_code}" "http://127.0.0.1:${OCTANE_PORT:-8000}/api/health"' 2>/dev/null || true)
        if [ "$code" = "200" ]; then
            info "健康檢查通過 (200)"
            return 0
        fi
        [ "$i" -lt "$tries" ] && sleep "$sleep_s"
    done
    warning "健康檢查失敗（最後一次 HTTP ${code:-000}）"
    return 1
}

# env 變更生效：不 git pull / composer install / migrate。
#   預設只處理 Laravel 層（${PROJECT_PATH}/.env）：config:cache -> octane:reload -> worker 重啟
#   --recreate 另外處理 compose 層（本目錄 .env：REDIS_PASSWORD / NGINX_BIND_ADDR / port ...）：
#              先 docker compose up -d（只重建設定有變的 service），等 web/worker 就緒，再做 Laravel 層
do_apply_env() {
    local recreate=0 arg
    for arg in "$@"; do
        case "$arg" in
            --recreate) recreate=1 ;;
            *) error "apply-env 未知參數: $arg（可用：--recreate）" ;;
        esac
    done

    info "套用 env 變更（recreate=${recreate}）..."
    if [ "$recreate" = 1 ]; then
        warning "docker compose up -d（只重建設定有變動的 service）..."
        $COMPOSE_CMD up -d --remove-orphans
        wait_container_healthy "$WEB_CONTAINER" || error "${WEB_CONTAINER} 重建後未在時限內就緒"
        wait_container_healthy "$WORKER_CONTAINER" || error "${WORKER_CONTAINER} 重建後未在時限內就緒"
    fi

    do_config_cache
    do_octane_reload
    do_worker_restart
    do_health_check || error "apply-env 失敗：/api/health 非 200"
    if [ "$recreate" = 0 ]; then
        info "未重建容器；若改的是 compose 層變數（REDIS_PASSWORD / NGINX_BIND_ADDR / port / PROJECT_*），請加 --recreate"
    fi
    info "apply-env 完成"
}

usage_text() {
    cat <<EOF
用法: $0 [指令] [選項]
  deploy               標準部署 (cache + octane reload + worker reload)
  cache                重新 cache (config / route / view)
  reload               Octane reload (graceful)
  migrate              執行 migrate
  apply-env [--recreate]
                       env 變更生效（不 pull / composer / migrate）：
                       config:cache -> octane reload -> worker 重啟 -> /api/health，失敗回非 0
                       --recreate：先 docker compose up -d（只重建設定有變的 service），
                                   compose 層變數（REDIS_PASSWORD / NGINX_BIND_ADDR / port）才需要
  restart              重開所有 container
  rebuild              重建 Docker image
  clean                全清空重部署
  不帶參數             互動選單
EOF
}

# ─── 互動選單 ────────────────────────────────────────────────

show_menu() {
    echo ""
    echo "=============================="
    echo "  wallet-v2 部署工具"
    echo "=============================="
    echo "  1) 標準部署        (cache + octane reload + worker reload)"
    echo "  2) 重新 cache      (config / route / view)"
    echo "  3) Octane reload   (graceful，不中斷服務)"
    echo "  4) 執行 migrate"
    echo "  5) 重開所有 container"
    echo "  6) 重建 Docker image"
    echo "  7) 全清空重部署 (down --volumes + rebuild)"
    echo "  8) 套用 env 變更   (config cache + reload + worker 重啟 + 健康檢查)"
    echo "  q) 離開"
    echo "=============================="
    echo -n "請選擇 [1-8 / q]: "
}

run_menu() {
    while true; do
        show_menu
        read -r choice
        choice="${choice//[[:space:]]/}"
        case "$choice" in
            1) do_deploy ;;
            2) do_cache ;;
            3) do_octane_reload ;;
            4) do_migrate ;;
            5) do_restart_containers ;;
            6) do_rebuild_image ;;
            7) do_full_redeploy ;;
            8) do_apply_env ;;
            q|Q) info "離開"; exit 0 ;;
            *) warning "無效選項，請重新輸入" ;;
        esac
        echo ""
    done
}

# ─── 進入點 ─────────────────────────────────────────────────
# 支援直接帶參數執行，例如：./deploy.sh cache、./deploy.sh apply-env --recreate
# 不帶參數則顯示互動選單；被 source 時（測試用）只載入函式，不執行
main() {
    case "${1:-}" in
        deploy)    do_deploy ;;
        cache)     do_cache ;;
        reload)    do_octane_reload ;;
        migrate)   do_migrate ;;
        apply-env) shift; do_apply_env "$@" ;;
        restart)   do_restart_containers ;;
        rebuild)   do_rebuild_image ;;
        clean)     do_full_redeploy ;;
        -h|--help|help) usage_text ;;
        "")        run_menu ;;
        *)
            usage_text >&2
            error "未知指令: $1"
            ;;
    esac
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
