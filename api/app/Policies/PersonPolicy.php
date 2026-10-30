<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Person $person): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSocio() && $user->person_id === $person->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Person $person): bool
    {
        return $user->isAdmin();
    }

    public function changeStatus(User $user, Person $person): bool
    {
        return $user->isAdmin() && ! $this->isSelf($user, $person);
    }

    public function manageAccount(User $user, Person $person): bool
    {
        return $user->isAdmin() && ! $this->isSelf($user, $person);
    }

    private function isSelf(User $user, Person $person): bool
    {
        return (int) $user->person_id === (int) $person->id;
    }
}
