# MentalLog

自分のメンタル状態を日々ログ化し、**ストレスの見える化・傾向・回復パターンの把握**を支援する Web アプリケーション。

メンタルケアの第一歩として「記録 → 振り返り → 自己認知」を回すことを目的とする。特に、ストレス時に陥りがちな**思考のクセ（認知のクセ）を毎回チェック**して自覚を促すことを重視している。

🌐 本番: **https://mentallog.cde.jp**

---

## 目次
- [主な機能](#主な機能)
- [技術スタック](#技術スタック)
- [ローカル開発（Docker）](#ローカル開発docker)
- [運用スクリプト（bin/）](#運用スクリプトbin)
- [本番デプロイ](#本番デプロイ)
- [使い方](#使い方)
- [ロールと権限](#ロールと権限)
- [画面一覧](#画面一覧)
- [テスト](#テスト)
- [ディレクトリ構成](#ディレクトリ構成)
- [ドキュメント](#ドキュメント)

---

## 主な機能

### 📝 日次ログの記録（1日1件）
- **数値（0〜10）**：ストレス / 体力 / メンタル余裕
- **○×ストレス源**：仕事・バンド関係・コミュニティ等（ユーザ毎に項目をマスタ設定可能・○のとき内容補足）
- **テキスト**：今日一番きつかったこと / 一言まとめ
- **チェック（複数選択）**
  - 頭の中のクセ（0-100思考・自責 等／**超重要**）
  - 体の反応（睡眠が浅い・イライラ 等）
  - 今日やった回復行動（温泉・サウナ・音楽 等）

### 📊 見える化・分析
- ストレス／体力／メンタル余裕の**時系列グラフ**（Chart.js）
- ストレス源・思考のクセ・体の反応の**頻度ランキング**
- **回復パターン分析**：回復行動を取った翌日のメンタル余裕を「取らなかった翌日」と比較し、効いた行動を示唆

### 🔍 ログ参照・絞り込み
- 日付範囲・各数値の min/max で絞り込み
- 一般ユーザは自分のみ、管理者は全ユーザ＋ユーザ指定

### ⚙️ マスタ・ユーザ管理
- ○×項目マスタ（ユーザ毎に追加・改名・並び替え・無効化）
- 管理者：ユーザ管理（CRUD・ロール変更・有効無効）、チェックリスト共通マスタ管理

> UI は日本語化済み（`lang/ja.json`）。トップページ `/` は認証状態に応じてログイン／ダッシュボードへ誘導。

---

## 技術スタック

| 分類 | 採用 |
|---|---|
| 言語 / FW | PHP 8.3 / Laravel 13 |
| DB | PostgreSQL 16 |
| フロント | Blade + Tailwind CSS + Chart.js（Vite ビルド） |
| 認証 | Laravel Breeze（Blade スタック） |
| テスト | Pest（TDD、専用 PostgreSQL に対して実行、カバレッジ pcov） |
| 実行環境 | Docker（マルチステージ / dev・本番共通の Dockerfile） |
| 本番配信 | 上位 nginx-proxy(jwilder) + Let's Encrypt で TLS 終端 |
| CI/CD | GitHub Actions（`product` への PR マージで SSH 自動デプロイ） |

---

## ローカル開発（Docker）

前提：Docker Desktop（Compose v2）。ポート 8080 / 8081 / 5432 が空いていること。

```bash
cp .env.docker.example .env   # 既に .env がある場合は不要
make build                    # イメージビルド
./bin/start.sh                # コンテナ起動（= make up）
make migrate                  # マイグレーション
make seed                     # 初期データ（ロール・チェックリストマスタ・管理者）
make npm-build                # フロントビルド（開発時は make npm-dev）
```

| 用途 | URL |
|---|---|
| アプリ | http://localhost:8080 |
| DB確認（Adminer） | http://localhost:8081 |

- **初期管理者（開発シード）**：`admin@example.com` / `password`
- 開発コンテナ構成：`app`(php-fpm) / `web`(nginx) / `db`(postgres) / `adminer`
- Laravel を新規から作る場合など、詳細は `docs/docker.md` を参照。

---

## 運用スクリプト（bin/）

日常操作用のショートカット。**`.env` の `APP_ENV` で dev / 本番を自動判定**するため、ローカルでも VPS でも同じコマンドで動く（オプション不要）。

| スクリプト | 役割 |
|---|---|
| `bin/start.sh` | コンテナ起動 |
| `bin/stop.sh` | コンテナ停止・削除 |
| `bin/restart.sh` | 再起動 |
| `bin/ssh.sh` | app コンテナへシェル接続 / コマンド実行 |
| `bin/sql.sh` | DB コンテナ上で psql |

```bash
./bin/ssh.sh                       # app コンテナに入る
./bin/ssh.sh php artisan migrate   # コンテナ内でコマンド実行
./bin/sql.sh                       # psql プロンプト
./bin/sql.sh -c "SELECT count(*) FROM users"
```

- 環境は自動判定（`local`→`docker-compose.yml`／`production`→`docker-compose.prod.yml`）。
- 明示的に切り替えたい場合のみ先頭に `-p`（本番）/ `-d`（dev）を付与。

---

## 本番デプロイ

本番はコード・依存・Vite 資産を**イメージに焼き込み**、`.env` は実行時にサーバ側で供給する方式（UrlShare と同構成）。

### 構成
- `docker/php/Dockerfile`：マルチステージ（`base` / `development` / `build` / `production` / `nginx`）。`build` で `composer install --no-dev`・`vite build`・route/view cache を実施し、`production`(php-fpm) と `nginx`(静的配信) に成果物を焼き込む。
- `docker-compose.prod.yml`：`nginx` / `php` / `db`。80/443 の終端・証明書は上位 nginx-proxy が担当（`VIRTUAL_HOST=mentallog.cde.jp`）、アプリ側はポート非公開。
- `script/deploy.sh`：VPS 上で `git reset --hard` → `docker compose up -d --build` → `migrate` → config/route/view cache を実行。
- `.github/workflows/deploy-product.yml`：`product` への PR マージ、または手動実行（workflow_dispatch）で SSH 経由デプロイ。

### デプロイの流れ
```
main で開発 → main→product の PR を作成・マージ → GitHub Actions が VPS へ SSH デプロイ
```
手動再デプロイは Actions タブの「Deploy to Product (VPS)」→ Run workflow。

### サーバ側の前提（初回のみ）
- リポジトリを VPS に clone し `product` を checkout。
- `.env`（`.env.production.example` を元に作成し `APP_KEY` を生成）と `.env.db`（`.env.db.example` を元に作成）を配置。両者の DB パスワードは一致させる。
- 上位 `nginx-proxy` + letsencrypt-companion が稼働し、外部ネットワーク `nginx-proxy` が存在すること。
- GitHub Secrets：`VPS_HOST` / `VPS_PORT` / `VPS_USER` / `VPS_SSH_KEY` / `DEPLOY_SCRIPT_PATH` / `DEPLOY_ARGS`。

### 管理者ユーザの作成
```bash
./bin/ssh.sh php artisan app:make-admin adm@example.com
# → パスワードを対話（秘匿）入力。--password= で一括指定も可
```
admin ロール・有効・メール確認済みで作成（既存なら更新）。○×項目テンプレは Observer が自動複製。

---

## 使い方

1. トップ（`/`）にアクセス → 未ログインなら `/login` へ。ログイン（または新規登録）。
2. **ダッシュボード**で直近の推移と当日ログの状態を確認。
3. **「ログを書く」**から当日の状態を記録（同一日は上書き更新）。
4. **「分析」**で時系列・傾向・回復パターンを振り返る。
5. **「○×項目の設定」**で自分のストレス源カテゴリをカスタマイズ。

---

## ロールと権限

| ロール | 権限 |
|---|---|
| システム管理者 | 全ユーザ・全データの参照/管理、ユーザ管理、共通マスタ管理 |
| 一般ユーザ | **自分の情報のみ** 作成/参照/編集/削除、自分の○×項目マスタ設定 |

> 機微情報（メンタル状態）を扱うため、他ユーザのデータは Policy で厳格に分離（他人のログ操作は 403）。

---

## 画面一覧

| 画面 | 対象 |
|---|---|
| ログイン / 登録 | 全員 |
| ダッシュボード | 一般 / 管理者 |
| ログ入力・編集・詳細 | 本人（管理者は閲覧可） |
| ログ一覧（絞り込み） | 一般（管理者は全件） |
| 分析 | 一般 / 管理者 |
| ○×項目マスタ設定 | 一般 |
| ユーザ管理 | 管理者 |
| チェックリストマスタ管理 | 管理者 |

---

## テスト

TDD で実装。全テストは専用 PostgreSQL（`mentallog_test`）に対して実行。

```bash
make test                       # 全テスト（Pest）
make coverage                   # カバレッジ計測
make test-filter F=LogService   # 絞り込み実行
```

**現状：106 passed（216 assertions）／ カバレッジ 92.9%**。詳細は `docs/test.md`。

---

## ディレクトリ構成

```
app/
  Console/Commands/         # MakeAdmin（app:make-admin）
  Http/Controllers/         # Log / CheckItem / Analytics / Dashboard / Admin/*
  Http/Requests/            # FormRequest（バリデーション）
  Http/Middleware/          # EnsureUserIsAdmin
  Models/                   # 8モデル
  Policies/                 # LogPolicy / CheckItemPolicy
  Services/                 # LogService / AnalyticsService（業務ロジック）
  Observers/                # UserObserver（既定○×複製・ロール付与）
  Support/                  # DefaultCheckItems
bin/                        # 運用スクリプト（start/stop/restart/ssh/sql）
database/
  migrations/               # 8テーブル
  seeders/                  # Role / ChecklistCategory / ChecklistOption
  factories/                # User / Log / CheckItem
lang/ja.json                # UI 日本語化
resources/views/            # Blade（layouts / logs / analytics / check_items / admin / errors）
routes/web.php
script/deploy.sh            # 本番デプロイ（VPS 上で実行）
tests/                      # Unit / Feature（Pest）
docker/                     # php(Dockerfile) / nginx(dev・prod conf) / postgres(init)
docker-compose.yml          # 開発
docker-compose.prod.yml     # 本番
.github/workflows/          # deploy-product.yml（自動デプロイ）
docs/                       # 設計・計画・テスト・作業履歴
```

---

## ドキュメント

| ファイル | 内容 |
|---|---|
| `docs/require.md` | 要件定義 |
| `docs/architecture.md` | アーキテクチャ（Mermaid図） |
| `docs/model.md` | データモデル（ER図） |
| `docs/disp.md` | 画面設計 |
| `docs/plan.md` | 実装プラン・進捗（TDD） |
| `docs/docker.md` | Docker構築手順 |
| `docs/test.md` | テスト仕様書（実施記録） |
| `docs/chglogs/` | 日次の作業履歴 |

---

## よく使うコマンド

```bash
# 運用スクリプト（dev/本番 自動判定）
./bin/start.sh / stop.sh / restart.sh
./bin/ssh.sh                 # app コンテナへ
./bin/sql.sh                 # psql

# Makefile（開発）
make help                    # コマンド一覧
make migrate                 # マイグレーション
make fresh                   # DB再作成 + シーダ
make test                    # テスト
make npm-dev                 # Vite 開発サーバ
```

---

## ライセンス
個人利用・学習目的のプロジェクト。
