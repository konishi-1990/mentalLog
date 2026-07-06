#!/usr/bin/env bash
# コンテナ再起動
#   dev : bin/restart.sh        （docker-compose.yml）
#   本番: bin/restart.sh -p      （docker-compose.prod.yml）
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE_FILE="docker-compose.yml"
if [ "${1:-}" = "-p" ] || [ "${1:-}" = "--prod" ]; then
  COMPOSE_FILE="docker-compose.prod.yml"
  shift
fi

echo "↻ コンテナを再起動します... ($COMPOSE_FILE)"
docker compose -f "$COMPOSE_FILE" down
docker compose -f "$COMPOSE_FILE" up -d "$@"
echo
docker compose -f "$COMPOSE_FILE" ps
