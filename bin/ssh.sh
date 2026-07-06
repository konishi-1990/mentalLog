#!/usr/bin/env bash
# app サーバへ SSH（シェル）接続
#   環境は .env の APP_ENV で自動判定（production=本番 / それ以外=dev）
#   明示指定したい場合のみ -p(本番) / -d(dev) を先頭に付与
set -euo pipefail

cd "$(dirname "$0")/.."

# --- 環境自動判定（オプション不要）---
COMPOSE_FILE="docker-compose.yml"
APP_SVC="app"
APP_ENV="$(grep -E '^APP_ENV=' .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'\''\r' || true)"
if [ "$APP_ENV" = "production" ]; then
  COMPOSE_FILE="docker-compose.prod.yml"
  APP_SVC="php"
fi
# 明示フラグで上書き
case "${1:-}" in
  -p|--prod) COMPOSE_FILE="docker-compose.prod.yml"; APP_SVC="php"; shift ;;
  -d|--dev)  COMPOSE_FILE="docker-compose.yml";      APP_SVC="app"; shift ;;
esac

# 引数があればそれをコマンドとして実行、なければ対話シェル
if [ "$#" -gt 0 ]; then
  docker compose -f "$COMPOSE_FILE" exec "$APP_SVC" "$@"
else
  docker compose -f "$COMPOSE_FILE" exec "$APP_SVC" bash
fi
