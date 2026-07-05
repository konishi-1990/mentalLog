<?php

namespace App\Policies;

use App\Models\Log;
use App\Models\User;

class LogPolicy
{
    /**
     * 管理者は全て許可（他のメソッド判定より優先）。
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Log $log): bool
    {
        return $log->user_id === $user->id;
    }

    public function update(User $user, Log $log): bool
    {
        return $log->user_id === $user->id;
    }

    public function delete(User $user, Log $log): bool
    {
        return $log->user_id === $user->id;
    }
}
