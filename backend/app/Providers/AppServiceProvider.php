<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));

        Gate::define('platform.admin', fn (User $user) => $user->is_platform_admin);

        foreach (config('permissions.abilities') as $ability) {
            Gate::define($ability, fn (User $user) => app(CurrentTenant::class)->can($user, $ability));
        }
    }
}
