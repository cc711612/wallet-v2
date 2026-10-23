#!/bin/bash
# web / worker entrypoint 共用的啟動前檢查（由 entrypoint source）。
# container 以非 root 執行，主機上 storage/、bootstrap/cache/、.env 的擁有者若還是 root，
# Laravel 會在寫 log / cache 或讀 .env 時才失敗；這裡提早失敗並指出修正方式。
preflight_check() {
  local role="$1" ok=1 d
  if [ "$(id -u)" = "0" ]; then
    echo "[$role] 警告：以 root 執行（預期為 Dockerfile 的 wallet 使用者）" >&2
  fi
  if [ ! -r .env ]; then
    echo "[$role] 無法讀取 .env（uid=$(id -u) gid=$(id -g)）" >&2
    ok=0
  fi
  for d in storage storage/logs storage/framework/cache storage/framework/views bootstrap/cache; do
    if [ ! -w "$d" ]; then
      echo "[$role] $d 不可寫（uid=$(id -u) gid=$(id -g)）" >&2
      ok=0
    fi
  done
  if [ -e storage/logs/laravel.log ] && [ ! -w storage/logs/laravel.log ]; then
    echo "[$role] storage/logs/laravel.log 不可寫" >&2
    ok=0
  fi
  if [ "$ok" = 0 ]; then
    echo "[$role] 請在主機執行：deployment/production/deploy.sh fix-perms" >&2
    exit 1
  fi
}
