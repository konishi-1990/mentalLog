<?php

namespace App\Http\Requests;

use App\Models\ChecklistOption;
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
            'hardest_text' => ['nullable', 'string', 'max:2000'],
            'summary_text' => ['nullable', 'string', 'max:500'],

            'check_items' => ['array'],
            'check_items.*.is_on' => ['nullable', 'boolean'],
            'check_items.*.detail_text' => ['nullable', 'string', 'max:1000'],

            'checklist' => ['array'],
            'checklist.*' => ['integer', Rule::exists('checklist_options', 'id')],
            'checklist_details' => ['array'],
            'checklist_details.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * カテゴリ内「特になし」排他 と requires_text 必須の追加検証。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('checklist', []);
            if (empty($ids)) {
                return;
            }

            $options = ChecklistOption::whereIn('id', $ids)->get();

            // 「特になし」は同一カテゴリ内で単独選択のみ
            foreach ($options->groupBy('category_id') as $categoryOptions) {
                $hasNone = $categoryOptions->firstWhere('is_none', true);
                if ($hasNone && $categoryOptions->count() > 1) {
                    $validator->errors()->add('checklist', '「特になし」は他の項目と同時に選択できません。');
                }
            }

            // requires_text の選択肢は補足テキスト必須
            foreach ($options as $option) {
                if ($option->requires_text && blank($this->input("checklist_details.{$option->id}"))) {
                    $validator->errors()->add("checklist_details.{$option->id}", '補足の入力が必要です。');
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
        ];
    }
}
