# MentalLog 実装プラン（TDD版）

本書は MentalLog の実装計画を定義する。設計は `require.md` / `architecture.md` / `model.md` / `disp.md`、実行環境は `docker.md` を参照。

---

## 1. 開発方針

- **TDD（テスト駆動開発）** で進める。各機能は 🔴 Red → 🟢 Green → 🔵 Refactor のサイクルで実装する。
  - 🔴 Red：まず失敗するテストを書く（テスト＝仕様書）
  - 🟢 Green：テストを通す最小実装を行う
  - 🔵 Refactor：テスト緑を保ったままコードを整理する
- 各フェーズは「テスト→実装→リファクタ」で **1機能ずつコミット可能な粒度** とする。
- Laravel 11 + Breeze(Blade) を土台に、業務ロジックは `Service` 層へ寄せる。
- 段階リリース型。各フェーズ末で「動く状態」を作りながら積み上げる。

### 技術選定（確定事項）
| 項目 | 採用 | 備考 |
|---|---|---|
| テストフレームワーク | **Pest** | モダン・可読性重視。内部は PHPUnit |
| テスト用DB | **専用 PostgreSQL（`mentallog_test`）** | 本番と同一エンジン。CHECK制約・自己結合・集計SQLまで正確に検証 |

---

## 2. テスト戦略

### テストピラミッド
| 層 | 対象 | 例 |
|---|---|---|
| **Unit** | Service・モデルのロジック | `LogService::upsertDailyLog` の1日1件制御、`AnalyticsService` の集計計算 |
| **Feature** | HTTPエンドポイント＋認可 | ログ作成の成功/リダイレクト、他人ログへの403、絞り込み結果 |
| E2E（対象外） | ブラウザ操作 | 初期スコープ外（手動確認） |

### 重点方針
- **認可の分離テストを最重要視**する。機微データ（メンタル情報）を扱うため、「他人のログが見えない/操作できない（403）」を必ずテストで担保する。
- **コアロジック（Service / Policy）を厚くテスト**する。Blade（画面）は最小限のスモークで可。
- 全テストで `RefreshDatabase` を用い、テストDBにマイグレーションを適用する。
- 集計系（分析）は **固定データ→期待値** のUnitテストで数値を保証する。

---

## 3. フェーズ計画

### フェーズ0: 基盤 ＋ テスト環境（TDDの土台）✅ 完了
**ゴール**: `make test` で Pest が走り、テストDBに対して緑になる。→ **達成（27 tests passed）**

| # | 作業 | 状況 |
|---|---|---|
| 0-1 | Laravel + Breeze(Blade) 導入 | ✅ **Laravel 13**（11.xは勧告ブロックのため最新版採用）+ Breeze(Blade, --pest) |
| 0-2 | Laravel `.env` を postgres 接続 | ✅ `DB_HOST=db` / `APP_URL=http://localhost:8080` / ロケール ja。docker compose と .env 共有 |
| 0-3 | Pest 導入 | ✅ `breeze:install --pest` で導入 |
| 0-4 | テストDB `mentallog_test` 用意 | ✅ 手動作成 + `docker/postgres/init/01-create-test-db.sql`（新規volume自動作成）+ `make db-test-create` |
| 0-5 | `phpunit.xml` 設定 + `RefreshDatabase` | ✅ phpunit.xml を pgsql/`mentallog_test` に変更 |
| 0-6 | `make test` 追加、最初のテスト | ✅ `tests/Feature/SmokeTest.php`（/dashboard→login リダイレクト等）緑 |
| 0-7 | 共通レイアウト（サイドナビ）＋CSSベース | ✅ `layouts/app.blade.php` をサイドバー化 + `sidebar.blade.php` + テーマ変数(app.css) |

> 補足：CSSは Breeze 同梱の **Tailwind CSS** を採用（当初「素のCSS」想定から変更）。未実装メニューは `Route::has()` で無効表示にし、後続フェーズで自動有効化。

---

### フェーズ1: DB・モデル・認可基盤 ✅ 完了
**ゴール**: モデル・リレーション・シーダがテストで保証される。→ **達成（全44 tests passed）**

