# MentalLog アプリ改修プラン（2026-09 分析レポート反映版）

本書は `docs/analysis/report-202609.md`（対象: 本番で実データを持つ利用者1名 / 2026-07-06〜09-07 / n=28）の
提案事項（§4 追加したいログ項目 / §5 分析機能への提案）を、**実装可能な単位に落とした計画**である。

基礎設計は `require.md` / `architecture.md` / `model.md` / `disp.md`、
直前の拡張は `plan-log-extension.md`（全6フェーズ・PR #4 でリリース済み）を参照。

作成日: 2026-09-09 / 対象ブランチ: `feat/analysis-followup`

> ✅ **フェーズ1・フェーズ2は実装済み・本番反映済み**（2026-09-09 / PR #6・#7）。
> 実装時の設計変更は §10、本番での検証結果は `docs/deploylogs/deploy-7-20260909-0223.md`。
> フェーズ3〜6 は未着手（要判断 A/B/C と、フェーズ1・2 リリース後の入力率観測が前提）。
>
> 🔴 **本番反映時に §1 の A・B の真因が判明した。** 本番の `checklist_categories` は3件しか
> 無く（`intake` 不在・`tracks_effect` = false）、**PR #5（2026-08-23）の seeder が未実行だった**。
> つまり `effect_score` が28日分すべて NULL だったのは「0-10 の自由入力だから埋まらなかった」
> のではなく **計測欄が一度も描画されていなかった**ためで、`intake` が0件だったのは
> **カテゴリ自体が本番に存在しなかった**ため。詳細は §10.5。
> フェーズ1の3段階ラジオ化は自由入力より確実に良いため有効だが、**その効果は今回はじめて
> 検証可能になった**点に注意（過去の入力率0%は3段階化の根拠にはならない）。

---

