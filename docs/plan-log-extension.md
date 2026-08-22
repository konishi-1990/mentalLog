# MentalLog ログ項目拡張プラン（TDD版）

本書は既存 MentalLog に対する **記録項目8点の追加と分析対応** の実装計画を定義する。
基礎設計は `require.md` / `architecture.md` / `model.md` / `disp.md`、既存の実装経緯は `plan.md`、
既存テストの一覧は `test.md` を参照。

作成日: 2026-08-11 / 対象ブランチ: `feat/extend-log-fields`

---

## 1. 背景（なぜ追加するか）

本番 `cony@cde.jp` のログ13件（2026-07-06〜08-10）を集計した結果、
**現行スキーマでは説明できない事象**が複数見つかった。追加項目はすべてこの実データに紐づく。

| 観測された事象 | 現行スキーマの限界 | 対応項目 |
|---|---|---|
| 体力と心の余裕が**逆相関**（r = -0.382）。体力10で余裕3の日がある一方、体力0で余裕6の日もある | 「日中の活動量」と「回復」が別物である可能性が高いが、**睡眠を記録していないため検証できない** | #1 睡眠 |
| 「昨日の話がフラッシュバックする」「前日のんを引きずっている」が繰り返し出現 | ログが当日で閉じており、**"引きずり"を定量化できない** | #2 持ち越し感 |
| `hardest_text` が「理不尽」「勝手に進んでいく」「拒否られた」と**統制不能な出来事に集中** | ストレスを"量"でしか持たず、**性質で分類できない** | #4 コントロール可能度 |
| 「何もできてない日」と「何かした日」でメンタル余裕に差がない（5.00 vs 4.71） | 回復行動が**やったか否かのみ**。効いていないのか測れていないのか判別不能 | #3 回復行動の計測 |
| 回復行動の「その他」が5件で最多（夕方サテン×2 / 2shot / 一本のビール / 邪魔された） | **選択肢が実態を表現できていない**。特に飲酒が回復行動に紛れている | #8 摂取物 |
| 自由記述に「上層部」「上からのやつ」など特定の人物が繰り返し登場 | ○×項目は領域（仕事/バンド/コミュニティ）止まりで、**"人"の粒度がない** | #6 相手タグ |
| 記録13件中、土日は2件のみ | 曜日は `logged_on` から導出できるが、**有給・出張・祝日は判別不能** | #7 勤務形態 |
| 7/19–7/28 に**11日の空白**、8/3–8/9 に8日の空白 | 欠測が「調子が良くて書かなかった」のか「書けないほど悪かった」のか不明 | #5 欠測の可視化 |

### 前提となる統計的注意

n=13 では有意（p<.05）と言えるのは概ね |r| ≳ 0.55。現時点で該当するのは
**ストレス × 心の余裕 = -0.672** のみで、他は傾向どまり。
したがって分析機能は「相関値だけを出す」のではなく、**必ず有効件数 n を併記する**こと。

---

## 2. 開発方針

- `plan.md` を踏襲し **TDD（🔴 Red → 🟢 Green → 🔵 Refactor）** で進める。
- **各フェーズは独立してコミット可能・デプロイ可能**な粒度とする。Phase 1 だけ本番に出しても壊れない。
- ビジネスロジックは `Service` 層へ寄せる（既存 `LogService` / `AnalyticsService` を拡張）。
- マスタ削除は**すべて論理削除**（`is_active = false`）。過去ログの表示を壊さないため。
- カテゴリ code をブレードに直書きしない。マスタ側にフラグを持たせて分岐する。

### 重要な制約 2 点

**① すべて nullable にする（必須）**
既存ログ（本番13件）は追加項目がすべて未入力。NOT NULL にすると migrate が失敗する。
また **全項目を空のまま保存できること**を回帰テストで固定する（下記 T1-5）。

**② 入力負担を増やさない**
現状フォームは 数値3 + ○×7 + テキスト2 + チェック3カテゴリ。全項目を足すと
数値7 + ○×7 + 相手タグ + テキスト2 + チェック4カテゴリ + 回復行動の詳細 になる。
**現在の記録率は36%（36日中13日）で、これ以上重くすると記録自体が止まりかねない。**