| # | 🔴 テスト | 🟢 実装 | 状況 |
|---|---|---|---|
| 1-1 | 各モデルのリレーション・`User::isAdmin()` | 8マイグレーション + 8モデル（`#[Fillable]`属性） | ✅ `tests/Unit/Models/UserTest.php` |
| 1-2 | CHECK制約（stress=11/stamina=-1 で例外）、複合ユニーク（同日2件で例外） | CHECK制約(DB::statement) / unique | ✅ `tests/Feature/Database/ConstraintsTest.php` |
| 1-3 | シーダ（ロール2件、カテゴリ3件、選択肢6/6/7、`is_none`/`requires_text`） | `RoleSeeder`/`ChecklistCategorySeeder`/`ChecklistOptionSeeder` | ✅ `tests/Feature/Database/SeederTest.php` |
| 1-4 | ユーザ作成時に○×テンプレ5件が複製 | **`UserObserver`**（`created`で複製 / `creating`で既定ロール付与）+ `App\Support\DefaultCheckItems` | ✅ `tests/Feature/DefaultCheckItemsTest.php` |

**マイグレーション順**（実装）: users(既存) → roles → add_role_and_status_to_users → check_items → checklist_categories → checklist_options → logs → log_check_item_values → log_checklist_selections

**補足（設計からの実装判断）**:
- ○×テンプレ複製は当初 `DefaultCheckItemSeeder` 想定だったが、**登録・ファクトリ含め全経路で確実に複製**するため `UserObserver`（`#[ObservedBy]`）方式を採用。テンプレ定義は `App\Support\DefaultCheckItems`。
- `UserObserver::creating` で role 未指定時に一般ユーザロールを自動付与（Breeze登録が role_id なしでも成立）。
- CHECK制約は Laravel のスキーマビルダに fluent 記法がないため `DB::statement` で付与。
- `migrate:fresh --seed` 実測: roles=2 / check_items=5 / categories=3 / options=19。

---

### フェーズ2: ログCRUD（コア機能・最重要）✅ 完了
**ゴール**: 1日1件・子テーブル保存・認可が全てテストで緑。→ **達成（全67 tests passed）**

| # | 🔴 テスト | 🟢 実装 | 状況 |
|---|---|---|---|
| 2-1 | `LogService::upsertDailyLog`：新規作成 / 同日再送信で更新（件数不変） | `App\Services\LogService`（トランザクション + updateOrCreate） | ✅ `tests/Unit/LogServiceTest.php` |
| 2-2 | ○選択時のみ `detail_text` 保存、チェックが子テーブルに入る | 子の delete→再作成で置換。他人の項目は防御的に無視 | ✅ 同上 + `LogCrudTest` |
| 2-3 | 0-10範囲外で422、「特になし」排他、その他テキスト必須 | `StoreLogRequest`（`withValidator`で排他/requires_text）/ `UpdateLogRequest` | ✅ `tests/Feature/LogValidationTest.php` |
| 2-4 | 他人のログ view/edit/update/delete で403、未ログイン302 | `LogPolicy`（`before`で管理者許可） | ✅ `tests/Feature/LogAuthorizationTest.php` |
| 2-5 | index/create/store/show/edit/update/destroy | `LogController` + `Route::resource('logs')` | ✅ `tests/Feature/LogCrudTest.php` |
| 2-6 | 入力/編集・詳細・一覧画面 | `logs/{create,edit,show,index}` + `partials/form`（○展開・特になし排他JS・スライダー） | ✅ |

**補足（実装判断）**:
- Laravel 13 の空 `Controller` に `AuthorizesRequests` トレイトを追加（`$this->authorize()` 利用のため）。
- フォームのペイロード形状：`check_items[id][is_on|detail_text]` / `checklist[]`（option_id）/ `checklist_details[option_id]`。
- 一覧の絞り込みは**フェーズ3**で拡張予定（現状は自分のログを日付降順ページネーション）。

---

### フェーズ3: 一覧・絞り込み ✅ 完了
**ゴール**: 自分のログを条件で絞れる（管理者は全件＋ユーザ絞り込み）。→ **達成（全74 tests passed）**

| # | 🔴 テスト | 🟢 実装 | 状況 |
|---|---|---|---|
| 3-1 | 日付 from/to で件数が絞られる | `whereDate` | ✅ `tests/Feature/LogFilterTest.php` |
| 3-2 | 各数値 min/max（境界値含む）＋範囲外はバリデーションエラー | `LogFilterRequest` + 動的 `when`/`where` | ✅ 同上 |
| 3-3 | 一般ユーザは自分のみ、管理者は全件＋ユーザ絞り込み | `isAdmin` でベースクエリ分岐 | ✅ 同上 |
| 3-4 | 一覧画面（絞り込みパネル・色分け・空状態・管理者ユーザ列） | `logs/index` 刷新 + `withQueryString` ページネーション | ✅ |