## 目次
- [1. 何が問題か（レポートの要点）](#1-何が問題かレポートの要点)
- [2. 開発方針](#2-開発方針)
- [3. 提案事項の採否とフェーズ割り](#3-提案事項の採否とフェーズ割り)
- [4. 要判断事項（着手前にご確認いただきたい3点）](#4-要判断事項着手前にご確認いただきたい3点)
- [5. フェーズ計画](#5-フェーズ計画)
- [6. テスト計画まとめ](#6-テスト計画まとめ)
- [7. 検証手順](#7-検証手順)
- [8. リリース](#8-リリース)
- [9. 着手順とスコープの絞り方](#9-着手順とスコープの絞り方)
- [10. 実装記録（フェーズ1・2）](#10-実装記録フェーズ12)

---

## 1. 何が問題か（レポートの要点）

改修の動機は「項目が足りない」ことではない。**作った機能が動いていない／数字が読めない**の2点である。

| # | 事象 | 根拠（レポート） | 種別 |
|---|---|---|---|
| A | `effect_score` / `duration_min` が **28日分すべて NULL**。`AnalyticsService::recoveryEffect()`（`AnalyticsService.php:280`）が実質空を返し続けている | §1 拡張項目の入力率 | 🔴 **実装済み機能の死亡** |
| B | `intake` カテゴリの選択が **0件**。マスタ（`ChecklistOptionSeeder`）にはあるのに使われず、飲酒の記録が回復行動の「その他」に入っている | §1 / §4 #6 | 🔴 **導線の失敗** |
| C | 「体力」が**ストレスとも余裕とも無相関**（r=+0.03 / −0.06）。前半 r=−0.23 → 後半 r=+0.11 で符号反転＝**途中で解釈が入れ替わった疑い** | §3 | 🔴 **項目そのものが分析に使えない** |
| D | 「同日に重なったストレス源の数」が余裕を決めている（1件→2件で **−1.93 の崖**）。これは現行 `checkItemFrequency()` の単純頻度では**絶対に見えない** | §2 ① | 🟡 **分析の切り口不足** |
| E | ストレスは日ごとに独立（前日→翌日 r=+0.08）、余裕は持ち越す（**r=+0.46**）。この自己相関を出す機能がない | §2 ④ | 🟡 同上 |
| F | 相関表のトップが n=8 の参考値（持ち越し感×コントロール可能度 −0.83）。n は併記済み（`AnalyticsService.php:75`）だが**並び順がミスリードを生む** | §5 #5 | 🟡 表示の問題 |
| G | 記録率 **44%**、かつ**しんどい日の翌日が抜けている**（翌日欠測日のストレス 7.56 vs 6.83）。平均値が実態より楽観的 | §2 ⑨ / §3 | 🟡 データの読み方 |
| H | 入力時刻が平均 **16.1時**。夜のイベントが翌日ログに混ざる（28件中5件が翌日入力） | §3 | 🟡 測定タイミング |

### レポートの結論を計画に反映する

> **分析の精度を上げる一番の投資は、新項目の追加より記録の継続**（§6）

したがって本計画は **「入力項目を増やすフェーズ」を後ろに置き、「既存データで効く改修」を先に出す**構成にする。
A・B・D・E・F・G は **スキーマ変更ゼロ、または入力負担ゼロ**で対応でき、しかも**過去28件に遡って効く**。

---

## 2. 開発方針

- `plan.md` / `plan-log-extension.md` を踏襲し **TDD（🔴 Red → 🟢 Green → 🔵 Refactor）** で進める。
- **各フェーズは独立してコミット可能・デプロイ可能**な粒度とする。
- ビジネスロジックは Service 層（`LogService` / `AnalyticsService`）へ寄せる。
- 追加カラムは**すべて nullable**。本番28件は未入力のため NOT NULL では migrate が落ちる。
- **カテゴリ code をブレードに直書きしない**（マスタ側のフラグで分岐）。
  現行 `form.blade.php:194` の `$category->code === 'thought_habit'` は既にこの規約に違反しており、フェーズ1の Refactor で解消する。
- 過去データの意味を書き換える変更（列の再定義・ラベル反転）は行わない。**新カラム追加＋旧カラム凍結**とする。

### 入力負担の上限（最重要の制約）

現状フォームの操作数は **数値3（必須スライダー）＋任意数値4＋○×7＋相手タグ＋テキスト2＋チェック4カテゴリ**。
`plan-log-extension.md` §2 の時点で記録率36%、**現在44%**。これ以上素朴に足すと記録自体が止まる。

本計画では以下を守る:

1. **必須入力を増やすのは「すでに何かを選んだとき」だけ**（フェーズ1の `effect_score` がこれ）。
2. 新規の任意項目は**すべて `<details>`「くわしく」内**に置き、既定は閉じる。
3. **0–10 スライダーを増やさない。** 3段階のボタン（1タップ）を優先する。
   スライダーは「触らないと入らない」ため、`effect_score` が28日間ゼロだった原因そのものである。

---

## 3. 提案事項の採否とフェーズ割り

### §4 追加で取得したいログ項目

| レポート§4 | 項目 | 採否 | フェーズ | スキーマ変更 | 入力負担 |
|---|---|---|---|---|---|
| 🔴 #1 | `effect_score` を回復行動選択時に必須化 | **採用** | 1 | なし | 選択時のみ +1タップ |
| 🔴 #2 | ストレス源ごとの強度（軽/中/重） | **採用** | 3 | `log_check_item_values.severity` | ○のときのみ +1タップ |
| 🔴 #3 | イベント発生時刻帯 | **採用** | 4 | `logs.event_time_band` | 任意（くわしく内） |
| 🔴 #4 | 記録スキップ理由（1タップ） | **保留** | — | — | **要判断 A** |
| 🟡 #5 | 就寝時刻 / 起床時刻 | **採用** | 4 | `logs.bedtime` / `logs.wake_time` | 任意（`sleep_hours` を置換） |
| 🟡 #6 | `intake` の入力導線 | **採用** | 1 | `checklist_categories.description` のみ | 増えない |
| 🟡 #7 | 在宅 / 出社 | **採用** | 6 | `logs.work_location` | 任意 |
| 🟡 #8 | 人（people）の関与の重さ | **採用** | 3 | `log_people.weight` | 選択時のみ +1タップ |
| 🟢 #9 | ポジティブ側の指標 | **採用** | 6 | `logs.positive_score` | 任意 |
| 🟢 #10 | 「体力」の定義修正 / 疲労度への反転 | **採用（新カラム方式）** | 5 | `logs.fatigue` 追加＋`stamina` 凍結 | 差し替えのため増えない |
| 🟢 #11 | 翌朝時点の主観 | **採用** | 6 | `logs.morning_capacity` | 任意 |

### §5 分析機能への提案

| レポート§5 | 提案 | 採否 | フェーズ | 備考 |
|---|---|---|---|---|
| #1 | `stressSourceOverlap()` を追加 | **採用** | 2 | 既存28件に遡って効く。本人の実感と一致する唯一の指標（§2①） |
| #2 | `thoughtHabitCount()` を追加 | **採用** | 2 | 同上 |
| #3 | `coverage()` に欠測バイアスの提示 | **採用** | 2 | 入力項目を増やさずに G に対処できる |
| #4 | 自己相関（前日→翌日） | **採用** | 2 | 「ストレスは毎日リセット／余裕は溜まる」は行動指針に直結（§2④） |
| #5 | `correlations()` に n<10 の参考値バッジ | **採用** | 2 | 並び替えも同時に直す |
| （追加提案） | `inputLag()`＝当日入力／翌日入力の内訳 | **採用** | 2 | `logs.created_at` と `logged_on` の差分。**スキーマ変更ゼロ**で H を可視化できる |
| （追加提案） | ダッシュボードに未記録連続日数のバナー | **採用** | 2 | `coverage()` の既存戻り値（`longest_gap`）を使うだけ。記録継続への最小の打ち手 |

### 今回は対象外とするもの

| 項目 | 理由 |
|---|---|
| 入力リマインド通知（H の根本対策） | メール／PWA push の基盤が無く、本計画の他フェーズ全部を合わせたより重い。**H は時刻帯項目（フェーズ4）と `inputLag()`（フェーズ2）で「取れていないことが見える」状態までにする** |
| 睡眠時間のレンジ拡大（§3） | アプリ側では解決しない（実際に 6.0〜8.0 しか寝ていない）。就寝/起床時刻（フェーズ4）で分散のある変数に置き換えるのが唯一の打ち手 |

---

## 4. 要判断事項（着手前にご確認いただきたい3点）

### 要判断 A — 記録スキップ理由（レポート §4 #4）

レポートは「今日は書けなかった：理由（しんどすぎ/忙しい/忘れた）を1タップで残す」を 🔴 で提案している。
しかしこれは **`plan-log-extension.md` §3 で「作らない」と決めた「平和な日のワンタップ入力」と実装機構が同一**
（ログの無い日に対してワンタップでレコードを作る）であり、当時のご指示で取り下げた経緯がある。

**再開するかどうかはご判断が必要**。本計画では既定で保留とし、代替として
フェーズ2で `coverage()` に欠測バイアスの提示（§5 #3）を入れる。
これは**入力を1つも増やさずに** G の「平均値をどれだけ割り引いて読むべきか」までは答えられる。
ただし「なぜ書けなかったか」は原理的に取れないため、**欠測の原因分析は諦めることになる**。

| 選択肢 | 実装 | 影響 |
|---|---|---|
| A-1（既定・推奨） | 入力は作らず `coverage()` の可視化のみ | 入力負担ゼロ。欠測理由は不明のまま |
| A-2 | `logs` に「未記録」種別を持たせる | 前回の決定を覆す。`logs.stress` 等が NOT NULL なので**スキーマ設計から見直しが必要**（別テーブル `log_skips` のほうが安全） |

### 要判断 B — 「体力」の扱い（レポート §3 / §4 #10）

現状 `stamina` は**分析に使えない**（無相関 かつ 期間で符号反転）。選択肢は2つ。

| 選択肢 | 実装 | 影響 |
|---|---|---|
| B-1（推奨） | `logs.fatigue`（0–10・高いと疲れている）を新設し、フォームから `stamina` を外して**凍結**。分析は 2026-09-XX 以降 `fatigue`、それ以前は `stamina` として別扱い | 過去28件の解釈を書き換えない。**時系列が一度分断される**（グラフに切り替え線が入る） |
| B-2 | `stamina` のラベルと補足文だけ直す | 実装は最小だが、**過去28件の意味が変わる**。§3 の符号反転がそのまま残り、分析は当面使えない |

B-1 で進める前提で計画（フェーズ5）を書いているが、**時系列の分断を許容するかはご判断**。

### 要判断 C — 入力負担の総量

フェーズ3〜6を全部入れると、任意項目が現在の4個から **10個前後**になる。
記録率44%の現状で、たとえ「くわしく」に畳んでもフォームの見た目の重さは増える。

**推奨は「フェーズ1・2を先に本番投入し、記録率と `effect_score` の入力率を2〜4週間見てから
フェーズ3以降のスコープを決める」**（→ §9）。フェーズ3以降を一括で承認いただく必要はない。

---

## 5. フェーズ計画

### フェーズ1: 死んでいる入力導線の修復（🔴 A・B）

**ゴール**: `recoveryEffect()` が意味のある値を返し始め、飲食物が回復行動から分離される。
**スキーマ変更はほぼ無し。入力負担は「回復行動を選んだときだけ +1タップ」。**

#### 設計判断: 効いた感を 0–10 スライダーから **3段階ボタン**に変える

現行は `<input type="number" placeholder="0-10">`（`form.blade.php:234`）。
**空欄のまま送信でき、しかも自分で数字を考える必要がある**ため28日間ゼロだった。

レポートは「デフォルト5でプリセットしてスライダー表示」を提案しているが、
**既定値を置くと全件5が入って情報量ゼロになる**（触らなくても値が入るため、入力率100%・分散0の最悪形）。

→ **既定値なしの3段階ラジオ**にする。1タップで済み、かつ明示的な選択を強制できる。

| 表示 | 保存値（`effect_score`） |
|---|---|
| 効かなかった | 2 |
| 少し効いた | 5 |
| かなり効いた | 8 |

`effect_score` は `smallint` / CHECK `0..10` のままなので**カラム変更もマイグレーションも不要**、
既存の `recoveryEffect()`（平均を取るだけ）もそのまま動く。

`duration_min` も同様に4択（`〜15分` → 15 / `〜30分` → 30 / `〜1時間` → 60 / `1時間以上` → 120）とし、
**任意のまま**にする（効果測定に必須なのは `effect_score` のほう）。

#### 「何もできてない」を除外する仕組み

`recovery_action` の選択肢「何もできてない」は 12/28日（43%）で選ばれており、
これに「効いた感」を要求するのは不整合。**`is_none = true` に変更する**ことで解決する。

- 既存の排他ロジック（`StoreLogRequest::withValidator`）が効き、他の回復行動と同時選択できなくなる
  （§2⑤ のとおり実データでも排他になっている）
- 効果測定パネルの表示条件に `is_none = false` を足すだけで、必須化の対象から外れる
- **過去の選択レコードには影響しない**（バリデーションは新規送信のみ）

#### 🔴 Red — 先に書くテスト

| # | ファイル | テスト内容 |
|---|---|---|
| T1-1 | `tests/Feature/LogValidationTest.php` | `tracks_effect` カテゴリの選択肢を選んだのに `effect_score` 未送信で **422** |
| T1-2 | 〃 | `is_none` の選択肢（何もできてない）だけを選んだときは `effect_score` 不要で **保存できる** |
| T1-3 | 〃 | `tracks_effect` でないカテゴリ（頭の中のクセ等）では `effect_score` を要求しない |
| T1-4 | 〃 | 選択していない選択肢の `selection_meta` が送られてきても無視される |
| **T1-5** | `tests/Feature/LogCrudTest.php` | **チェックリストを1つも選ばなければ従来どおり保存できる**（← 回帰テスト。`logPayload()` が `checklist => []` なので既存テスト全体がこれを担保する） |
| T1-6 | 〃 | 3段階ボタンで選んだ値が `effect_score` に 2/5/8 として保存される |
| T1-7 | `tests/Unit/AnalyticsServiceTest.php` | `recoveryEffect()` が「何もできてない」を集計対象に含めない |
| T1-8 | `tests/Feature/LogCrudTest.php` | `intake` カテゴリの説明文がフォームに表示される |

#### 🟢 Green — 実装

| 対象 | 内容 |
|---|---|
| `database/migrations/*_add_description_to_checklist_categories_table.php` | `description` text nullable を追加。**これだけがフェーズ1のスキーマ変更** |
| `app/Models/ChecklistCategory.php` | `#[Fillable]` に `description` を追加 |
| `database/seeders/ChecklistCategorySeeder.php` | 各カテゴリに `description` を設定。`thought_habit` に「超重要」の文言、`intake` に「**コーヒー・お酒・薬はこちら。回復行動ではなくこちらに入れてください**」 |
| `database/seeders/ChecklistOptionSeeder.php` | `recovery_action` の「何もできてない」に `is_none => true`。「その他」の補足に「飲食物は『摂取したもの』へ」の誘導を添える |
| `app/Support/EffectLevels.php` | **新規**。3段階ラベル ⇄ スコア（2/5/8）の対応。`DayTypes.php` と同じ置き方 |
| `app/Support/DurationBuckets.php` | **新規**。4択ラベル ⇄ 分（15/30/60/120） |
| `app/Http/Requests/StoreLogRequest.php` | `selection_meta.*.effect_score` を `Rule::in(EffectLevels::scores())` に。`withValidator` に「選択された `tracks_effect` かつ `is_none=false` の選択肢は `effect_score` 必須」を追加 |
| `resources/views/logs/partials/form.blade.php` | `:221-242` の効果測定ブロックを3段階ラジオ＋4択セレクトに差し替え。表示条件に `! $option->is_none` を追加。カテゴリ見出しの下に `$category->description` を表示 |
| `resources/views/logs/show.blade.php` | `:154-158` の表示を `EffectLevels::label()` 経由に（数値ではなく「かなり効いた」と出す） |
| `resources/views/analytics/index.blade.php` | 回復行動の効果カード（`:169`）に `EffectLevels` のラベルを併記 |

#### 🔵 Refactor

- `form.blade.php:194` の `$category->code === 'thought_habit'` 直書きを `$category->description` に置き換える（規約違反の解消）。
- `LogService::syncChecklistSelections()` の `$meta` 読み出しは現状のままで動くが、
  **選択されていない選択肢の meta を捨てている**ことをコメントで明示する（T1-4 の根拠）。

#### 本番反映時の注意

seeder は自動実行されないため、デプロイ後に手動実行が必要:

```bash
./bin/ssh.sh php artisan db:seed --class=ChecklistCategorySeeder
./bin/ssh.sh php artisan db:seed --class=ChecklistOptionSeeder
```

どちらも `updateOrCreate` なので冪等。ただし **「何もできてない」の `is_none` 変更は
既存の28件の選択レコードには影響しない**ことを、マージ前に上記テスト（T1-7）で確認する。

---

### フェーズ2: 既存データで効く分析の追加（🟡 D・E・F・G・H）

**ゴール**: 記録を1件も増やさずに、レポートで「効いた」切り口をアプリ上で見られるようにする。
**スキーマ変更なし・入力負担ゼロ・過去28件に遡って効く。** 本計画で最も費用対効果が高い。

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T2-1 | `tests/Unit/AnalyticsServiceTest.php` | `stressSourceOverlap()` が「同日の○件数」ごとの n・平均ストレス・平均余裕を返す |
| T2-2 | 〃 | ○が0件の日も 0件グループとして返る（`logs` 起点で集計する担保） |
| T2-3 | 〃 | `thoughtHabitCount()` が「クセの個数」ごとの平均を返す。`is_none` の選択は 0個として数える |
| T2-4 | 〃 | `autocorrelations()` が前日→翌日の r と n を返す。**連続していない日はペアに含めない** |
| T2-5 | 〃 | 期間内のログが0件／1件でも `autocorrelations()` が落ちず r=null を返す |
| T2-6 | 〃 | `coverage()` に `next_missing`（翌日が欠測だった日の平均）が入り、記録済み日との差が取れる |
| T2-7 | 〃 | `inputLag()` が「当日入力／翌日入力／それ以降」の件数と平均入力時刻を返す |
| T2-8 | `tests/Feature/AnalyticsPageTest.php` | ログ0件でも新カード全部が 200 を返す |
| T2-9 | 〃 | 相関表が **n≧10 を優先して並び、n<10 に「参考値」バッジが付く** |
| T2-10 | 〃 | ダッシュボードで未記録が3日以上続いていればバナーが出る／連続していなければ出ない |

#### 🟢 Green

`app/Services/AnalyticsService.php` に追加:

| メソッド | 内容 | レポート根拠 |
|---|---|---|
| `stressSourceOverlap()` | `logs` を起点に `log_check_item_values.is_on` の同日件数で group by → n・avg(stress)・avg(mental_capacity)。**○が0件の日を落とさないため `logs` 側から左結合する**のが要点 | §2 ① / §5 #1 |
| `thoughtHabitCount()` | 同構造。`thought_habit` カテゴリの選択数（`is_none` は 0 として扱う） | §2 ② / §5 #2 |
| `autocorrelations()` | 前日→翌日のペアを作り `stress→stress` / `mental_capacity→mental_capacity` / `stress→mental_capacity` / `carryover→mental_capacity` の r と n。**既存 `recoveryPattern()` の「日付キーで翌日を引く」実装をそのまま流用できる** | §2 ④ / §5 #4 |
| `coverage()` に追記 | `next_missing` = 翌日が欠測だった日の avg(stress) / avg(mental_capacity) と、翌日も記録した日の同値。**平均をどれだけ割り引くかが読める** | §2 ⑨ / §5 #3 |
| `inputLag()` | `logged_on` と `created_at::date` の差で 当日 / 翌日 / それ以降 を分類、avg(`created_at` の時刻)。**`logs.timestamps` が既にあるのでカラム追加不要** | §3 |

ビュー・コントローラ:

- `resources/views/analytics/index.blade.php`
  - 「重なり数 × スコア」「クセの個数 × スコア」の2カード（数値表で十分。既存 `<x-analytics-bars>` は頻度用なので流用しない）
  - 「前日→翌日の持ち越し」カード（r と n の表）
  - 記録率カード（`:54-78`）に **欠測バイアスの行**と**入力ラグ**を追記
  - 相関表（`:80-111`）を **n 降順＋|r| 降順**に並べ替え、`n < 10` に `参考値` バッジを付ける
- `resources/views/dashboard.blade.php` — **未記録が3日以上続いているときだけ**バナーを出す（`coverage()['longest_gap']` を使う）
- `AnalyticsController` / `DashboardController` — 新メソッドの結果を view に渡す

> 相関表の閾値 10 は「n=28 で信頼できるのはストレス×余裕だけ」というレポート §2⑥ の判断に合わせた暫定値。
> `AnalyticsService` の定数として切り出し、後から動かせるようにする。

#### 🔵 Refactor

`stressSourceOverlap()` と `thoughtHabitCount()` は「同日の該当件数で group by して平均を出す」同一構造。
private ヘルパに寄せる（2箇所なので重複容認でも可。実装時に判断）。

---

### フェーズ3: 強度を測る（🔴 §4 #2 / 🟡 §4 #8）

**ゴール**: 「仕事は常時ON（21/28日）」で説明できなくなっている変動を、強度で重み付けできる。
フェーズ2の「重なり数」が閾値構造を示したので（1件→2件で −1.93）、**次に効くのは件数ではなく重み**。

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T3-1 | `tests/Unit/LogServiceTest.php` | ○のとき `severity` が保存され、✕のとき **NULL に落ちる**（`detail_text` と同じ挙動） |
| T3-2 | `tests/Feature/LogValidationTest.php` | `severity` が 1–3 以外で 422、未送信なら 200 |
| T3-3 | `tests/Unit/LogServiceTest.php` | `log_people.weight` が保存される／他人の person_id は無視される（既存 T4-5 の担保を壊さない） |
| T3-4 | `tests/Unit/AnalyticsServiceTest.php` | `stressSourceLoad()` が「同日の severity 合計」ごとの平均を返す |
| T3-5 | 〃 | `severity` が全件 NULL でも落ちず、`load` 集計が空を返す |

#### 🟢 Green

| 対象 | 内容 |
|---|---|
| `*_add_severity_to_log_check_item_values_table.php` | `severity` smallint nullable ＋ CHECK `1..3` |
| `*_add_weight_to_log_people_table.php` | `weight` smallint nullable ＋ CHECK `1..3` |
| `app/Support/SeverityLevels.php` | 新規。`1=軽い / 2=中くらい / 3=重い`。`DayTypes.php` と同じ置き方 |
| `app/Models/LogCheckItemValue.php` | `#[Fillable]` と `casts()` に `severity` |
| `app/Models/Log.php` | `people()` の `withPivot` に `weight` を追加 |
| `app/Http/Requests/StoreLogRequest.php` | `check_items.*.severity` / `people_weights.*` を nullable で追加 |
| `app/Services/LogService.php` | `syncCheckItemValues()` — ✕なら `severity` も NULL。`syncPeople()` — `weight` を pivot に |
| `resources/views/logs/partials/form.blade.php` | ○を選んだときだけ出る補足テキスト欄の**横**に3ボタン。相手タグも同様。既存の `[data-toggle-detail]` / `[data-person]` ハンドラに1行足すだけ |
| `resources/views/logs/show.blade.php` | 強度ラベルの表示 |
| `app/Services/AnalyticsService.php` | `stressSourceLoad()` を追加（`stressSourceOverlap()` の severity 合計版） |
| `resources/views/analytics/index.blade.php` | 「重なり数」カードの隣に「強度合計」カード。相手タグ頻度に平均 weight を併記 |

> レポート §4 #8 のとおり特定の相手タグ1件が6日登場し内容も一貫している（§2③ でも相手タグが最有力）。
> 相手タグの入力は 8/21〜9/4 の14件で止まっているため、**weight より先に「入力が続くか」が課題**。
> フェーズ3の相手タグ側は、フェーズ2で相手タグ頻度カードが見えるようになった後の入力状況を見てから判断してよい。

---

### フェーズ4: 時刻を取る（🔴 §4 #3 / 🟡 §4 #5）

**ゴール**: 自由記述に頻出する「就寝前」「夕方から夜」といった時間帯の情報が構造化され、
睡眠が **6.0〜8.0 しか分散のない `sleep_hours`** から時刻ベースに置き換わる。

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T4-1 | `tests/Feature/LogValidationTest.php` | `event_time_band` が許可値以外で 422、未送信なら 200 |
| T4-2 | 〃 | `bedtime` / `wake_time` が時刻形式以外で 422 |
| T4-3 | `tests/Unit/LogServiceTest.php` | 両方の時刻が入っていれば `sleep_hours` が**差分から自動計算**される（23:30→07:00 = 7.5） |
| T4-4 | 〃 | **日付を跨ぐケース**（23:30→07:00）と跨がないケース（01:00→07:00）の両方 |
| T4-5 | 〃 | 時刻が片方だけ／両方なしのときは `sleep_hours` の手入力値を尊重する（既存挙動の維持） |
| T4-6 | `tests/Unit/AnalyticsServiceTest.php` | `timeBandBreakdown()` が時刻帯別の平均（当日ストレス・翌日持ち越し）を返す |

#### 🟢 Green

| 対象 | 内容 |
|---|---|
| `*_add_event_time_and_sleep_times_to_logs_table.php` | `event_time_band` string(20) / `bedtime` time / `wake_time` time（すべて nullable） |
| `app/Support/TimeBands.php` | 新規。`morning`（朝）/ `daytime`（日中）/ `evening`（夕方）/ `night`（夜）/ `before_bed`（就寝前） |
| `app/Models/Log.php` | `#[Fillable]` と `casts()` |
| `app/Http/Requests/StoreLogRequest.php` | `Rule::in(TimeBands::codes())`、`date_format:H:i` |
| `app/Services/LogService.php` | `resolveSleepHours()` を追加。両時刻が揃っていれば差分（日跨ぎは +24h）で `sleep_hours` を上書き |
| `resources/views/logs/partials/form.blade.php` | 「くわしく」内に時刻帯セレクトと `<input type="time">` 2つ。**`sleep_hours` は「時刻を入れれば自動計算」の注記付きで残す** |
| `app/Services/AnalyticsService.php` | `timeBandBreakdown()`。`CORRELATION_PAIRS` は変更しない（時刻は数値でないため） |
| `resources/views/analytics/index.blade.php` | 「時刻帯 × 翌日の持ち越し感」カード（レポート §4 #3 の狙い） |

> `sleep_hours` を自動計算で上書きする設計は、**既存13件の手入力値を壊さない**（時刻が NULL のため計算されない）。
> 過去データとの連続性が保てるので、`stamina` の件（要判断 B）と違い分断は起きない。

---

### フェーズ5: 「体力」の再定義（🔴 C / 要判断 B）

**ゴール**: 分析に使えない項目を、使える項目に置き換える。**要判断 B で B-1 を選んだ場合のみ着手。**

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T5-1 | `tests/Feature/LogValidationTest.php` | `fatigue` が 0–10 の範囲外で 422、未送信なら 200 |
| T5-2 | `tests/Feature/LogCrudTest.php` | **`stamina` を送らなくても保存できる**（現状 `required` なので、この変更が本フェーズの核） |
| T5-3 | 〃 | 既存ログ（`stamina` あり・`fatigue` なし）の詳細画面が 200 を返し、`stamina` が表示される |
| T5-4 | `tests/Unit/AnalyticsServiceTest.php` | `correlations()` に `fatigue` の組が入り、`stamina` の組も残る（両方が n 併記で出る） |
| T5-5 | `tests/Feature/AnalyticsPageTest.php` | `fatigue` が全件 NULL でも 200 |

#### 🟢 Green

| 対象 | 内容 |
|---|---|
| `*_add_fatigue_to_logs_table.php` | `fatigue` smallint nullable ＋ CHECK `0..10` |
| 同マイグレーション | **`logs.stamina` の NOT NULL を解除**（凍結後の新規ログでは送られてこないため） |
| `app/Models/Log.php` | `#[Fillable]` / `casts()` に `fatigue` |
| `app/Http/Requests/StoreLogRequest.php` | `stamina` を `required` → `nullable`、`fatigue` を `nullable` で追加 |
| `resources/views/logs/partials/form.blade.php` | `$scores` から `stamina` を外し、`fatigue`（「疲労度・高いと疲れている」）を必須スライダーとして追加 |
| `resources/views/logs/show.blade.php` | `$scores` に `fatigue`（`higher_is_better => false`）。`stamina` は**値がある場合のみ**表示（過去ログ用） |
| `resources/views/logs/index.blade.php` | 列を `stamina` → `fatigue`（過去分は `stamina` にフォールバック） |
| `app/Services/AnalyticsService.php` | `METRIC_LABELS` に `fatigue`、`CORRELATION_PAIRS` に `fatigue × mental_capacity` / `fatigue × sleep_hours` を追加。`stamina` の組は残す |
| `resources/views/analytics/index.blade.php` / `dashboard.blade.php` | 時系列グラフに `fatigue` 系列を追加。**切り替え日に注記を出す**（凡例に「9月XX日より疲労度へ変更」） |
| `database/factories/LogFactory.php` | `fatigue` を `optional()` で |

#### 🔵 Refactor

`show.blade.php` / `index.blade.php` / `form.blade.php` に3箇所ある `$scores` 定義が
ずれ始めているため、`app/Support/LogMetrics.php` に一元化する（ラベル・向き・必須/任意・凍結フラグ）。
`AnalyticsService::METRIC_LABELS` もここから引く。

> ⚠️ このフェーズは**マージすると本番の入力項目が変わる**。フェーズ1〜4と違い後戻りが効きにくいので、
> 要判断 B の確認が取れるまでブランチを分けて保持する。

---

### フェーズ6: 指標の追加（🟢 §4 #7 #9 #11 / 要判断 C）

**ゴール**: 床が高いネガ側だけでなく、正の指標と朝の主観が取れる。**要判断 C 次第でスコープを絞る。**

レポート §4 #9 の指摘どおり、ストレスは平均7.07・**最小値4**・sd1.33 で**床が高く天井に張り付いている**。
ネガ側だけでは回復の兆しが検出できない。

#### 🔴 Red

| # | ファイル | テスト内容 |
|---|---|---|
| T6-1 | `tests/Feature/LogValidationTest.php` | `positive_score` / `morning_capacity` が 0–10 の範囲外で 422、未送信なら 200 |
| T6-2 | 〃 | `work_location` が許可値以外で 422 |
| T6-3 | `tests/Unit/AnalyticsServiceTest.php` | `correlations()` に `positive_score` / `morning_capacity` の組が入る |
| T6-4 | 〃 | `morning_capacity → mental_capacity`（朝→夕方）の回復カーブが `autocorrelations()` に入る |
| T6-5 | `tests/Feature/AnalyticsPageTest.php` | 全件 NULL でも 200 |

#### 🟢 Green

| 対象 | 内容 |
|---|---|
| `*_add_positive_and_morning_scores_to_logs_table.php` | `positive_score` / `morning_capacity` smallint nullable ＋ CHECK `0..10`、`work_location` string(20) nullable |
| `app/Support/WorkLocations.php` | 新規。`office`（出社）/ `remote`（在宅）/ `mixed`（半々） |
| `app/Support/LogMetrics.php` | フェーズ5の Refactor で作った定義に3項目を追加 |
| `resources/views/logs/partials/form.blade.php` | すべて「くわしく」内。`work_location` は `day_type` の隣（`day_type` は細分せず**別項目**にする＝過去データの互換を保つ） |
| `app/Services/AnalyticsService.php` | `CORRELATION_PAIRS` に `positive_score × mental_capacity` / `morning_capacity × mental_capacity`、`dayTypeBreakdown()` の隣に `workLocationBreakdown()` |

> `day_type` を細分しない理由: 既存の `weekday`（7件）/ `holiday`（5件）の集計を壊さないため。
> 「平日の中の在宅/出社差」（レポート §4 #7）は独立した軸として持つほうが分析でも扱いやすい。

---

## 6. テスト計画まとめ

既存 `test.md` の方針（Unit=Service/モデル、Feature=HTTP＋認可、認可テスト最重視）を継承する。

| ファイル | 区分 | 追加/新規 |
|---|---|---|
| `tests/Feature/LogValidationTest.php` | Feature | 追加（T1-1〜4 / T3-2 / T4-1,2 / T5-1 / T6-1,2） |
| `tests/Feature/LogCrudTest.php` | Feature | 追加（T1-5,6,8 / T5-2,3） |
| `tests/Unit/LogServiceTest.php` | Unit | 追加（T3-1,3 / T4-3,4,5） |
| `tests/Unit/AnalyticsServiceTest.php` | Unit | 追加（T1-7 / T2-1〜7 / T3-4,5 / T4-6 / T5-4 / T6-3,4） |
| `tests/Feature/AnalyticsPageTest.php` | Feature | 追加（T2-8,9,10 / T5-5 / T6-5） |

### 全フェーズで守る回帰テスト

`tests/Pest.php` の `logPayload()` は **`checklist => []`** のままにする。
これにより既存の全テストが「チェックリストを選ばなければ従来どおり保存できる」（T1-5）の回帰テストになる。
**フェーズ1で `effect_score` を必須化しても既存テストが緑であること**が、
条件付き必須化が正しく実装できている証拠になる。

同様に、**フェーズ3以降の追加項目を `logPayload()` に足さない**。
「任意項目を1つも送らなくても保存できる」が自動的に固定される。

### 既知の失敗

`tests/Feature/ExampleTest.php` は `/` に 200 を期待しているが、`routes/web.php` の仕様で302を返すため
**本作業以前から失敗している**。今回も触らない。

---

## 7. 検証手順

```bash
docker compose up -d
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed --class=ChecklistCategorySeeder
docker compose exec -T app php artisan db:seed --class=ChecklistOptionSeeder
docker compose exec -T app ./vendor/bin/pest        # または make test
./bin/sql.sh -c "\d logs"                            # 追加カラムと CHECK 制約
./bin/sql.sh -c "\d log_check_item_values"
```

### 手動確認

| # | フェーズ | 画面 | 確認内容 |
|---|---|---|---|
| 1 | 1 | `/logs/create` | 回復行動を選ぶと3段階ボタンが出る。**選ばずに保存するとエラーになる** |
| 2 | 1 | 〃 | 「何もできてない」を選ぶと他の回復行動が外れ、**効いた感を要求されない** |
| 3 | 1 | 〃 | チェックリストを一切触らずに保存できる（従来と同じ手数） |
| 4 | 1 | 〃 | 「摂取したもの」に説明文が出て、回復行動の「その他」に誘導の注記がある |
| 5 | 1 | `/analytics` | 回復行動の効果カードに**数値ではなくラベル**が出る |
| 6 | 2 | `/analytics` | 重なり数カードに **0件〜4件の5行**が出て、1件→2件で余裕が落ちているのが読める（本番28件で §2① の表と一致するはず） |
| 7 | 2 | 〃 | 相関表の1行目が **n=28 のストレス×余裕**になっている（現状は n=8 の参考値がトップ） |
| 8 | 2 | 〃 | 記録率カードに欠測バイアスと入力ラグが出る |
| 9 | 2 | `/dashboard` | 未記録が3日以上続いたときだけバナーが出る |
| 10 | 3–6 | `/logs/create` | 「くわしく」を**開かずに**保存できる（全フェーズで維持） |
| 11 | 5 | `/logs/{id}` | 過去ログ（`stamina` あり・`fatigue` なし）が壊れずに表示される |

**フェーズ2の #6・#7 は本番データでの確認が最も価値が高い。**
レポート §2 の表と画面の数字が一致すれば、集計ロジックの正しさが実データで裏付けられる。

---

## 8. リリース

既存フローどおり。

```
main で実装 → main→product の PR を作成・マージ
→ GitHub Actions「Deploy to Product (VPS)」が SSH デプロイ
→ script/deploy.sh 内で php artisan migrate --force まで自動実行
```

| 注意点 | 内容 |
|---|---|
| マイグレーション | 本番で自動実行される。**追加カラムはすべて nullable** であることをマージ前に確認 |
| フェーズ5 の NOT NULL 解除 | `logs.stamina` の制約変更は既存28件に影響しない（値が入っているため）が、**ロールバック時に NOT NULL へ戻せない**（新規ログが NULL を持つため）。down() は「NOT NULL に戻さない」実装にする |
| seeder | 自動実行されない。**フェーズ1は seeder が本体**なので、デプロイ後の手動実行を忘れないこと（§5 フェーズ1末尾のコマンド） |
| 個人データ | 本計画で個人データを扱う画面・出力は増えない。`docs/analysis/` 配下のレポートはローカル閲覧前提であり、**コミットするかは別途判断**（現状 untracked） |

---

## 9. 着手順とスコープの絞り方

### 推奨: **フェーズ1 → フェーズ2 で一度止める**

理由:

1. **フェーズ1・2はスキーマ変更がほぼ無く、入力負担も増えない**（フェーズ1は「選んだときだけ +1タップ」）。
   にもかかわらず、死んでいた `recoveryEffect()` が動き出し、レポートで最も効いた切り口（重なり数・自己相関）が
   **過去28件に遡ってアプリ上で見えるようになる**。
2. フェーズ3以降は**すべて入力項目の追加**であり、記録率44%の現状では
   「項目を増やした結果、記録が止まって n が増えない」という最悪の結果になり得る。
3. レポート §6 の結論そのもの — **「分析の精度を上げる一番の投資は、新項目の追加より記録の継続」**。

### 判断ポイント（フェーズ1・2 リリース後 2〜4週間）

| 観測する数字 | 見方 |
|---|---|
| `effect_score` の入力率 | 回復行動を選んだ日のうち何%で入ったか。**低ければ3段階でもまだ重い**＝フェーズ3の強度3ボタンも同じ運命になるので、設計を見直す |
| `intake` の選択率 | 説明文だけで導線が直るか。0件のままなら導線ではなく**カテゴリの並び順や配置**の問題 |
| 記録率 | 44% から下がっていないか。下がっていればフェーズ3以降は着手しない |
| 重なり数カードの n | 本番で 0件〜4件の各グループがどれだけ埋まったか |

### その後の優先順位

| 順 | フェーズ | 前提 |
|---|---|---|
| 3 | フェーズ3（強度） | `effect_score` の入力率が十分＝3ボタン方式が機能していること |
| 4 | フェーズ4（時刻） | 同上。`sleep_hours` の自動計算があるので**むしろ入力が楽になる**可能性がある |
| 5 | フェーズ5（体力の再定義） | **要判断 B** の決定。時系列の分断を許容するか |
| 6 | フェーズ6（指標追加） | **要判断 C**。フェーズ3・4 の入力率を見てからスコープを決める |
| — | 要判断 A（欠測理由） | 前回の決定を覆すかのご判断待ち。覆さない場合はフェーズ2の `coverage()` 拡張で打ち止め |

---

## 10. 実装記録（フェーズ1・2）

実装日: 2026-09-09 / ブランチ: `feat/analysis-followup`
テスト: **271 passed**（フェーズ1・2で +64。既知の `ExampleTest` 1失敗は初期構築時から）

### 10.1 計画からの変更点

| # | 計画 | 実装 | 理由 |
|---|---|---|---|
| 1 | 「その他」の補足プレースホルダを個別に変更 | **カテゴリ説明文に集約**（`checklist_categories.description`） | 選択肢ごとのプレースホルダ列を足すと、この1文のためだけにスキーマが増える。説明文はカテゴリ単位で足りるうえ、`code === 'thought_habit'` 直書きの解消と同じ仕組みで済む |
| 2 | 「効いた感」を必須化 | **`is_none = false` の選択肢に限って必須化** | 「何もできてない」（12/28日 = 43%）に効果を聞くのは不整合。`is_none` にすることで排他ロジックにも乗る（計画どおり）が、必須化の除外条件としても使った |
| 3 | `recoveryEffect()` はそのまま流用 | **`is_none` を集計対象から除外**（1行追加） | 上記のとおり効果を聞かない選択肢が回復効果ランキングに `効果 —` で並ぶのを防ぐ |
| 4 | `coverage()` に欠測バイアスを追加 | 併せて **`current_gap`（期間末までの連続未記録日数）も追加** | ダッシュボードのバナーに必要なのは「最長」ではなく「今どれだけ途切れているか」。`longest_gap` では過去の空白で毎回警告が出続ける |
| 5 | 自己相関は 4組 | **5組**（体力の前日→翌日を追加） | 体力は §3 のとおり分析に使えないが、**自己相関 r=+0.38 は「体力の解釈が入れ替わった」仮説の検証材料**になる（解釈が揺れていれば持ち越しも崩れる）。フェーズ5の判断根拠に使える |

### 10.2 追加・変更したファイル

**新規**

| ファイル | 内容 |
|---|---|
| `app/Support/EffectLevels.php` | 効いた感の3段階（2/5/8）。`describe()` は3段階外の旧値を「7/10」にフォールバック、`nearestLabel()` は平均値を最も近い段階のラベルに |
| `app/Support/DurationBuckets.php` | かけた時間の4択（15/30/60/120分）。`describe()` は選択肢外の旧値を「90分」にフォールバック |
| `database/migrations/2026_09_09_100001_add_description_to_checklist_categories_table.php` | `description` text nullable。**フェーズ1・2で唯一のスキーマ変更** |

**変更**

| ファイル | 内容 |
|---|---|
| `app/Services/AnalyticsService.php` | `stressSourceOverlap()` / `thoughtHabitCount()` / `autocorrelations()` / `inputLag()` を追加。`coverage()` に `bias` と `current_gap`、`correlations()` に `reliable` と並べ替え、`recoveryEffect()` に `is_none` 除外。private に `pearson()` / `dailyCountQuery()` / `groupByDailyCount()` / `missingBias()` |
| `app/Http/Requests/StoreLogRequest.php` | `effect_score` / `duration_min` を `Rule::in` に。`withValidator` に効いた感の条件付き必須 |
| `app/Http/Controllers/AnalyticsController.php` | 新4メソッドを view へ |
| `app/Http/Controllers/DashboardController.php` | `coverage()` を view へ（バナー用） |
| `app/Models/ChecklistCategory.php` | `description` を `#[Fillable]` に |
| `database/seeders/ChecklistCategorySeeder.php` | 4カテゴリに説明文 |
| `database/seeders/ChecklistOptionSeeder.php` | 「何もできてない」を `is_none => true` に |
| `resources/views/logs/partials/form.blade.php` | 効果測定を3段階ラジオ＋選択式の時間に。`! $option->is_none` 条件。カテゴリ説明文の表示と `code` 直書きの解消 |
| `resources/views/logs/show.blade.php` | 計測値をラベル表示に |
| `resources/views/analytics/index.blade.php` | 重なり／クセ個数／前日からの持ち越しの3カード、記録率カードに欠測バイアスと入力タイミング、相関表に参考値バッジ、回復効果にラベル併記 |
| `resources/views/dashboard.blade.php` | 未記録連続バナー（ログ0件のときは出さない） |

`app/Services/LogService.php` は**変更なし**。`selection_meta` の受け渡しは既存実装のままで足りた。

### 10.3 実装中に気づいた問題（レポート側の読み違い）

🔴 **レポート §3 の「入力時刻が平均16時」は UTC 基準の値**。

`config/app.php` の `timezone` は `UTC` 固定で、`logs.created_at` も UTC で保存されている。
レポートはこの生値をそのまま「時」として読んでいるため、JST では **平均 16.1時 ＝ 翌日 01:06 頃**になる。
「最早 1.1時」も JST では 10:06 で、レポートの「就業時間内に記録している」という説明とは逆の姿になる。

- **`inputLag()` の当日/翌日/それ以降の分類は影響を受けない**（UTC 日付は JST 日付より前にしかずれないため、
  「UTC で翌日」なら JST でも確実に翌日以降）。件数は下限として正しい。
- **平均入力時刻だけは基準が曖昧**なので、画面には `（UTC 基準）` と明記して出している。

§4 #3（イベント発生時刻帯）の必要性そのものは変わらない —— むしろ「いつ書いたか」から
「いつ起きたか」を推定するのは無理だと分かったので、**時刻帯を項目として持つ根拠は強まった**。
ただし「夕方に記録している」という前提で立てた仮説は、実際の入力時刻を JST で読み直してから判断すべき。

→ アプリ側の対処として `config('app.timezone')` を JST にするかは**別途の判断事項**（既存28件の
`created_at` の読み方が変わるため、フェーズ4の時刻項目とまとめて決めるのが安全）。

### 10.4 本番データでの検証結果（完了）

計画 §7 の手動確認のうちローカルで埋められなかったものを、本番で実施した（読み取りのみ）。
全結果は `docs/deploylogs/deploy-7-20260909-0223.md`。

| # | 確認内容 | 結果 |
|---|---|---|
| 6 | 重なり数カードが §2① の表と一致するか | ✅ **完全一致**（0件:8.00 / 1件:6.40 / 2件:4.47 / 3件:4.40 / 4件:4.00）。1件→2件の −1.93 の崖を画面で再現 |
| 7 | 相関表の1行目が n=28 のストレス×余裕になっているか | ✅ `-0.706 / n=28` が1行目。従来トップの n=8「持ち越し感×コントロール可能度 -0.83」は下位へ |
| — | 自己相関がレポート §2④ と一致するか | ✅ 余裕→余裕 +0.465 / ストレス→ストレス +0.079 / 体力→体力 +0.384 |
| — | 欠測バイアスがレポート §2⑨ と一致するか | ✅ 翌日記録 n=18（6.83 / 5.22）vs 翌日欠測 n=9（**7.56 / 4.78**） |
| — | seeder の実行 | ✅ 実行済み。**PR #5 分も同時に解消**（§10.5） |
| — | 画面の実描画 | ✅ `/analytics` `/dashboard` `/logs/create` すべて HTTP 200 |

**クセの個数カードだけレポートと1日ぶんズレた（0個が 6日 → 7日）。これは実装が正しい。**
レポートは「特になし」を明示選択した日のみを0個としていたが、本実装は `logs` 起点で数えるため
**そのカテゴリで何も選ばなかった1日も0個に含める**（合計が28日で揃う）。
「○が0件の日を落とさない」設計（テスト T2-2）が意図どおり効いている。

### 10.5 A・B の真因（本番反映時に判明）

本番の seeder 実行前の状態:

| | 実行前 | 実行後 |
|---|---|---|
| `checklist_categories` | **3件**（`intake` なし） | 4件 |
| `recovery_action.tracks_effect` | **false** | true |
| `checklist_options` | 21件 | 26件 |
| 「何もできてない」の `is_none` | false | true |

`deploy-5-20260823-0031.md` に記録されていた「seeder 未実施」がそのまま残っていた。

| §1 の記述 | 実際 |
|---|---|
| A: 「フォームもバリデーションも実装済みだが一度も入力されていない」 | `tracks_effect` = false のため**計測欄が描画されていなかった**。コードは正しく、マスタが未反映だっただけ |
| B: 「マスタはあるのに使われず」 | **`intake` カテゴリが本番に存在しなかった**。フォームに出ようがなかった |

**この計画の §1 A・B は「導線の失敗」と診断していたが、実際は「デプロイ手順の欠落」だった。**

#### フェーズ3以降への影響

| 論点 | 影響 |
|---|---|
| フェーズ1の3段階ラジオ化 | **有効だが、根拠が弱くなった。** 自由入力より確実に良いのは変わらないが、「0/28 だったから自由入力はダメ」という証拠は無効。実際の入力率は今回はじめて観測できる |
| 計画 §9 の判断ポイント | **`effect_score` の入力率が唯一の判断材料として重要度が上がった。** これが埋まらなければフェーズ3の強度3ボタンも同じ運命になる |
| `intake` の選択率 | 同様に今回が初計測。カテゴリが表示されて初めて「使われるか」が分かる |
| **デプロイ手順そのもの** | 🔴 **seeder が2回連続で漏れた。** `script/deploy.sh` に seeder を含めるか、PR テンプレート／デプロイログのチェック項目にするかを検討すべき（本計画のスコープ外だが、次に seeder を伴う変更を出す前に決めておく必要がある） |

