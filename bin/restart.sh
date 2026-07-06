#!/usr/bin/env bash
# コンテナ再起動
set -euo pipefail

cd "$(dirname "$0")/.."

echo "↻ コンテナを再起動します..."
docker compose down
docker compose up -d "$@"
echo
docker compose ps
