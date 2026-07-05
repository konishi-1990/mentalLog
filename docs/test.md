# MentalLog テスト仕様書（実施記録）

本書は TDD で実装した MentalLog の自動テスト一覧と結果をまとめたもの。

## 1. サマリ

| 指標 | 値 |
|---|---|
| テストフレームワーク | Pest（PHPUnit ベース） |
| テストDB | 専用 PostgreSQL（`mentallog_test`、本番同一エンジン） |
| DBリセット | `RefreshDatabase`（全テストでマイグレーション適用） |
| **合計テスト数** | **106 passed** |
| **アサーション数** | **216** |
| **コードカバレッジ** | **92.9%**（計測: pcov） |
| 実行時間 | 約 2〜3 秒 |

### 実行コマンド
```bash
make test                       # 全テスト
make coverage                   # カバレッジ計測
make test-filter F=LogService   # 絞り込み実行
```

### カバレッジ（コア）
| 対象 | 被覆率 |
|---|---|
| `Services/LogService` | 96.9% |
| `Services/AnalyticsService` | 100% |
| `Policies/LogPolicy` | 100% |
| `Policies/CheckItemPolicy` | 100% |
| `Http/Middleware/EnsureUserIsAdmin` | 100% |
| `Http/Requests/*`（各FormRequest） | 100% |
| `Observers/UserObserver` | 100% |
| 全体 | 92.9% |

> 未被覆の多くは Breeze 認証スキャフォールドと一部モデルのリレーションメソッド（低リスク）。

---

## 2. テスト方針

- **Unit**：Service・モデルのロジック（純粋な計算・状態）。
- **Feature**：HTTPエンドポイント＋認可（実リクエスト）。
- **重点**：機微情報のため「他人のデータが見えない/操作できない（403）」を必ず検証。集計は固定データ→期待値で数値保証。

---

## 3. テスト一覧（自作分）

### 3.1 Unit — モデル `tests/Unit/Models/UserTest.php`（5）
| # | テスト内容 |
|---|---|
| 1 | admin ロールのユーザは `isAdmin()` が true |
| 2 | 一般ユーザは `isAdmin()` が false |
| 3 | user は role に属する（belongsTo） |
| 4 | user は logs を持つ（hasMany） |
| 5 | user は checkItems を持つ（hasMany） |

### 3.2 Unit — `tests/Unit/LogServiceTest.php`（4）
| # | テスト内容 |
|---|---|
| 1 | 新規ログを数値・テキストとともに作成する |
| 2 | 同一日の再登録は更新（1件のまま・値が上書き） |
| 3 | ○の○×項目は detail_text を保存し、×は null にする |
| 4 | 再登録で子（○×・チェック）が置き換えられる |

### 3.3 Unit — `tests/Unit/AnalyticsServiceTest.php`（4）
| # | テスト内容 |
|---|---|
| 1 | 時系列：期間内の日次3数値を日付順で返す |
| 2 | ○×頻度：○になった回数を項目ごとに集計する |
| 3 | チェック頻度：選択された回数を選択肢ごとに集計する |
| 4 | 回復パターン：回復行動を取った翌日のメンタル余裕平均を算出する |

### 3.4 Feature — スモーク `tests/Feature/SmokeTest.php`（2）
| # | テスト内容 |
|---|---|
| 1 | 未ログインではダッシュボードからログインへリダイレクトされる |
| 2 | ログイン済みユーザはダッシュボードを表示できる |

### 3.5 Feature — DB制約 `tests/Feature/Database/ConstraintsTest.php`（4）
| # | テスト内容 |
|---|---|
| 1 | stress が範囲外(11)だと保存できない（CHECK制約） |
| 2 | stamina が範囲外(-1)だと保存できない（CHECK制約） |
| 3 | 同一ユーザ・同一日のログは重複作成できない（複合ユニーク） |
| 4 | 異なるユーザなら同一日でも作成できる |

### 3.6 Feature — シーダ `tests/Feature/Database/SeederTest.php`（5）
| # | テスト内容 |
|---|---|
| 1 | ロールが admin / user の2件投入される |
| 2 | チェックリストカテゴリが3件（クセ/体の反応/回復行動） |
| 3 | 回復行動の「その他」は requires_text=true |
| 4 | 「特になし」は is_none=true で2カテゴリに存在 |
| 5 | 各カテゴリに選択肢が投入される（6/6/7） |

### 3.7 Feature — 既定○×複製 `tests/Feature/DefaultCheckItemsTest.php`（3）
| # | テスト内容 |
|---|---|
| 1 | ユーザ作成時に既定の○×項目が複製される（5件） |
| 2 | ○×項目はユーザごとに独立して複製される |
| 3 | 複製された○×項目は既定で有効・並び順が付与される |

### 3.8 Feature — ログService経由CRUD `tests/Feature/LogCrudTest.php`（8）
| # | テスト内容 |
|---|---|
| 1 | 未ログインではログ作成画面にアクセスできない |
| 2 | ログイン済みユーザはログ作成画面を表示できる |
| 3 | ログを保存できる（数値・テキスト） |
| 4 | ○の○×項目の内容が保存される |
| 5 | チェックリストの選択が保存される |
| 6 | ログを更新できる |
| 7 | ログを削除できる |
| 8 | ログ詳細を表示できる |

