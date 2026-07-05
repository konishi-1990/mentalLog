<?php

namespace App\Observers;

use App\Models\Role;
use App\Models\User;
use App\Support\DefaultCheckItems;

class UserObserver
{
    /**
     * 作成前：ロール未指定なら既定の一般ユーザロールを割り当てる。
     */
    public function creating(User $user): void
    {
        if (empty($user->role_id)) {
            $user->role_id = Role::firstOrCreate(
                ['code' => 'user'],
                ['name' => '一般ユーザ'],
            )->id;
        }
    }

    /**
     * 作成後：既定の○×項目テンプレートを複製する。
     */
    public function created(User $user): void
    {
        $items = [];
        foreach (DefaultCheckItems::names() as $i => $name) {
            $items[] = [
                'user_id' => $user->id,
                'name' => $name,
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $user->checkItems()->insert($items);
    }
}
