<?php

namespace App\Providers;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use App\Support\CurrentTerm;
use App\Support\Rbac\AccessControl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTerm::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->role === RoleEnum::SuperAdmin ? true : null);

        Gate::define('module', fn (User $user, string $module) => app(AccessControl::class)->can($user, $module) !== AccessLevelEnum::Blocked);

        Gate::define('manage-org-structure', fn (User $user) => false);

        Gate::define('manage-restrictions', fn (User $user) => false);
    }
}
