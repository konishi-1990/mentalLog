<?php

namespace Database\Seeders;

use App\Models\ChecklistCategory;
use Illuminate\Database\Seeder;

class ChecklistCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'thought_habit',
                'name' => '頭の中のクセ',
                // 選んだ個数がメンタル余裕とほぼ連動する（report-202609.md §2 ②）
                'description' => '超重要：当てはまった個数がメンタル余裕とほぼ連動します。',
                'sort_order' => 1,
            ],
            [
                'code' => 'body_reaction',
                'name' => '体の反応',
                'description' => '体に出たサインを選んでください。',
                'sort_order' => 2,
            ],
            [
                'code' => 'recovery_action',
                'name' => '回復行動',
                // 飲食物が「その他」に紛れていたため、ここで摂取物へ誘導する（§4 #6）
                'description' => 'やれたことだけ選んでください。選ぶと「効いた感」を聞きます。'
                    .'飲食物（コーヒー・お酒・薬）は下の「摂取したもの」へ。',
                'sort_order' => 3,
                'tracks_effect' => true,
            ],
            [
                'code' => 'intake',
                'name' => '摂取したもの',
                'description' => 'コーヒー・お酒・薬はこちら。回復行動ではなくこちらに入れてください。'
                    .'睡眠の質との交絡因子になるため、分けて記録します。',
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $category) {
            ChecklistCategory::updateOrCreate(['code' => $category['code']], $category);
        }
    }
}
