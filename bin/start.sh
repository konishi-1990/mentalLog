#!/usr/bin/env bash
# コンテナ起動
#   dev : bin/start.sh          （docker-compose.yml）
#   本番: bin/start.sh -p        （docker-compose.prod.yml）
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE_FILE="docker-compose.yml"
if [ "${1:-}" = "-p" ] || [ "${1:-}" = "--prod" ]; then
  COMPOSE_FILE="docker-compose.prod.yml"
  shift
fi

echo "▶ コンテナを起動します... ($COMPOSE_FILE)"
docker compose -f "$COMPOSE_FILE" up -d "$@"
echo
docker compose -f "$COMPOSE_FILE" ps
