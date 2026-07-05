<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // マスタ（順序に依存：categories → options）
        $this->call([
            RoleSeeder::class,
            ChecklistCategorySeeder::class,
            ChecklistOptionSeeder::class,
        ]);

        // 開発用の管理者ユーザ（UserObserver により○×テンプレも複製される）
        User::factory()->admin()->create([
            'name' => '管理者',
            'email' => 'admin@example.com',
        ]);
    }
}
