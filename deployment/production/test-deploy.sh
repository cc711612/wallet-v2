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
# FAKE_DOCKER_FAIL：參數含此字串時回 1（模擬該步失敗）
if [ -n "${FAKE_DOCKER_FAIL:-}" ] && [[ "$*" == *"$FAKE_DOCKER_FAIL"* ]]; then exit 1; fi
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

# ── 3) fix-perms（假 docker）─────────────────────────────────
run_fix() { # $1 .env 額外內容；輸出 exit code 到 $TMP/rc
    local d="$TMP/fix" p="$TMP/fix/app"
    rm -rf "$d"; mkdir -p "$d" "$p/storage" "$p/bootstrap/cache"
    : > "$p/.env"
    cp "$HERE/deploy.sh" "$d/deploy.sh"
    printf 'PROJECT_NAME="wallet-v2"\nPROJECT_PATH=%s\n%b' "$p" "${1:-}" > "$d/.env"
    : > "$TMP/docker.log"
    PATH="$BIN:$PATH" FAKE_DOCKER_LOG="$TMP/docker.log" bash "$d/deploy.sh" fix-perms > "$TMP/out.log" 2>&1
    echo $? > "$TMP/rc"
}

run_fix
check_rc0 "fix-perms 預設"
got="$(cat "$TMP/docker.log")"
p="$TMP/fix/app"
case "$got" in
    "docker run --rm --network none --user 0:0 --entrypoint sh -v $p/storage:/fix/storage -v $p/bootstrap/cache:/fix/bootstrap-cache -v $p/.env:/fix/app.env wallet-v2:app -c "*" fix-perms 10001 10001")
        ok "fix-perms 預設: 只掛 storage / bootstrap/cache / .env，UID:GID=10001:10001" ;;
    *) bad "fix-perms 預設: 參數不符"; echo "$got" ;;
esac
if [ "$(grep -c '^docker ' "$TMP/docker.log")" = 1 ]; then ok "fix-perms: 只呼叫一次 docker"; else bad "fix-perms: docker 呼叫次數"; fi

run_fix 'APP_UID=2000\nAPP_GID="2001"\n'
case "$(cat "$TMP/docker.log")" in
    *" fix-perms 2000 2001") ok "fix-perms: 讀 .env 的 APP_UID/APP_GID" ;;
    *) bad "fix-perms: APP_UID/APP_GID 未生效"; cat "$TMP/docker.log" ;;
esac

run_fix 'APP_UID=0\n'
if [ "$(cat "$TMP/rc")" != 0 ] && [ ! -s "$TMP/docker.log" ]; then ok "fix-perms: APP_UID=0 拒絕且未呼叫 docker"; else bad "fix-perms: APP_UID=0"; fi

# ── 4) composer-install / rebuild（假 docker，stdin 關閉以確保非互動）────────
run_cmd() { # $1 子指令；輸出 exit code 到 $TMP/rc
    local d="$TMP/cmd" p="$TMP/cmd/app"
    rm -rf "$d"; mkdir -p "$d" "$p/storage" "$p/bootstrap/cache"
    : > "$p/.env"; echo '{}' > "$p/composer.json"
    cp "$HERE/deploy.sh" "$d/deploy.sh"
    printf 'PROJECT_NAME="wallet-v2"\nPROJECT_PATH=%s\n' "$p" > "$d/.env"
    : > "$TMP/docker.log"
    PATH="$BIN:$PATH" FAKE_DOCKER_LOG="$TMP/docker.log" bash "$d/deploy.sh" "$1" < /dev/null > "$TMP/out.log" 2>&1
    echo $? > "$TMP/rc"
}
calls_short() { grep '^docker ' "$TMP/docker.log" | sed -e "s#--env-file [^ ]*\.env -f [^ ]*docker-compose.yml#--env-file ENV -f COMPOSE#" -e 's# -c .*# -c ...#'; }

run_cmd composer-install
check_rc0 "composer-install"
p="$TMP/cmd/app"
expect_ci="docker run --rm --user 0:0 --entrypoint sh -e COMPOSER_ALLOW_SUPERUSER=1 -e COMPOSER_HOME=/tmp/composer-home -e COMPOSER_CACHE_DIR=/composer-cache -v wallet-v2-composer-cache:/composer-cache -v $p:/var/www/html -w /var/www/html wallet-v2:app -c ...
docker run --rm --network none --user 0:0 --entrypoint sh -v $p/storage:/fix/storage -v $p/bootstrap/cache:/fix/bootstrap-cache -v $p/.env:/fix/app.env wallet-v2:app -c ..."
if [ "$(calls_short)" = "$expect_ci" ]; then ok "composer-install: 一次性 root container（rw 掛程式碼、cache 在 named volume）-> fix-perms"; else bad "composer-install: 呼叫不符"; calls_short; fi
if grep -q -- '--no-dev --optimize-autoloader' "$TMP/docker.log" && grep -q 'chown -R 0:0 vendor' "$TMP/docker.log"; then ok "composer-install: --no-dev --optimize-autoloader 且 vendor 交回 root"; else bad "composer-install: composer 參數 / vendor 擁有者"; fi

FAKE_DOCKER_FAIL='COMPOSER_ALLOW_SUPERUSER' run_cmd composer-install
if [ "$(cat "$TMP/rc")" != 0 ] && [ "$(grep -c '^docker ' "$TMP/docker.log")" = 1 ]; then ok "composer-install 失敗: 非 0 且不續跑 fix-perms"; else bad "composer-install 失敗: rc=$(cat "$TMP/rc")"; fi

run_cmd rebuild
check_rc0 "rebuild"
if [ "$(calls_short | grep -v '^docker ps')" = "docker compose --env-file ENV -f COMPOSE build
docker run --rm --network none --user 0:0 --entrypoint sh -v $p/storage:/fix/storage -v $p/bootstrap/cache:/fix/bootstrap-cache -v $p/.env:/fix/app.env wallet-v2:app -c ...
docker compose --env-file ENV -f COMPOSE up -d --remove-orphans" ]; then ok "rebuild: build -> fix-perms -> up -d（stdin 關閉仍完成，非互動）"; else bad "rebuild: 呼叫順序"; calls_short; fi
if grep -q '\[y/N\]\|請選擇' "$TMP/out.log"; then bad "rebuild: 出現互動提示"; else ok "rebuild: 無互動提示"; fi

for step in build fix-perms 'up -d'; do
    FAKE_DOCKER_FAIL="$step" run_cmd rebuild
    if [ "$(cat "$TMP/rc")" != 0 ]; then ok "rebuild: $step 失敗 -> 非 0"; else bad "rebuild: $step 失敗卻回 0"; fi
done
FAKE_DOCKER_FAIL=build run_cmd rebuild
if ! grep -q 'up -d' "$TMP/docker.log"; then ok "rebuild: build 失敗不執行 up"; else bad "rebuild: build 失敗仍 up"; fi

exit "$fail"