---

### フェーズ4: ○×項目マスタ設定（ユーザ毎）✅ 完了
**ゴール**: 自分のストレス源カテゴリを追加・改名・並び替え・無効化できる。→ **達成（全84 tests passed）**

| # | 🔴 テスト | 🟢 実装 | 状況 |
|---|---|---|---|
| 4-1 | 追加/改名/無効化、名前必須、他人の項目を操作で403 | `CheckItemController` + `CheckItemPolicy` + `Store/UpdateCheckItemRequest` | ✅ `tests/Feature/CheckItemTest.php` |
| 4-2 | 無効化しても過去ログ回答は保持 | `is_active` 論理無効化（destroy も物理削除せず無効化） | ✅ 同上 |
| 4-3 | 設定画面＋並び替え（他人項目は無視） | `check_items/index`（追加/編集/有効切替/▲▼並び替え）+ `reorder` アクション | ✅ |

**補足（実装判断）**:
- リソースのパラメータを `parameters(['check-items' => 'checkItem'])` に設定し、`$checkItem` 暗黙バインドを成立させた。
- `reorder` ルートは `{checkItem}` に食われないよう resource より前に定義。
- 並び替え・○×保存ともユーザ所有分のみ反映（防御的フィルタ）。

---

### フェーズ5: 見える化・分析 ✅ 完了
**ゴール**: 時系列グラフと傾向・回復パターンが見られる。→ **達成（全91 tests passed）**

| # | 🔴 テスト | 🟢 実装 | 状況 |
|---|---|---|---|
| 5-1 | 時系列：指定期間の日次3数値を日付順で返す | `AnalyticsService::timeSeries` | ✅ `tests/Unit/AnalyticsServiceTest.php` |
| 5-2 | 頻度：○×/チェックの出現回数（既知データで期待値） | `checkItemFrequency`/`checklistFrequency`（GROUP BY・`count(*)::int`） | ✅ 同上 |
| 5-3 | 回復パターン：回復行動翌日のメンタル余裕平均（固定データ検証） | `recoveryPattern`（翌日突合・with/without比較・delta） | ✅ 同上 |
| 5-4 | ダッシュボード・分析画面 | `DashboardController`/`AnalyticsController` + Blade + **Chart.js**（npm導入）+ `x-analytics-bars` コンポーネント | ✅ `tests/Feature/AnalyticsPageTest.php` |

**補足（実装判断）**:
- 時系列・頻度は PostgreSQL 集計（GROUP BY）に寄せ、回復パターンは翌日突合の明快さ優先で PHP 側集計。
- Chart.js は npm 同梱し `window.Chart` として公開、各画面で `@json` データを描画。
- ダッシュボードは直近14日推移＋ストレス平均＋思考のクセTOP3。分析画面は期間指定で時系列＋頻度横棒＋回復パターン。

---

### フェーズ6: 管理者機能 ✅ 完了
**ゴール**: ユーザ管理と共通マスタ管理。→ **達成（全104 tests passed）**

| # | 🔴 テスト | 🟢 実装 | 状況 |
|---|---|---|---|
| 6-1 | 一般ユーザが管理画面アクセスで403、管理者は200 | `EnsureUserIsAdmin`（`admin` エイリアス登録） | ✅ `tests/Feature/Admin/*` |
| 6-2 | ユーザCRUD・ロール変更・有効無効・メール重複 | `Admin/UserController` + `Store/UpdateUserRequest` | ✅ `UserManagementTest`（7件） |
| 6-3 | 共通マスタCRUD（`requires_text`/`is_none`・無効化） | `Admin/ChecklistOptionController` + Request | ✅ `ChecklistMasterTest`（6件） |
| 6-4 | 管理画面（ユーザ一覧/作成/編集・チェックリスト管理） | `admin/users/*`・`admin/checklist/index` | ✅ |

**補足（実装判断）**:
- サイドバーの管理メニューは `Route::has` に加え `isAdmin()` でも判定（一般ユーザには非表示）。
- チェックリスト選択肢の削除は物理削除せず無効化（過去ログ保持）。
- ユーザ管理一覧から各ユーザのログへ導線（`logs.index?user_id=`）。