→ 対策：追加項目は `<details>` で折りたたんだ「くわしく」セクションにまとめ、**既定は閉じる**。
閉じたまま従来と同じ手数で保存できる状態を維持する。

---

## 3. 追加項目一覧

| # | 項目 | 保存先 | 型・範囲 | UI |
|---|---|---|---|---|
| 1 | 睡眠時間 | `logs.sleep_hours` | `decimal(3,1)` 0.0–24.0 | number（step 0.5） |
| 1 | 睡眠の質 | `logs.sleep_quality` | `smallint` 0–10 | スライダー |
| 2 | 前日からの持ち越し感 | `logs.carryover` | `smallint` 0–10 | スライダー |
| 4 | コントロール可能度 | `logs.controllability` | `smallint` 0–10 | スライダー |
| 7 | 勤務形態 | `logs.day_type` | `string(20)` | select |
| 3 | 回復行動の所要時間 | `log_checklist_selections.duration_min` | `smallint` | number |
| 3 | 効いた感 | `log_checklist_selections.effect_score` | `smallint` 0–10 | number |
| 6 | 相手タグ | `people` / `log_people` | 新テーブル | チェックボックス |
| 8 | 摂取物 | `checklist_options`（新カテゴリ `intake`） | **スキーマ変更なし** | 既存チェックリスト |
| 5 | 欠測 | — | **入力項目を作らない** | 分析画面で可視化 |

### #5 を入力項目にしない理由

「記録しなかった日の理由をワンタップで残す」は、先に取り下げた
**「平和な日のワンタップ入力」と実装機構が同一**（ログの無い日に対してワンタップでレコードを作る）。
ご指示を踏まえ入力項目としては作らず、**分析側で欠測そのものを可視化する**（Phase 6）。
入力を1つも増やさずに、7/19–7/28 のような空白に気づける。

### `day_type` の値

`weekday`（平日）/ `holiday`（休日）/ `paid_leave`（有給）/ `business_trip`（出張）/ `other`（その他）
定数は `app/Support/DayTypes.php` に集約する（既存 `app/Support/DefaultCheckItems.php` と同じ置き方）。

---

## 4. フェーズ計画

### フェーズ1: `logs` のスカラー項目（#1 #2 #4 #7）

**ゴール**: 睡眠・持ち越し感・コントロール可能度・勤務形態を保存・表示できる。

#### 🔴 Red — 先に書くテスト

| # | ファイル | テスト内容 |
|---|---|---|
| T1-1 | `tests/Unit/LogServiceTest.php` | `upsertDailyLog` が追加5項目を保存する |
| T1-2 | 〃 | 更新時に追加項目だけを変更できる |
| T1-3 | `tests/Feature/LogValidationTest.php` | `sleep_hours` が 0.0 未満 / 24.0 超で 422 |
| T1-4 | 〃 | `sleep_quality` / `carryover` / `controllability` が 0–10 の範囲外で 422 |
| T1-5 | 〃 | `day_type` が許可値以外で 422 |
| **T1-6** | `tests/Feature/LogCrudTest.php` | **追加項目を1つも送らなくても従来どおり保存できる**（← 最重要の回帰テスト） |
| T1-7 | 〃 | 追加項目を含めて保存すると詳細画面に表示される |

#### 🟢 Green — 実装

| 対象 | 内容 |
|---|---|
| `database/migrations/*_add_extra_scores_to_logs_table.php` | 5カラム追加（**すべて nullable**）。既存 `2026_07_06_100006_create_logs_table.php` に倣い `DB::statement` で `CHECK` 制約を追加する。**NULL は CHECK を通過する**（NULL 比較は UNKNOWN 扱いで制約を満たす）ため nullable と併用可 |
| `app/Models/Log.php` | `#[Fillable]` 属性と `casts()` に追加（既存の属性ベース記法を踏襲） |
| `app/Support/DayTypes.php` | 新規。`day_type` の定数とラベル |
| `app/Http/Requests/StoreLogRequest.php` | `nullable` ルール、`attributes()` に日本語名。`UpdateLogRequest` は継承のため変更不要（要確認） |
| `app/Services/LogService.php` | `upsertDailyLog()` の `updateOrCreate` 第2引数に追加 |
| `resources/views/logs/partials/form.blade.php` | 冒頭 `@php` の `$scores` 配列に 0–10 系を追加すれば**既存ループがそのままスライダーを描画する**。`sleep_hours` と `day_type` のみ個別マークアップ。追加分は `<details>` で囲む |
| `resources/views/logs/show.blade.php` | 冒頭 `@php` の `$scores` 連想配列に追加 |
| `resources/views/logs/index.blade.php` | 睡眠時間の列を追加（他は詳細画面のみ） |
| `database/factories/LogFactory.php` | 新カラムを `optional()` で |

