<?php

namespace App\Listeners;

use DutchCodingCompany\FilamentSocialite\Events\InvalidState;
use DutchCodingCompany\FilamentSocialite\Events\Login;
use DutchCodingCompany\FilamentSocialite\Events\Registered;
use DutchCodingCompany\FilamentSocialite\Events\RegistrationNotEnabled;
use DutchCodingCompany\FilamentSocialite\Events\UserNotAllowed;
use Illuminate\Support\Facades\Log;

/**
 * Audit-logs the events emitted by the filament-socialite plugin.
 *
 * Each method maps one plugin event to a single structured Log line so the
 * `storage/logs/laravel.log` file gives operators a paper trail for Google
 * sign-ins, denials, and registration attempts.
 */
class LogSocialiteEvents
{
    /**
     * Successful Google login for an existing or freshly-registered user.
     *
     * The plugin's Login event carries `$socialiteUser` (the FilamentSocialite
     * pivot row, which knows its `provider`) and `$oauthUser` (the upstream
     * Socialite user). Both are real, neither is a `$user`/`$provider` pair.
     */
    public function handleLogin(Login $event): void
    {
        $user = $event->socialiteUser->getUser();

        Log::info('socialite.login', [
            'user_id' => $user->getAuthIdentifier(),
            'provider' => $event->socialiteUser->provider,
        ]);
    }

    /**
     * Auto-registration via the domain allowlist created a new user.
     */
    public function handleRegistered(Registered $event): void
    {
        $user = $event->socialiteUser->getUser();

        Log::info('socialite.registered', [
            'user_id' => $user->getAuthIdentifier(),
            'provider' => $event->provider,
            'email' => $event->oauthUser->getEmail(),
        ]);
    }

    /**
     * Login was rejected (typically because the email domain is not on the
     * allowlist).
     */
    public function handleUserNotAllowed(UserNotAllowed $event): void
    {
        Log::warning('socialite.denied', [
            'email' => $event->oauthUser?->getEmail(),
            'reason' => 'domain_not_allowed',
        ]);
    }

    /**
     * Socialite returned with an invalid OAuth state token (CSRF / replay).
     */
    public function handleInvalidState(InvalidState $event): void
    {
        Log::warning('socialite.invalid_state');
    }

    /**
     * Login attempted by an unknown email while auto-registration is closed.
     */
    public function handleRegistrationNotEnabled(RegistrationNotEnabled $event): void
    {
        Log::warning('socialite.registration_disabled', [
            'email' => $event->oauthUser?->getEmail(),
        ]);
    }
}
