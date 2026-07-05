<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogFilterRequest extends FormRequest
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
        $score = ['nullable', 'integer', 'between:0,10'];

        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'stress_min' => $score,
            'stress_max' => $score,
            'stamina_min' => $score,
            'stamina_max' => $score,
            'mental_min' => $score,
            'mental_max' => $score,
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