#### 🔵 Refactor

- `show.blade.php` の `$scoreClass()` は「`stress` 以外は高いほど良い」と決め打ちしている。
  **`carryover` は高いほど悪い**ため、良し悪しの向きを配列で持つ形に直す。

---

### フェーズ2: 摂取物カテゴリ（#8）

**ゴール**: カフェイン・アルコールを回復行動と分けて記録できる。

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T2-1 | `tests/Feature/LogCrudTest.php` | `intake` カテゴリの選択肢を選んで保存できる |
| T2-2 | `tests/Unit/AnalyticsServiceTest.php` | `checklistFrequency($user, ..., 'intake')` が摂取物のみ集計する |

#### 🟢 Green

**コード変更なし。seeder のみ。**

- `database/seeders/ChecklistCategorySeeder.php` — `['code' => 'intake', 'name' => '摂取したもの', 'sort_order' => 4]`
- `database/seeders/ChecklistOptionSeeder.php` — コーヒー・エナドリ / アルコール / 市販薬 / どちらもなし（`is_none`）/ その他（`requires_text`）

`LogController::formData()` が全カテゴリを取得しているため、フォーム・保存・分析はいずれも改修なしで通る。
既存の「特になし」排他ロジック（`StoreLogRequest::withValidator`）もそのまま効く。

---

### フェーズ3: 回復行動の計測（#3）

**ゴール**: 「何が本当に効いたか」を所要時間と効果で測れる。

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T3-1 | `tests/Unit/LogServiceTest.php` | 選択と同時に `duration_min` / `effect_score` が保存される |
| T3-2 | 〃 | 未入力なら NULL で保存される（任意入力の担保） |
| T3-3 | `tests/Feature/LogValidationTest.php` | `effect_score` が 0–10 の範囲外で 422 |
| T3-4 | `tests/Unit/AnalyticsServiceTest.php` | `recoveryEffect()` が行動ごとの平均効果・平均時間・実施日数を返す |

#### 🟢 Green

| 対象 | 内容 |
|---|---|
| `*_add_tracks_effect_to_checklist_categories_table.php` | `tracks_effect boolean default false`。seeder で `recovery_action` のみ true |
| `*_add_effect_tracking_to_log_checklist_selections_table.php` | `duration_min` / `effect_score`（ともに nullable、`effect_score` に 0–10 の CHECK） |
| `app/Models/ChecklistCategory.php` / `LogChecklistSelection.php` | `#[Fillable]` と `casts()` を更新 |
| `app/Http/Requests/StoreLogRequest.php` | `selection_meta.*.duration_min` / `.effect_score` を追加。**必須にはしない** |
| `app/Services/LogService.php` | `syncChecklistSelections()` に meta 引数を追加して `create()` に流す |
| `resources/views/logs/partials/form.blade.php` | 既存の `requires_text` 入力欄と同じ要領で、`tracks_effect` カテゴリの選択肢にだけ入力欄を出す。表示切替は末尾の既存スクリプト（`[data-option]` の change ハンドラ）に条件を1つ足すだけ |

> カテゴリ code をブレードに直書きせず `tracks_effect` フラグで分岐する。
> 将来「頭の中のクセ」にも効果測定を付けたくなったとき、seeder の1行で済む。

---

### フェーズ4: 相手タグ（#6）

**ゴール**: 「誰との関わりで消耗しているか」を集計できる。

既存 `check_items` の実装を**構造ごと踏襲**する（テーブル構造・Policy・CRUD・並び替えがそのまま使える）。

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T4-1 | `tests/Feature/PersonTest.php` | 相手タグを追加・更新・論理削除できる |
| T4-2 | 〃 | **他人の相手タグを更新/削除できない（403）** |
| T4-3 | 〃 | 並び替えで他人の ID を混ぜても無視される |
| T4-4 | `tests/Unit/LogServiceTest.php` | ログ保存時に相手タグが紐づく |
| T4-5 | 〃 | **他人の person_id を送っても無視される**（`syncCheckItemValues` と同じ防御） |
| T4-6 | `tests/Unit/AnalyticsServiceTest.php` | `personFrequency()` が出現回数の多い順で返す |

