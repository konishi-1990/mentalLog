<?php

namespace App\Console\Commands;

use App\Models\ChecklistOption;
use App\Models\LogChecklistSelection;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 「その他」＋補足テキストで記録されていた選択を、後から追加した選択肢へ付け替える。
 *
 * 付け替えないと新しい選択肢は追加日以降しか集計されず、過去分の効いた感と比べられない。
 * 書き換えるのは checklist_option_id だけで、補足・効いた感・時間は残す。
 * 一致させる文字列は私的な内容になり得るため、リポジトリには書かず引数で渡す。
 */
class ReclassifySelections extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:reclassify-selections
                            {--from= : 付け替え元の選択肢 id（例: 回復行動の「その他」）}
                            {--to= : 付け替え先の選択肢 id（同じカテゴリであること）}
                            {--match= : 補足テキストの部分一致（大文字小文字を区別しない）}
                            {--user= : 対象ユーザのメールアドレス（省略時は全ユーザ）}
                            {--dry-run : 対象を表示するだけで書き換えない}';

    /**
     * @var string
     */
    protected $description = '補足テキストに一致する選択を別の選択肢へ付け替える';

    public function handle(): int
    {
        $from = ChecklistOption::find($this->option('from'));
        $to = ChecklistOption::find($this->option('to'));
        $match = (string) $this->option('match');

        if (! $from || ! $to) {
            $this->error('--from / --to に存在する選択肢 id を指定してください。');

            return self::FAILURE;
        }
        if ($from->category_id !== $to->category_id) {
            $this->error('付け替え元と付け替え先は同じカテゴリの選択肢にしてください。');

            return self::FAILURE;
        }
        if ($match === '') {
            // 空だと補足のある行がすべて対象になる
            $this->error('--match を指定してください。');

            return self::FAILURE;
        }

        $user = null;
        if ($email = $this->option('user')) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error("ユーザが見つかりません: {$email}");

                return self::FAILURE;
            }
        }

        $targets = LogChecklistSelection::query()
            ->with('log:id,logged_on')
            ->where('checklist_option_id', $from->id)
            ->where('detail_text', 'ilike', '%'.addcslashes($match, '%_\\').'%')
            ->when($user, fn ($q) => $q->whereRelation('log', 'user_id', $user->id))
            ->orderBy('id')
            ->get();

        // 同じログに付け替え先が既にあると unique(log_id, checklist_option_id) に当たる
        $existing = LogChecklistSelection::where('checklist_option_id', $to->id)
            ->whereIn('log_id', $targets->pluck('log_id'))
            ->pluck('log_id')
            ->all();
        [$skipped, $moving] = $targets->partition(fn ($s) => in_array($s->log_id, $existing, true));

        $dryRun = (bool) $this->option('dry-run');
        $this->info(sprintf(
            '「%s」→「%s」: 対象 %d 件 / スキップ %d 件%s',
            $from->label, $to->label, $moving->count(), $skipped->count(), $dryRun ? '（dry-run：書き換えません）' : '',
        ));

        $this->table(
            ['selection_id', '日付', '補足', '効いた感', '時間', '処理'],
            $targets->map(fn ($s) => [
                $s->id,
                $s->log->logged_on->format('Y-m-d'),
                $s->detail_text,
                $s->effect_score ?? '—',
                $s->duration_min ?? '—',
                $skipped->contains($s) ? 'スキップ（付け替え先が既にある）' : '付け替え',
            ])->all(),
        );

        if ($dryRun || $moving->isEmpty()) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($moving, $to) {
            LogChecklistSelection::whereIn('id', $moving->pluck('id'))
                ->update(['checklist_option_id' => $to->id]);
        });

        $this->info("{$moving->count()} 件を付け替えました。");

        return self::SUCCESS;
    }
}
