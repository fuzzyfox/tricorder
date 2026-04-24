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
     *
     * Filament-Socialite event listeners live in App\Listeners\LogSocialiteEvents
     * and are wired up via Laravel's default event auto-discovery (see
     * `bootstrap/app.php` → `Application::configure()` → `withEvents()`). Each
     * `handle<Event>(EventClass $event)` method is reflected at boot and
     * registered against its first-parameter type-hint, so no manual
     * `Event::listen()` calls are needed here.
     */
    public function boot(): void
    {
        //
    }
}
