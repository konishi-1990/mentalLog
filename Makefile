# MentalLog 開発用ショートカット
# 使い方: make <target>

.DEFAULT_GOAL := help

## ---- 環境構築 ----
build: ## イメージをビルド
	docker compose build

up: ## コンテナ起動（バックグラウンド）
	docker compose up -d

down: ## コンテナ停止・削除
	docker compose down

restart: ## 再起動
	docker compose down && docker compose up -d

ps: ## 稼働状況
	docker compose ps

logs: ## ログ追従
	docker compose logs -f

## ---- Laravel 初期導入（プロジェクト未作成時に一度だけ）----
create-project: ## カレントに Laravel 11 を新規作成
	docker compose run --rm app composer create-project laravel/laravel:^11.0 tmp \
	&& docker compose run --rm app sh -c "cp -a tmp/. . && rm -rf tmp"

install-breeze: ## Breeze(Blade) 導入
	docker compose exec app composer require laravel/breeze --dev
	docker compose exec app php artisan breeze:install blade
	docker compose exec app npm install

## ---- 日常操作 ----
sh: ## app コンテナへ入る
	docker compose exec app bash

migrate: ## マイグレーション
	docker compose exec app php artisan migrate

seed: ## シーダ実行
	docker compose exec app php artisan db:seed

fresh: ## DB再作成 + シーダ
	docker compose exec app php artisan migrate:fresh --seed

npm-dev: ## Vite 開発サーバ
	docker compose exec app npm run dev

npm-build: ## Vite ビルド
	docker compose exec app npm run build

key: ## APP_KEY 生成
	docker compose exec app php artisan key:generate

## ---- テスト (TDD) ----
test: ## 全テスト実行 (Pest)
	docker compose exec app ./vendor/bin/pest

test-filter: ## 絞り込み実行: make test-filter F=キーワード
	docker compose exec app ./vendor/bin/pest --filter=$(F)

coverage: ## カバレッジ計測
	docker compose exec app ./vendor/bin/pest --coverage

db-test-create: ## テストDB(mentallog_test)を作成
	docker compose exec db psql -U $(or $(DB_USERNAME),mentallog) -d $(or $(DB_DATABASE),mentallog) -c "CREATE DATABASE mentallog_test" || true

help: ## このヘルプ
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'
