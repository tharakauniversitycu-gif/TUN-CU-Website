<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('member-login', fn ($r) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(config('library.login_attempts_per_minute'))->by($r->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('library-member', fn ($r) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(config('library.requests_per_minute'))->by($r->user()?->id ?? $r->ip()));
    }
}