### 3.9 Feature — ログ認可 `tests/Feature/LogAuthorizationTest.php`（5）
| # | テスト内容 |
|---|---|
| 1 | 他人のログ詳細は閲覧できない（403） |
| 2 | 他人のログ編集画面は開けない（403） |
| 3 | 他人のログは更新できない（403） |
| 4 | 他人のログは削除できない（403） |
| 5 | 管理者は他人のログを閲覧できる |

### 3.10 Feature — ログ入力バリデーション `tests/Feature/LogValidationTest.php`（6）
| # | テスト内容 |
|---|---|
| 1 | stress が範囲外(11)だと422 |
| 2 | 数値が未入力だとエラー |
| 3 | logged_on が未入力だとエラー |
| 4 | 同一カテゴリで「特になし」と他項目の同時選択はエラー |
| 5 | requires_text の「その他」を選んで補足が空だとエラー |
| 6 | 「特になし」を単独で選ぶのは有効 |

### 3.11 Feature — ログ絞り込み `tests/Feature/LogFilterTest.php`（7）
| # | テスト内容 |
|---|---|
| 1 | 日付 from/to で絞り込める |
| 2 | ストレスの min/max で絞り込める（境界値を含む） |
| 3 | 体力・メンタル余裕の min/max で絞り込める |
| 4 | 一般ユーザは自分のログしか見えない |
| 5 | 管理者は全ユーザのログを見られる |
| 6 | 管理者はユーザ指定で絞り込める |
| 7 | 範囲外の絞り込み値はバリデーションエラー |

### 3.12 Feature — ○×項目マスタ `tests/Feature/CheckItemTest.php`（12）
| # | テスト内容 |
|---|---|
| 1 | 設定画面を表示できる |
| 2 | ○×項目を追加できる |
| 3 | 追加した項目は末尾の並び順になる |
| 4 | 項目名を変更できる |
| 5 | 項目を無効化できる |
| 6 | 無効化しても過去ログの回答は保持される |
| 7 | 項目名は必須 |
| 8 | 他人の項目は更新できない（403） |
| 9 | 項目を削除（無効化）できる |
| 10 | 他人の項目は削除できない（403） |
| 11 | 並び替えできる |
| 12 | 他人の項目を含む並び替えは無視される |

### 3.13 Feature — 分析画面 `tests/Feature/AnalyticsPageTest.php`（3）
| # | テスト内容 |
|---|---|
| 1 | 未ログインでは分析画面にアクセスできない |
| 2 | ログイン済みユーザは分析画面を表示できる |
| 3 | ダッシュボードを表示できる |

### 3.14 Feature — 管理者/ユーザ管理 `tests/Feature/Admin/UserManagementTest.php`（7）
| # | テスト内容 |
|---|---|
| 1 | 一般ユーザは管理ユーザ画面にアクセスできない（403） |
| 2 | 管理者はユーザ管理画面を表示できる |
| 3 | 管理者はユーザを作成できる |
| 4 | 管理者はユーザのロールを変更できる |
| 5 | 管理者はユーザを無効化できる |
| 6 | メールアドレスは重複できない |
| 7 | 管理者はユーザを削除できる |

### 3.15 Feature — 管理者/チェックリスト `tests/Feature/Admin/ChecklistMasterTest.php`（6）
| # | テスト内容 |
|---|---|
| 1 | 一般ユーザはチェックリスト管理にアクセスできない（403） |
| 2 | 管理者はチェックリスト管理画面を表示できる |
| 3 | 管理者は選択肢を追加できる |
| 4 | 管理者は選択肢を更新できる（requires_text等） |
| 5 | 管理者は選択肢を無効化できる |
| 6 | ラベルは必須 |

---

## 4. Breeze 由来テスト（認証スキャフォールド）

導入時に付属し、そのまま緑を維持しているテスト。

| ファイル | 件数 | 概要 |
|---|---|---|
| `Auth/AuthenticationTest` | 4 | ログイン画面表示・認証成功/失敗・ログアウト |
| `Auth/RegistrationTest` | 2 | 登録画面・新規登録 |
| `Auth/PasswordResetTest` | 4 | パスワードリセットのリンク/画面/実行 |
| `Auth/PasswordConfirmationTest` | 3 | パスワード確認 |
| `Auth/PasswordUpdateTest` | 2 | パスワード更新 |
| `Auth/EmailVerificationTest` | 3 | メール検証 |
| `ProfileTest` | 5 | プロフィール表示/更新/削除 |
| `ExampleTest`（Unit/Feature） | 2 | 雛形 |

---

## 5. フェーズ別テスト対応（TDD）

| フェーズ | 主なテスト | 件数 |
|---|---|---|
| 0 基盤＋テスト土台 | Smoke | 2 |
| 1 DB・モデル・認可 | UserTest / Constraints / Seeder / DefaultCheckItems | 17 |
| 2 ログCRUD | LogService / LogCrud / LogAuthorization / LogValidation | 23 |
| 3 一覧・絞り込み | LogFilter | 7 |
| 4 ○×項目マスタ | CheckItem | 12 |
| 5 見える化・分析 | AnalyticsService / AnalyticsPage | 7 |
| 6 管理者機能 | Admin/UserManagement / Admin/ChecklistMaster | 13 |
| 7 仕上げ | （既存の拡充・エラー画面） | — |
| Breeze | Auth / Profile / Example | 25 |
| **合計** | | **106** |

---

## 6. 実行結果ログ（最終）

```
Tests:    106 passed (216 assertions)
Duration: 約2〜3s
Coverage: 92.9%
```
