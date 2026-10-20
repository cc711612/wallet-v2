#!/bin/bash
# deploy.sh 的本機測試（不碰 docker 實體、不需要 wallet 環境）：
#   1) PROJECT_NAME 解析（各種引號 / 空白 / 註解寫法）
#   2) apply-env 的呼叫順序與參數（PATH 前面放假 docker，記錄參數）、健康檢查失敗時的結束碼
# 用法：bash deployment/production/test-deploy.sh
set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fail=0

ok()  { printf 'PASS  %s\n' "$1"; }
bad() { printf 'FAIL  %s\n' "$1"; fail=1; }

# ── 1) PROJECT_NAME 解析 ──────────────────────────────────────
parse_case() { # $1 label, $2 .env 內容（printf 格式）
    local d="$TMP/parse"
    rm -rf "$d"; mkdir -p "$d"
    cp "$HERE/deploy.sh" "$d/deploy.sh"
    printf '%b' "$2" > "$d/.env"
    local got
    got=$(bash -c 'source "$1"; printf "%s|%s" "$PROJECT_NAME" "$WEB_CONTAINER"' _ "$d/deploy.sh")
    if [ "$got" = "wallet-v2|wallet-v2-web" ]; then ok "PROJECT_NAME: $1"; else bad "PROJECT_NAME: $1 -> '$got'"; fi
}
parse_case 'plain'            'PROJECT_NAME=wallet-v2\n'
parse_case 'double quoted'    'PROJECT_NAME="wallet-v2"\n'
parse_case 'single quoted'    "PROJECT_NAME='wallet-v2'\n"
parse_case 'surrounding space' 'PROJECT_NAME =   wallet-v2   \n'
parse_case 'quoted + space'   'PROJECT_NAME=  "wallet-v2"  \n'
parse_case 'trailing comment' 'PROJECT_NAME=wallet-v2   # prod\n'
parse_case 'quoted + comment' 'PROJECT_NAME="wallet-v2" # prod\n'
parse_case 'CRLF'             'PROJECT_NAME="wallet-v2"\r\n'
parse_case 'export prefix'    'export PROJECT_NAME=wallet-v2\n'
parse_case 'last one wins'    'PROJECT_NAME=old\nPROJECT_NAME=wallet-v2\n'
parse_case 'missing -> default' 'TZ=Asia/Taipei\n'
parse_case 'empty -> default'   'PROJECT_NAME=\n'
parse_case 'ignore similar key' 'XPROJECT_NAME=bad\nPROJECT_NAME=wallet-v2\n'

# ── 2) apply-env（假 docker）──────────────────────────────────
BIN="$TMP/bin"; mkdir -p "$BIN"
cat > "$BIN/docker" <<'STUBEND'
#!/bin/bash
echo "docker $*" >> "$FAKE_DOCKER_LOG"
case "$1" in
    ps) printf 'wallet-v2-web\nwallet-v2-worker\nwallet-v2-nginx\nwallet-v2-redis\n' ;;
    inspect) echo healthy ;;
    exec) case "$*" in *curl*) printf '%s' "${FAKE_HEALTH_CODE:-200}" ;; esac ;;
esac
exit 0
STUBEND
chmod +x "$BIN/docker"

run_apply() { # 參數即 apply-env 的參數；輸出 exit code 到 $TMP/rc
    local d="$TMP/apply"
    rm -rf "$d"; mkdir -p "$d"
    cp "$HERE/deploy.sh" "$d/deploy.sh"
    printf 'PROJECT_NAME="wallet-v2"\n' > "$d/.env"
    : > "$TMP/docker.log"
    PATH="$BIN:$PATH" FAKE_DOCKER_LOG="$TMP/docker.log" APPLY_ENV_HEALTH_SLEEP=0 APPLY_ENV_HEALTH_TRIES=3 \
        bash "$d/deploy.sh" apply-env "$@" > "$TMP/out.log" 2>&1
    echo $? > "$TMP/rc"
}
calls() { grep -v -e '^docker ps ' -e '^docker inspect ' "$TMP/docker.log" | sed "s#--env-file [^ ]*\\.env -f [^ ]*docker-compose.yml#--env-file ENV -f COMPOSE#"; }

check_rc0() { # $1 label
    local rc; rc="$(cat "$TMP/rc")"
    if [ "$rc" = 0 ]; then ok "$1: exit 0"; else bad "$1: exit $rc"; fi
}

expect_calls() { # $1 label, $2 expected（多行）
    local got; got="$(calls)"
    if [ "$got" = "$2" ]; then ok "$1"; else bad "$1"; echo "--- expected"; echo "$2"; echo "--- got"; echo "$got"; fi
}

run_apply
check_rc0 "apply-env 預設"
expect_calls "apply-env 預設: 呼叫順序（不 up -d、不 pull/composer/migrate）" "docker exec wallet-v2-web php artisan config:cache
docker exec wallet-v2-web php artisan octane:reload
docker exec wallet-v2-worker supervisorctl restart all
docker exec wallet-v2-web sh -c curl -s -o /dev/null -m 10 -w \"%{http_code}\" \"http://127.0.0.1:\${OCTANE_PORT:-8000}/api/health\""
if grep -qE 'compose|migrate|composer|git ' "$TMP/docker.log"; then bad "apply-env 預設: 不該出現 compose/migrate/composer"; else ok "apply-env 預設: 無 compose/migrate/composer"; fi

run_apply --recreate
check_rc0 "apply-env --recreate"
expect_calls "apply-env --recreate: up -d 在 Laravel 層之前，且不帶 --build/--force-recreate" "docker compose --env-file ENV -f COMPOSE up -d --remove-orphans
docker exec wallet-v2-web php artisan config:cache
docker exec wallet-v2-web php artisan octane:reload
docker exec wallet-v2-worker supervisorctl restart all
docker exec wallet-v2-web sh -c curl -s -o /dev/null -m 10 -w \"%{http_code}\" \"http://127.0.0.1:\${OCTANE_PORT:-8000}/api/health\""

FAKE_HEALTH_CODE=503 run_apply
rc=$(cat "$TMP/rc")
if [ "$rc" != 0 ] && [ "$(grep -c 'api/health' "$TMP/docker.log")" = 3 ]; then ok "健康檢查 503: 重試 3 次後 exit $rc (非 0)"; else bad "健康檢查 503: rc=$rc"; fi

run_apply --bogus
if [ "$(cat "$TMP/rc")" != 0 ] && [ ! -s "$TMP/docker.log" ]; then ok "未知旗標: 非 0 且未呼叫 docker"; else bad "未知旗標"; fi

exit "$fail"
