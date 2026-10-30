<?php

namespace App\Providers;

use App\Models\Person;
use App\Models\SavingsCycle;
use App\Models\User;
use App\Policies\PersonPolicy;
use App\Policies\SavingsCyclePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(SavingsCycle::class, SavingsCyclePolicy::class);

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('socio', fn (User $user) => $user->isSocio());
    }
}
