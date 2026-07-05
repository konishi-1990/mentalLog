<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:checklist_categories,id'],
            'label' => ['required', 'string', 'max:150'],
            'requires_text' => ['nullable', 'boolean'],
            'is_none' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['label' => 'ラベル', 'category_id' => 'カテゴリ'];
    }
}
