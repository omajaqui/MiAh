<?php

namespace App\Policies;

use App\Models\SavingsCycle;
use App\Models\User;

class SavingsCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSocio();
    }

    public function view(User $user, SavingsCycle $cycle): bool
    {
        return $user->canAccessCycle($cycle);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SavingsCycle $cycle): bool
    {
        return $user->isAdmin() && $cycle->isOpen();
    }

    public function manageMembers(User $user, SavingsCycle $cycle): bool
    {
        return $user->isAdmin() && $cycle->isOpen();
    }

    public function start(User $user, SavingsCycle $cycle): bool
    {
        return $user->isAdmin() && $cycle->isOpen();
    }

    public function close(User $user, SavingsCycle $cycle): bool
    {
        return $user->isAdmin() && ($cycle->isOpen() || $cycle->isInProgress());
    }

    public function finalize(User $user, SavingsCycle $cycle): bool
    {
        return $user->isAdmin() && $cycle->isInProgress();
    }

    public function registerMovement(User $user, SavingsCycle $cycle): bool
    {
        return $user->isAdmin() && $cycle->isInProgress();
    }

    public function viewAdmin(User $user): bool
    {
        return $user->isAdmin();
    }
}
