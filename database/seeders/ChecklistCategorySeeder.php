<?php

namespace Database\Seeders;

use App\Models\ChecklistCategory;
use Illuminate\Database\Seeder;

class ChecklistCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'thought_habit', 'name' => '頭の中のクセ', 'sort_order' => 1],
            ['code' => 'body_reaction', 'name' => '体の反応', 'sort_order' => 2],
            ['code' => 'recovery_action', 'name' => '回復行動', 'sort_order' => 3],
        ];

        foreach ($categories as $category) {
            ChecklistCategory::updateOrCreate(['code' => $category['code']], $category);
        }
    }
}
