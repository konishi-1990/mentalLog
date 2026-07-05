<?php

namespace App\Services;

use App\Models\Log;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LogService
{
    /**
     * 日次ログを作成または更新する（1ユーザ1日1件）。
     * 子（○×回答・チェック選択）はトランザクション内で置き換える。
     *
     * @param  array<string, mixed>  $data
     */
    public function upsertDailyLog(User $user, array $data): Log
    {
        return DB::transaction(function () use ($user, $data) {
            $log = Log::updateOrCreate(
                ['user_id' => $user->id, 'logged_on' => $data['logged_on']],
                [
                    'stress' => $data['stress'],
                    'stamina' => $data['stamina'],
                    'mental_capacity' => $data['mental_capacity'],
                    'hardest_text' => $data['hardest_text'] ?? null,
                    'summary_text' => $data['summary_text'] ?? null,
                ],
            );

            $this->syncCheckItemValues($user, $log, $data['check_items'] ?? []);
            $this->syncChecklistSelections($log, $data['checklist'] ?? [], $data['checklist_details'] ?? []);

            return $log;
        });
    }

    /**
     * ○×回答を置き換える。ユーザ自身の項目のみ受け付ける。
     *
     * @param  array<int, array{is_on?: mixed, detail_text?: ?string}>  $checkItems
     */
    private function syncCheckItemValues(User $user, Log $log, array $checkItems): void
    {
        $log->checkItemValues()->delete();

        $ownedIds = $user->checkItems()->pluck('id')->all();

        foreach ($checkItems as $checkItemId => $val) {
            if (! in_array((int) $checkItemId, $ownedIds, true)) {
                continue; // 他人の項目は無視（防御的）
            }

            $isOn = filter_var($val['is_on'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $log->checkItemValues()->create([
                'check_item_id' => $checkItemId,
                'is_on' => $isOn,
                'detail_text' => $isOn ? ($val['detail_text'] ?? null) : null,
            ]);
        }
    }

    /**
     * チェック選択を置き換える。
     *
     * @param  array<int, int|string>  $optionIds
     * @param  array<int|string, ?string>  $details
     */
    private function syncChecklistSelections(Log $log, array $optionIds, array $details): void
    {
        $log->checklistSelections()->delete();

        foreach ($optionIds as $optionId) {
            $log->checklistSelections()->create([
                'checklist_option_id' => $optionId,
                'detail_text' => $details[$optionId] ?? null,
            ]);
        }
    }
}
