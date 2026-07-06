#!/usr/bin/env bash
# コンテナ起動
#   環境は .env の APP_ENV で自動判定（production=本番 / それ以外=dev）
#   明示指定したい場合のみ -p(本番) / -d(dev) を先頭に付与
set -euo pipefail

cd "$(dirname "$0")/.."

# --- 環境自動判定（オプション不要）---
COMPOSE_FILE="docker-compose.yml"
APP_ENV="$(grep -E '^APP_ENV=' .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'\''\r' || true)"
if [ "$APP_ENV" = "production" ]; then
  COMPOSE_FILE="docker-compose.prod.yml"
fi
# 明示フラグで上書き
case "${1:-}" in
  -p|--prod) COMPOSE_FILE="docker-compose.prod.yml"; shift ;;
  -d|--dev)  COMPOSE_FILE="docker-compose.yml";      shift ;;
esac

echo "▶ コンテナを起動します... ($COMPOSE_FILE)"
docker compose -f "$COMPOSE_FILE" up -d "$@"
echo
docker compose -f "$COMPOSE_FILE" ps
