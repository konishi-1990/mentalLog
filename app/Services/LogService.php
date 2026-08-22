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
                    'sleep_hours' => $data['sleep_hours'] ?? null,
                    'sleep_quality' => $data['sleep_quality'] ?? null,
                    'carryover' => $data['carryover'] ?? null,
                    'controllability' => $data['controllability'] ?? null,
                    'day_type' => $data['day_type'] ?? null,
                    'hardest_text' => $data['hardest_text'] ?? null,
                    'summary_text' => $data['summary_text'] ?? null,
                ],
            );

            $this->syncCheckItemValues($user, $log, $data['check_items'] ?? []);
            $this->syncChecklistSelections(
                $log,
                $data['checklist'] ?? [],
                $data['checklist_details'] ?? [],
                $data['selection_meta'] ?? [],
            );
            $this->syncPeople($user, $log, $data['people'] ?? [], $data['people_details'] ?? []);

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
     * $meta（所要時間・効いた感）は選択された選択肢の分だけ反映する。
     *
     * @param  array<int, int|string>  $optionIds
     * @param  array<int|string, ?string>  $details
     * @param  array<int|string, array{duration_min?: mixed, effect_score?: mixed}>  $meta
     */
    private function syncChecklistSelections(Log $log, array $optionIds, array $details, array $meta = []): void
    {
        $log->checklistSelections()->delete();

        foreach ($optionIds as $optionId) {
            $optionMeta = $meta[$optionId] ?? [];

            $log->checklistSelections()->create([
                'checklist_option_id' => $optionId,
                'detail_text' => $details[$optionId] ?? null,
                'duration_min' => $this->nullableInt($optionMeta['duration_min'] ?? null),
                'effect_score' => $this->nullableInt($optionMeta['effect_score'] ?? null),
            ]);
        }
    }

    /**
     * 相手タグの紐づけを置き換える。ユーザ自身の相手タグのみ受け付ける。
     *
     * @param  array<int, int|string>  $personIds
     * @param  array<int|string, ?string>  $details
     */
    private function syncPeople(User $user, Log $log, array $personIds, array $details): void
    {
        $ownedIds = $user->people()->pluck('id')->all();

        $attach = [];
        foreach ($personIds as $personId) {
            if (! in_array((int) $personId, $ownedIds, true)) {
                continue; // 他人の相手タグは無視（防御的）
            }
            $attach[(int) $personId] = ['detail_text' => $details[$personId] ?? null];
        }

        $log->people()->sync($attach);
    }

    /**
     * 未入力（null・空文字）は NULL、それ以外は int にする。
     */
    private function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
