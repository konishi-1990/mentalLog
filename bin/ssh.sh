#!/usr/bin/env bash
# app サーバへ SSH（シェル）接続
#   dev : bin/ssh.sh            （docker-compose.yml / サービス app）
#   本番: bin/ssh.sh -p         （docker-compose.prod.yml / サービス php）
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE_FILE="docker-compose.yml"
APP_SVC="app"
if [ "${1:-}" = "-p" ] || [ "${1:-}" = "--prod" ]; then
  COMPOSE_FILE="docker-compose.prod.yml"
  APP_SVC="php"
  shift
fi

# 引数があればそれをコマンドとして実行、なければ対話シェル
if [ "$#" -gt 0 ]; then
  docker compose -f "$COMPOSE_FILE" exec "$APP_SVC" "$@"
else
  docker compose -f "$COMPOSE_FILE" exec "$APP_SVC" bash
fi