#### 🟢 Green

| 対象 | 元にするファイル |
|---|---|
| `*_create_people_table.php` | `2026_07_06_100003_create_check_items_table.php` と同一構造 |
| `*_create_log_people_table.php` | `log_id` / `person_id` / `detail_text` nullable、`unique(log_id, person_id)` |
| `app/Models/Person.php` | `app/Models/CheckItem.php` |
| `app/Policies/PersonPolicy.php` | `app/Policies/CheckItemPolicy.php` |
| `app/Http/Controllers/PersonController.php` | `app/Http/Controllers/CheckItemController.php`（index/store/update/destroy/reorder） |
| `app/Http/Requests/StorePersonRequest.php` / `UpdatePersonRequest.php` | 既存 CheckItem 版 |
| `resources/views/people/index.blade.php` | `resources/views/check_items/index.blade.php` |
| `routes/web.php` | `auth` グループ内に check-items と同じ並びで追加 |
| `app/Services/LogService.php` | `syncPeople()` を追加（他人の person は無視） |
| `app/Http/Controllers/LogController.php` | `formData()` に `people` を追加 |
| `database/factories/PersonFactory.php` | 新規 |

> `UserObserver` による既定テンプレの複製（`app/Support/DefaultCheckItems.php`）は
> **相手タグには用意しない**。人は完全にユーザ固有のため、初期値を持たせる意味がない。

#### 🔵 Refactor

`CheckItemController` と `PersonController` の `reorder()` はほぼ同一になる。
共通化するか重複を許すかは実装時に判断（2箇所なら重複容認でよい）。

---

### フェーズ5: 分析対応

**ゴール**: 追加項目が分析画面とダッシュボードに反映される。

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T5-1 | `tests/Unit/AnalyticsServiceTest.php` | `correlations()` が相関値と**有効件数 n** を返す |
| T5-2 | 〃 | **有効データが1件以下のとき `corr()` が NULL を返しても落ちない** |
| T5-3 | 〃 | `dayTypeBreakdown()` が勤務形態別の平均を返す |
| T5-4 | 〃 | NULL 混在データで平均が壊れない（NULL を除外して集計する） |
| T5-5 | `tests/Feature/AnalyticsPageTest.php` | **追加項目が全件 NULL でも画面が 200 を返す**（← 本番データがこの状態） |
| T5-6 | 〃 | ダッシュボードが睡眠系列ゼロ件でも 200 を返す |

#### 🟢 Green

`app/Services/AnalyticsService.php` に追加:

| メソッド | 内容 |
|---|---|
| `timeSeries()` | select に `sleep_hours` / `sleep_quality` / `carryover` / `controllability` を追加（既存の呼び出し側は列が増えるだけ） |
| `correlations()` | 新規。PostgreSQL の `corr()` で主要指標間の相関 ＋ **有効件数 n** |
| `dayTypeBreakdown()` | 新規。`day_type` 別の各スコア平均 |
| `personFrequency()` | 新規。`checkItemFrequency()` と同じ join 構造 |
| `recoveryEffect()` | 新規。回復行動ごとの `avg(effect_score)` / `avg(duration_min)` / 実施日数 |

> `intake` の頻度は既存 `checklistFrequency($user, $from, $to, 'intake')` で取得できるためメソッド追加不要。
> 既存 `recoveryPattern()`（翌日のメンタル余裕比較）は**残して併記**する。測り方が違うため両方に意味がある。

ビュー:

- `resources/views/analytics/index.blade.php` — 相関マトリクス、勤務形態別平均、相手タグ頻度、摂取物頻度、回復効果。頻度系は既存 `<x-analytics-bars>` をそのまま使う
- `resources/views/dashboard.blade.php` — 既存チャートに睡眠時間の系列を追加。**「睡眠 × 心の余裕」の相関を1枚のカードで出す**（今回いちばん検証したい仮説のため）
- `AnalyticsController` / `DashboardController` — 新メソッドの結果を view に渡す

