#!/usr/bin/env bash
# コンテナ起動
set -euo pipefail

cd "$(dirname "$0")/.."

echo "▶ コンテナを起動します..."
docker compose up -d "$@"
echo
docker compose ps
