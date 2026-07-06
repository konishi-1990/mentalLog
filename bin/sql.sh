#!/usr/bin/env bash
# DB サーバ上で psql を実行
set -euo pipefail

cd "$(dirname "$0")/.."

# .env から DB 接続情報を取得（未設定時は docker-compose のデフォルトに合わせる）
DB_USERNAME="$(grep -E '^DB_USERNAME=' .env 2>/dev/null | cut -d= -f2- || true)"
DB_DATABASE="$(grep -E '^DB_DATABASE=' .env 2>/dev/null | cut -d= -f2- || true)"
DB_USERNAME="${DB_USERNAME:-mentallog}"
DB_DATABASE="${DB_DATABASE:-mentallog}"

# 引数があれば psql にそのまま渡す（例: bin/sql.sh -c "SELECT 1"）
docker compose exec db psql -U "$DB_USERNAME" -d "$DB_DATABASE" "$@"
