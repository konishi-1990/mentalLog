# MentalLog Docker 環境構築手順

## 1. 構成

| サービス | イメージ / ビルド | 役割 | ポート |
|---|---|---|---|
| `app` | `docker/php/Dockerfile`（PHP 8.3-fpm） | Laravel 実行（php-fpm）+ Composer + Node20 | 9000（内部） |
| `web` | `nginx:1.27-alpine` | Web サーバ（`public/` 配信、app へ FastCGI） | `${APP_PORT:-8080}` → 80 |
| `db` | `postgres:16-alpine` | データベース | `${DB_PORT:-5432}` → 5432 |
| `adminer` | `adminer:latest` | DB 確認用 GUI（開発補助） | `${ADMINER_PORT:-8081}` → 8080 |

ネットワーク `mentallog`（bridge）で相互接続。DB データは名前付きボリューム `db-data` に永続化。

```
Browser → :8080 [nginx(web)] → :9000 [php-fpm(app)] → [postgres(db)]
                                                     ↘ [adminer :8081]
```

---

## 2. 前提

- Docker Desktop（または Docker Engine + Compose v2）がインストール済み。
- ポート 8080 / 8081 / 5432 が空いていること（使用中なら `.env` で変更）。

---

## 3. 初回セットアップ手順

### 3-1. 環境変数ファイルを用意
```bash
cp .env.docker.example .env
# 必要なら UID/GID をホストに合わせる（Mac/Linux）
#   UID=$(id -u)  GID=$(id -g) を .env に反映
```

### 3-2. イメージビルド
```bash
make build          # または: docker compose build
```

### 3-3. Laravel プロジェクトを作成（未作成の場合のみ・初回一度だけ）
リポジトリ直下に Laravel を展開します。
```bash
make create-project
# 実体: docker compose run --rm app composer create-project laravel/laravel:^11.0 ...
```
> 既に Laravel が入っている場合はこの手順は不要。

### 3-4. コンテナ起動
```bash
make up             # または: docker compose up -d
make ps             # 稼働確認（db が healthy になるまで待つ）
```

### 3-5. Laravel の .env を PostgreSQL に向ける
`.env`（Laravel 用）を編集し、DB 接続をコンテナに合わせる：
```dotenv
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
DB_HOST=db          # ★ コンテナ名ではなくサービス名 "db"
DB_PORT=5432
DB_DATABASE=mentallog
DB_USERNAME=mentallog
DB_PASSWORD=secret
```
> `.env.docker.example` と同じ値。ホストからではなく **コンテナ間通信**なので `DB_HOST=db`。

### 3-6. アプリキー生成 & マイグレーション
```bash
make key            # php artisan key:generate
make migrate        # マイグレーション
make seed           # 初期データ（ロール・チェックリストマスタ）
# まとめてやり直す場合: make fresh
```

### 3-7. フロント（Vite）
```bash
make npm-dev        # 開発時（HMR）
# 本番相当ビルドは: make npm-build
```

---

## 4. アクセス URL

| 用途 | URL |
|---|---|
| アプリ | http://localhost:8080 |
| Adminer（DB GUI） | http://localhost:8081 |

Adminer ログイン:
- System: **PostgreSQL**
- Server: **db**
- Username: **mentallog**
- Password: **secret**
- Database: **mentallog**

---

## 5. よく使うコマンド（Makefile）

```bash
make help           # 一覧表示
make up / down      # 起動 / 停止
make sh             # app コンテナに入る（bash）
make migrate        # マイグレーション
make fresh          # DB作り直し + シーダ
make logs           # ログ追従
```

コンテナ内で直接 artisan を叩く場合:
```bash
docker compose exec app php artisan <command>
docker compose exec app composer <command>
docker compose exec app npm <command>
```

---

## 6. トラブルシュート

| 症状 | 対処 |
|---|---|
| `public/` が無く 404/403 | Laravel 未作成。`make create-project` を実行 |
| DB 接続エラー | Laravel `.env` の `DB_HOST=db` を確認。`make ps` で db が healthy か確認 |
| 権限エラー（storage 書込不可） | `.env` の `UID/GID` をホストに合わせて `make build` し直す。または `docker compose exec app php artisan storage:link` と権限付与 |
| ポート衝突 | `.env` の `APP_PORT / DB_PORT / ADMINER_PORT` を変更 |
| 変更が反映されない | ソースはボリュームマウント。PHP設定/依存変更時は `make build` → `make restart` |

---

## 7. 停止・破棄

```bash
make down                       # コンテナ停止・削除（DBボリュームは保持）
docker compose down -v          # DBボリュームごと完全削除（データ消去）
```
