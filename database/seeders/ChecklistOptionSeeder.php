<?php

namespace Database\Seeders;

use App\Models\ChecklistCategory;
use App\Models\ChecklistOption;
use Illuminate\Database\Seeder;

class ChecklistOptionSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'thought_habit' => [
                ['label' => '全部ダメだと思った（0-100思考）'],
                ['label' => '自分のせいだと思いすぎた'],
                ['label' => '相手の気持ちを勝手に想像して疲れた'],
                ['label' => '同時に全部解決しようとした'],
                ['label' => '何も考えたくなくなった'],
                ['label' => '特になし', 'is_none' => true],
            ],
            'body_reaction' => [
                ['label' => '睡眠が浅い'],
                ['label' => '胃・胸が重い'],
                ['label' => 'イライラ'],
                ['label' => '無気力'],
                ['label' => '頭が回らない'],
                ['label' => '特になし', 'is_none' => true],
            ],
            'recovery_action' => [
                ['label' => '温泉・サウナ'],
                ['label' => '食事で回復'],
                ['label' => '音楽・バンド系'],
                ['label' => '一人時間'],
                ['label' => '軽い運動・散歩'],
                // 実データでは他の回復行動と排他になっている（§2 ⑤）。is_none にすることで
                // 排他ロジックが効き、同時に「効いた感」の必須化からも外れる。
                ['label' => '何もできてない', 'is_none' => true],
                ['label' => 'その他', 'requires_text' => true],
            ],
            // 回復行動に紛れていた飲酒・カフェインを切り出す（回復行動とは別物として測る）
            'intake' => [
                ['label' => 'コーヒー・エナドリ'],
                ['label' => 'アルコール'],
                ['label' => '市販薬'],
                ['label' => 'どちらもなし', 'is_none' => true],
                ['label' => 'その他', 'requires_text' => true],
            ],
        ];

        foreach ($data as $categoryCode => $options) {
            $category = ChecklistCategory::where('code', $categoryCode)->firstOrFail();

            foreach ($options as $i => $option) {
                ChecklistOption::updateOrCreate(
                    ['category_id' => $category->id, 'label' => $option['label']],
                    [
                        'requires_text' => $option['requires_text'] ?? false,
                        'is_none' => $option['is_none'] ?? false,
                        'sort_order' => $i + 1,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
