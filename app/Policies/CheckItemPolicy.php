<?php

namespace App\Policies;

use App\Models\CheckItem;
use App\Models\User;

class CheckItemPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function update(User $user, CheckItem $checkItem): bool
    {
        return $checkItem->user_id === $user->id;
    }

    public function delete(User $user, CheckItem $checkItem): bool
    {
        return $checkItem->user_id === $user->id;
    }
}