**各集計に「対象n件 / 入力済みm件」を必ず併記する。** NULL 混在を隠さない。

---

### フェーズ6: 未記録日の可視化（#5 の代替）

**ゴール**: 欠測が情報になる。**入力項目は1つも増やさない。**

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T6-1 | `tests/Unit/AnalyticsServiceTest.php` | `coverage()` が記録率を返す（13/36 → 0.36） |
| T6-2 | 〃 | 最長未記録連続日数を返す（7/19–7/28 → 10） |
| T6-3 | 〃 | 期間内に1件もログが無いとき 0 件で落ちない |

#### 🟢 Green

- `AnalyticsService::coverage()` — 記録率・最長未記録連続日数・未記録日一覧
- `resources/views/analytics/index.blade.php` — カレンダー風ヒートマップ（記録あり/なし）を1枚

---

## 5. テスト計画まとめ

既存 `test.md` の方針（Unit=Service/モデル、Feature=HTTP＋認可、認可テスト最重視）を継承する。

| ファイル | 区分 | 追加/新規 |
|---|---|---|
| `tests/Unit/LogServiceTest.php` | Unit | 追加（T1-1,2 / T3-1,2 / T4-4,5） |
| `tests/Unit/AnalyticsServiceTest.php` | Unit | 追加（T2-2 / T3-4 / T4-6 / T5-1〜4 / T6-1〜3） |
| `tests/Feature/LogCrudTest.php` | Feature | 追加（T1-6,7 / T2-1） |
| `tests/Feature/LogValidationTest.php` | Feature | 追加（T1-3,4,5 / T3-3） |
| `tests/Feature/AnalyticsPageTest.php` | Feature | 追加（T5-5,6） |
| `tests/Feature/PersonTest.php` | Feature | **新規**（T4-1〜3）。`CheckItemTest.php` を踏襲 |

`tests/Pest.php` の `logPayload()` ヘルパは**追加項目を含めない**まま維持する。
これにより既存テストが自動的に「追加項目なしでも保存できる」ことの回帰テストになる。

### 既知の失敗

`tests/Feature/ExampleTest.php` は `/` に 200 を期待しているが、`routes/web.php:12` の仕様で
302（ログイン or ダッシュボードへリダイレクト）を返すため**本作業以前から失敗している**。今回は触らない。

---

## 6. 検証手順

```bash
docker compose up -d
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed --class=ChecklistCategorySeeder
docker compose exec -T app php artisan db:seed --class=ChecklistOptionSeeder
docker compose exec -T app ./vendor/bin/pest        # または make test
./bin/sql.sh -c "\d logs"                            # 追加カラムと CHECK 制約を確認
```

### 手動確認

| # | 画面 | 確認内容 |
|---|---|---|
| 1 | `/logs/create` | 「くわしく」を**開かずに**保存できる（従来と同じ手数） |
| 2 | `/logs/create` | 「くわしく」を開いて全項目入力 → `/logs/{id}` に反映される |
| 3 | `/people` | 相手タグの追加・並び替え・無効化 |
| 4 | `/analytics` | **既存13件（追加項目すべて NULL）でエラーにならず**「入力済み0件」と表示される ← 本番データがこの状態のため最重要 |
| 5 | `/dashboard` | 睡眠系列が空でもチャートが壊れない |

---

## 7. リリース

既存フローどおり。

```
main で実装 → main→product の PR を作成・マージ
→ GitHub Actions「Deploy to Product (VPS)」が SSH デプロイ
→ script/deploy.sh 内で php artisan migrate --force まで自動実行
```

**マイグレーションは本番で自動実行される**ため、nullable の担保（T1-6）が緑であることを
マージ前に必ず確認すること。seeder は自動実行されないので、
`intake` カテゴリ追加後は `./bin/ssh.sh php artisan db:seed --class=ChecklistCategorySeeder` を手動実行する。

---

## 8. 着手順

推奨は **フェーズ1 → 2 → 3 → 4 → 5 → 6**。

フェーズ1と2だけでも単独で価値がある（睡眠の記録が始まり、飲酒が回復行動から分離される）ため、
ここまでを先に本番投入し、データが溜まり始めてからフェーズ5の分析を作るのが最も無駄がない。
フェーズ5・6を先に作っても、母数が n=13 のままでは読み取れる情報がほとんどない。
