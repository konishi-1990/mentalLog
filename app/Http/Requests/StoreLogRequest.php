<?php

namespace App\Http\Requests;

use App\Models\ChecklistOption;
use App\Support\DayTypes;
use App\Support\DurationBuckets;
use App\Support\EffectLevels;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 認可は auth ミドルウェア／Policy で担保
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logged_on' => ['required', 'date'],
            'stress' => ['required', 'integer', 'between:0,10'],
            'stamina' => ['required', 'integer', 'between:0,10'],
            'mental_capacity' => ['required', 'integer', 'between:0,10'],

            // フェーズ1で追加した項目。既存ログが未入力のため、いずれも任意。
            'sleep_hours' => ['nullable', 'numeric', 'between:0,24'],
            'sleep_quality' => ['nullable', 'integer', 'between:0,10'],
            'carryover' => ['nullable', 'integer', 'between:0,10'],
            'controllability' => ['nullable', 'integer', 'between:0,10'],
            'day_type' => ['nullable', Rule::in(DayTypes::codes())],

            'hardest_text' => ['nullable', 'string', 'max:2000'],
            'summary_text' => ['nullable', 'string', 'max:500'],

            'check_items' => ['array'],
            'check_items.*.is_on' => ['nullable', 'boolean'],
            'check_items.*.detail_text' => ['nullable', 'string', 'max:1000'],

            'checklist' => ['array'],
            'checklist.*' => ['integer', Rule::exists('checklist_options', 'id')],
            'checklist_details' => ['array'],
            'checklist_details.*' => ['nullable', 'string', 'max:1000'],

            // 回復行動の計測。
            // 「効いた感」は回復行動を選んだときだけ必須（withValidator で判定）。
            // 選ばなければ従来と同じ手数で保存できる。所要時間は任意のまま。
            'selection_meta' => ['array'],
            'selection_meta.*.duration_min' => ['nullable', 'integer', Rule::in(DurationBuckets::minutes())],
            'selection_meta.*.effect_score' => ['nullable', 'integer', Rule::in(EffectLevels::scores())],

            // 相手タグ。所有チェックは LogService 側で行う（他人の ID は無視）。
            'people' => ['array'],
            'people.*' => ['integer'],
            'people_details' => ['array'],
            'people_details.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * カテゴリ内「特になし」排他 / requires_text 必須 / 効いた感の条件付き必須。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('checklist', []);
            if (empty($ids)) {
                return;
            }

            $options = ChecklistOption::with('category')->whereIn('id', $ids)->get();

            // 「特になし」は同一カテゴリ内で単独選択のみ
            foreach ($options->groupBy('category_id') as $categoryOptions) {
                $hasNone = $categoryOptions->firstWhere('is_none', true);
                if ($hasNone && $categoryOptions->count() > 1) {
                    $validator->errors()->add('checklist', '「特になし」は他の項目と同時に選択できません。');
                }
            }

            foreach ($options as $option) {
                // requires_text の選択肢は補足テキスト必須
                if ($option->requires_text && blank($this->input("checklist_details.{$option->id}"))) {
                    $validator->errors()->add("checklist_details.{$option->id}", '補足の入力が必要です。');
                }

                // 効果測定カテゴリの選択肢は「効いた感」必須。
                // これを任意にしていた結果、本番28日分すべて未入力で recoveryEffect() が空を返していた。
                // 「何もできてない」（is_none）には効果を聞かない。
                if ($option->category?->tracks_effect && ! $option->is_none
                    && blank($this->input("selection_meta.{$option->id}.effect_score"))) {
                    $validator->errors()->add(
                        "selection_meta.{$option->id}.effect_score",
                        "「{$option->label}」の効いた感を選んでください。",
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logged_on' => '対象日',
            'stress' => 'ストレス',
            'stamina' => '体力',
            'mental_capacity' => 'メンタル余裕',
            'sleep_hours' => '睡眠時間',
            'sleep_quality' => '睡眠の質',
            'carryover' => '前日からの持ち越し感',
            'controllability' => 'コントロール可能度',
            'day_type' => '勤務形態',
            'selection_meta.*.duration_min' => 'かけた時間',
            'selection_meta.*.effect_score' => '効いた感',
        ];
    }
}
