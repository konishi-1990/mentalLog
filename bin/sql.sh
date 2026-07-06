#!/usr/bin/env bash
# DB サーバ上で psql を実行
#   環境は .env の APP_ENV で自動判定（production=本番 / それ以外=dev）
#   明示指定したい場合のみ -p(本番) / -d(dev) を先頭に付与
#   例: bin/sql.sh -c "SELECT 1"
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

# .env から DB 接続情報を取得（未設定時は docker-compose のデフォルトに合わせる）
DB_USERNAME="$(grep -E '^DB_USERNAME=' .env 2>/dev/null | cut -d= -f2- || true)"
DB_DATABASE="$(grep -E '^DB_DATABASE=' .env 2>/dev/null | cut -d= -f2- || true)"
DB_USERNAME="${DB_USERNAME:-mentallog}"
DB_DATABASE="${DB_DATABASE:-mentallog}"

# 引数があれば psql にそのまま渡す（例: bin/sql.sh -c "SELECT 1"）
docker compose -f "$COMPOSE_FILE" exec db psql -U "$DB_USERNAME" -d "$DB_DATABASE" "$@"
