#!/usr/bin/env bash
# コンテナ終了
set -euo pipefail

cd "$(dirname "$0")/.."

echo "■ コンテナを停止・削除します..."
docker compose down "$@"
