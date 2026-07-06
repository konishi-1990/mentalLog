#!/usr/bin/env bash
# app サーバへ SSH（シェル）接続
set -euo pipefail

cd "$(dirname "$0")/.."

# 引数があればそれをコマンドとして実行、なければ対話シェル
if [ "$#" -gt 0 ]; then
  docker compose exec app "$@"
else
  docker compose exec app bash
fi
