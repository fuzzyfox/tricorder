<?php

use App\Models\User;
use DutchCodingCompany\FilamentSocialite\Events\Login;
use DutchCodingCompany\FilamentSocialite\Events\UserNotAllowed;
use DutchCodingCompany\FilamentSocialite\Models\Contracts\FilamentSocialiteUser as FilamentSocialiteUserContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Two\User as SocialiteOauthUser;

uses(RefreshDatabase::class);

/**
 * Build a fake Laravel\Socialite\Two\User as returned by Socialite::driver(...)->user().
 */
function makeFakeOauthUser(string $id, string $email, string $name = 'Test User'): SocialiteOauthUser
{
    $user = new SocialiteOauthUser;
    $user->id = $id;
    $user->email = $email;
    $user->name = $name;
    $user->nickname = $name;
    $user->avatar = null;

    return $user;
}

/**
 * Minimal in-memory FilamentSocialiteUser pivot stand-in. The plugin's Login
 * event reads `$socialiteUser->getUser()` and `$socialiteUser->provider`, so
 * the listener only needs those two surfaces.
 */
function makeFakeSocialiteUser(Authenticatable $user, string $provider): FilamentSocialiteUserContract
{
    return new class($user, $provider) implements FilamentSocialiteUserContract
    {
        public function __construct(
            public Authenticatable $user,
            public string $provider,
        ) {
        }

        public function getUser(): Authenticatable
        {
            return $this->user;
        }

        public static function findForProvider(string $provider, SocialiteUserContract $oauthUser): ?FilamentSocialiteUserContract
        {
            return null;
        }

        public static function createForProvider(string $provider, SocialiteUserContract $oauthUser, Authenticatable $user): FilamentSocialiteUserContract
        {
            // not needed for these tests
            throw new RuntimeException('not implemented in fake');
        }
    };
}

it('logs at info when a user logs in', function () {
    $alice = User::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => null,
    ]);

    $oauthUser = makeFakeOauthUser('google-alice-id', 'alice@example.com', 'Alice');
    $socialiteUser = makeFakeSocialiteUser($alice, 'google');

    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn ($msg, $ctx) => $msg === 'socialite.login'
            && $ctx['user_id'] === $alice->id
            && $ctx['provider'] === 'google');

    event(new Login($socialiteUser, $oauthUser));
});

it('logs at warning when a user is not allowed', function () {
    $oauthUser = makeFakeOauthUser('google-mallory-id', 'mallory@evil.com', 'Mallory');

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg, $ctx) => $msg === 'socialite.denied'
            && $ctx['email'] === 'mallory@evil.com'
            && $ctx['reason'] === 'domain_not_allowed');

    event(new UserNotAllowed($oauthUser));
});
