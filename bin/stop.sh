#!/usr/bin/env bash
# コンテナ終了
#   dev : bin/stop.sh           （docker-compose.yml）
#   本番: bin/stop.sh -p         （docker-compose.prod.yml）
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE_FILE="docker-compose.yml"
if [ "${1:-}" = "-p" ] || [ "${1:-}" = "--prod" ]; then
  COMPOSE_FILE="docker-compose.prod.yml"
  shift
fi

echo "■ コンテナを停止・削除します... ($COMPOSE_FILE)"
docker compose -f "$COMPOSE_FILE" down "$@"
