# MentalLog

自分のメンタル状態を日々ログ化し、**ストレスの見える化・傾向・回復パターンの把握**を支援する Web アプリケーション。

メンタルケアの第一歩として「記録 → 振り返り → 自己認知」を回すことを目的とする。特に、ストレス時に陥りがちな**思考のクセ（認知のクセ）を毎回チェック**して自覚を促すことを重視している。

---

## 目次
- [主な機能](#主な機能)
- [技術スタック](#技術スタック)
- [セットアップ](#セットアップdocker)
- [使い方](#使い方)
- [ロールと権限](#ロールと権限)
- [画面一覧](#画面一覧)
- [テスト](#テスト)
- [ディレクトリ構成](#ディレクトリ構成)
- [ドキュメント](#ドキュメント)
- [よく使うコマンド](#よく使うコマンド)

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

---

## 技術スタック

| 分類 | 採用 |
|---|---|
| 言語 / FW | PHP 8.3 / Laravel 13 |
| DB | PostgreSQL 16 |
| フロント | Blade + Tailwind CSS + Chart.js（Vite ビルド） |
| 認証 | Laravel Breeze（Blade スタック） |
| テスト | Pest（TDD、専用 PostgreSQL に対して実行、カバレッジ pcov） |
| 実行環境 | Docker（app: php-fpm / web: nginx / db: postgres / adminer） |

---

## セットアップ（Docker）

前提：Docker Desktop（Compose v2）。ポート 8080 / 8081 / 5432 が空いていること。

```bash
cp .env.docker.example .env   # 既に .env がある場合は不要
make build                    # イメージビルド
make up                       # コンテナ起動
make migrate                  # マイグレーション
make seed                     # 初期データ（ロール・チェックリストマスタ・管理者）
make npm-build                # フロントビルド（開発時は make npm-dev）
```

| 用途 | URL |
|---|---|
| アプリ | http://localhost:8080 |
| DB確認（Adminer） | http://localhost:8081 |

- **初期管理者**：`admin@example.com` / `password`
- Laravel を新規から作る場合など、詳細は `docs/docker.md` を参照。

---

## 使い方

1. `http://localhost:8080` にアクセスしログイン（または新規登録）。
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
  Http/Controllers/         # Log / CheckItem / Analytics / Dashboard / Admin/*
  Http/Requests/            # FormRequest（バリデーション）
  Http/Middleware/          # EnsureUserIsAdmin
  Models/                   # 8モデル
  Policies/                 # LogPolicy / CheckItemPolicy
  Services/                 # LogService / AnalyticsService（業務ロジック）
  Observers/                # UserObserver（既定○×複製・ロール付与）
  Support/                  # DefaultCheckItems
database/
  migrations/               # 8テーブル
  seeders/                  # Role / ChecklistCategory / ChecklistOption
  factories/                # User / Log / CheckItem
resources/views/            # Blade（layouts / logs / analytics / check_items / admin / errors）
routes/web.php
tests/                      # Unit / Feature（Pest）
docker/                     # php(Dockerfile) / nginx / postgres(init)
docs/                       # 設計・計画・テスト各種
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

---

## よく使うコマンド

```bash
make help        # コマンド一覧
make up / down   # 起動 / 停止
make sh          # app コンテナに入る
make migrate     # マイグレーション
make fresh       # DB再作成 + シーダ
make test        # テスト
make npm-dev     # Vite 開発サーバ
```

---

## ライセンス
個人利用・学習目的のプロジェクト。
