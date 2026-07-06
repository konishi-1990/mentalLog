#!/bin/bash
#
# 本番（VPS）自動デプロイスクリプト（VPS 上で実行）
# GitHub Actions (.github/workflows/deploy-product.yml) から SSH 経由で呼ばれる。
#
# 使い方: bash deploy.sh <DEPLOY_DIR> [BRANCH] [COMPOSE_FILE]
#   DEPLOY_DIR   : リポジトリのチェックアウト先（例: /home/mentallog/mentallog）
#   BRANCH       : デプロイ対象ブランチ（既定: product）
#   COMPOSE_FILE : 使用する compose ファイル（既定: docker-compose.prod.yml）
#
# Secrets 例:
#   DEPLOY_SCRIPT_PATH = /home/mentallog/mentallog/script/deploy.sh
#   DEPLOY_ARGS        = /home/mentallog/mentallog product docker-compose.prod.yml
#
set -euo pipefail

DEPLOY_DIR="${1:-}"
BRANCH="${2:-product}"
COMPOSE_FILE="${3:-docker-compose.prod.yml}"
APP_SVC="php"               # docker compose の「サービス名」
APP_DIR="/var/www/html"     # コンテナ内の Laravel ルート

if [ -z "$DEPLOY_DIR" ]; then
  echo "Error: デプロイディレクトリを引数で指定してください" >&2
  echo "Usage: $0 <deploy_dir> [branch] [compose_file]" >&2
  exit 1
fi

echo "=== Deploy started: $(date) (dir=$DEPLOY_DIR branch=$BRANCH compose=$COMPOSE_FILE) ==="
cd "$DEPLOY_DIR"

dc()       { docker compose -f "$COMPOSE_FILE" "$@"; }
exec_php() { dc exec -T "$APP_SVC" bash -lc "$1"; }

# 1. 最新ソース取得
#    サーバ側のローカル変更は破棄し、origin/$BRANCH に完全一致させる
#    （.env / .env.db / db_data(named volume) は gitignore・ボリュームのため保持される）
echo "--- git fetch & reset ---"
git fetch --prune origin
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"

# 2. コンテナ起動（新ソースで再ビルド）
#    composer install / npm build / route:cache 等は production イメージのビルド時に実施済み
echo "--- docker compose up --build ---"
dc up -d --build

# 3. DB マイグレーション
echo "--- artisan migrate ---"
exec_php "cd $APP_DIR && php artisan migrate --force"

# 4. 設定キャッシュ再生成
#    本番イメージはビルド時に .env が無いため config:cache していない。
#    実行時にマウントした本番 .env の値でここでキャッシュを生成する。
echo "--- artisan cache refresh ---"
exec_php "cd $APP_DIR && php artisan config:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache"

echo "=== Deploy finished: $(date) ==="
