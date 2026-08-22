<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function update(User $user, Person $person): bool
    {
        return $person->user_id === $user->id;
    }

    public function delete(User $user, Person $person): bool
    {
        return $person->user_id === $user->id;
    }
}