---

### フェーズ7: 仕上げ ✅ 完了
**ゴール**: 品質確認・統一・完成。→ **達成（全106 tests passed / カバレッジ 92.9%）**

| 項目 | 状況 |
|---|---|
| カバレッジ計測 | ✅ pcov を Dockerfile に追加。**全体 92.9%**、コア（Services/Policies/Requests/Middleware）はほぼ100%（LogPolicy 100% / AnalyticsService 100% / LogService 96.9%） |
| 403 / 404 画面統一 | ✅ `errors/minimal` 基底 + `errors/403`・`errors/404`（日本語・ダッシュボード導線） |
| フラッシュメッセージ | ✅ 共通レイアウトで `status` を表示（各アクションで付与済み） |
| 追加テスト | ✅ check-items 削除（無効化）/ 他人削除403 を補完 |
| README | ✅ プロジェクト README を整備（セットアップ・docs 目次） |
| 最終サニティ | ✅ `migrate:fresh --seed` 成功、主要HTTP導線確認 |

---

## 8. 完成サマリ

全7フェーズ完了。**テスト 106 passed（216 assertions）/ カバレッジ 92.9%**。

| フェーズ | 内容 | テスト |
|---|---|---|
| 0 | 基盤＋テスト土台（Laravel13/Pest/専用PostgreSQL） | Smoke |
| 1 | DB・モデル・認可基盤（8テーブル・Observer） | Unit/Constraints/Seeder/DefaultCheckItems |
| 2 | ログCRUD（1日1件・○×・チェック・認可） | LogService/LogCrud/LogAuthorization/LogValidation |
| 3 | 一覧・絞り込み（日付・数値・管理者全件） | LogFilter |
| 4 | ○×項目マスタ（追加/改名/並替/無効化） | CheckItem |
| 5 | 見える化・分析（時系列/頻度/回復パターン・Chart.js） | AnalyticsService/AnalyticsPage |
| 6 | 管理者機能（ユーザ管理・共通マスタ） | Admin/UserManagement・ChecklistMaster |
| 7 | 仕上げ（カバレッジ・エラー画面・README） | — |

---

## 4. マイルストーン

| MS | 到達フェーズ | 完了時点で使える状態 |
|---|---|---|
| MS1 | F0–F1 | ログイン・DB構築・テスト土台完成 |
| **MS2** | **F2** | **毎日ログを記録できる（実用開始ライン）** |
| MS3 | F3–F4 | 振り返り・絞り込み・カテゴリ管理 |
| MS4 | F5 | 見える化・傾向分析 |
| MS5 | F6–F7 | 管理機能・完成 |

---

## 5. Makefile 追加予定ターゲット

```make
test:            # 全テスト実行     -> docker compose exec app ./vendor/bin/pest
test-filter:     # 絞り込み実行     -> ./vendor/bin/pest --filter=...
db-test-create:  # テストDB作成     -> mentallog_test を作成
coverage:        # カバレッジ計測   -> ./vendor/bin/pest --coverage
```

（既存: build / up / down / restart / ps / logs / create-project / install-breeze / sh / migrate / seed / fresh / npm-dev / npm-build / key）

---

## 6. テスト構成（ディレクトリ想定）

```
tests/
  Pest.php
  TestCase.php
  Unit/
    LogServiceTest.php          # 1日1件upsert・子テーブル保存
    AnalyticsServiceTest.php    # 時系列・頻度・回復パターン集計
    Models/
      UserTest.php              # isAdmin / リレーション
  Feature/
    Auth/                       # Breeze同梱
    LogCrudTest.php             # store/update/destroy/show
    LogAuthorizationTest.php    # ★他人ログ403・未ログイン302
    LogFilterTest.php           # 絞り込み
    CheckItemTest.php           # ○×マスタ + 認可
    Admin/
      UserManagementTest.php    # 管理者のみ
      ChecklistMasterTest.php
database/
  seeders/  (Role / ChecklistCategory / ChecklistOption / DefaultCheckItem)
```

---

## 7. 着手順

推奨: **フェーズ0（テスト土台）→ 1 → 2**（実用ライン MS2 まで一気に）。
特にフェーズ0を最初にしっかり作ることが TDD 成功の前提となる。
