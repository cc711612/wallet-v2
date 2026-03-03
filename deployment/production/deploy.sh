#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"

# 從 .env 讀取 PROJECT_NAME，fallback 到 wallet-v2
PROJECT_NAME=$(grep -E '^PROJECT_NAME=' "$ENV_FILE" 2>/dev/null | cut -d= -f2 | tr -d '[:space:]' || echo "wallet-v2")
PROJECT_NAME="${PROJECT_NAME:-wallet-v2}"

WEB_CONTAINER="${PROJECT_NAME}-web"
COMPOSE_CMD="docker compose --env-file $ENV_FILE -f $SCRIPT_DIR/docker-compose.yml"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()    { echo -e "${GREEN}[deploy]${NC} $*"; }
warning() { echo -e "${YELLOW}[deploy]${NC} $*"; }
error()   { echo -e "${RED}[deploy]${NC} $*"; exit 1; }

# ─── 動作定義 ────────────────────────────────────────────────

do_cache() {
    info "重新建立 cache..."
    docker exec "$WEB_CONTAINER" php artisan config:cache
    docker exec "$WEB_CONTAINER" php artisan route:cache
    docker exec "$WEB_CONTAINER" php artisan view:cache
    info "Cache 完成"
}

do_migrate() {
    info "執行 migrate..."
    docker exec "$WEB_CONTAINER" php artisan migrate --force
    info "Migrate 完成"
}

do_octane_reload() {
    info "Reload Octane workers (graceful)..."
    docker exec "$WEB_CONTAINER" php artisan octane:reload
    info "Octane reload 完成"
}

do_restart_containers() {
    warning "重開所有 container..."
    $COMPOSE_CMD restart
    info "所有 container 重開完成"
}

do_rebuild_image() {
    warning "重建 Docker image..."
    $COMPOSE_CMD up -d --build
    info "Image 重建完成"
}

do_deploy() {
    info "執行標準部署流程 (cache + octane reload)..."
    do_cache
    do_octane_reload
    info "部署完成"
}

# ─── 互動選單 ────────────────────────────────────────────────

show_menu() {
    echo ""
    echo "=============================="
    echo "  wallet-v2 部署工具"
    echo "=============================="
    echo "  1) 標準部署        (cache + octane reload)"
    echo "  2) 重新 cache      (config / route / view)"
    echo "  3) Octane reload   (graceful，不中斷服務)"
    echo "  4) 執行 migrate"
    echo "  5) 重開所有 container"
    echo "  6) 重建 Docker image"
    echo "  q) 離開"
    echo "=============================="
    echo -n "請選擇 [1-6 / q]: "
}

run_menu() {
    while true; do
        show_menu
        read -r choice
        case "$choice" in
            1) do_deploy ;;
            2) do_cache ;;
            3) do_octane_reload ;;
            4) do_migrate ;;
            5) do_restart_containers ;;
            6) do_rebuild_image ;;
            q|Q) info "離開"; exit 0 ;;
            *) warning "無效選項，請重新輸入" ;;
        esac
        echo ""
    done
}

# ─── 進入點 ─────────────────────────────────────────────────
# 支援直接帶參數執行，例如：./deploy.sh cache
# 不帶參數則顯示互動選單

case "${1:-}" in
    deploy)   do_deploy ;;
    cache)    do_cache ;;
    reload)   do_octane_reload ;;
    migrate)  do_migrate ;;
    restart)  do_restart_containers ;;
    rebuild)  do_rebuild_image ;;
    "")       run_menu ;;
    *)        error "未知指令: $1\n用法: $0 [deploy|cache|reload|migrate|restart|rebuild]" ;;
esac
