<?php

use App\Models\SocialiteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteOauthUser;

uses(RefreshDatabase::class);

/**
 * Build a fake Laravel\Socialite\Two\User as returned by Socialite::driver(...)->user().
 */
function fakeOauthUser(string $id, string $email, string $name = 'Test User'): SocialiteOauthUser
{
    $user = new SocialiteOauthUser;
    $user->id = $id;
    $user->email = $email;
    $user->name = $name;
    $user->nickname = $name;
    $user->avatar = null;

    return $user;
}

it('renders a google button on the panel login page', function () {
    $response = $this->get('/admin/login');

    $response->assertStatus(200);
    // Plugin renders an anchor to the redirect route under route name
    // `socialite.filament.admin.oauth.redirect` and labels the button "Google".
    $response->assertSee('Google', escape: false);
    $response->assertSee('admin/oauth/google', escape: false);
});

it('logs in an existing user matched by email', function () {
    $alice = User::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => null,
    ]);

    $oauthUser = fakeOauthUser('google-alice-id', 'alice@example.com', 'Alice');

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $response = $this->get('/admin/oauth/callback/google');

    expect(Auth::check())->toBeTrue();
    expect(Auth::user()->is($alice))->toBeTrue();

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/admin');

    expect(SocialiteUser::query()
        ->where('provider', 'google')
        ->where('provider_id', 'google-alice-id')
        ->where('user_id', $alice->id)
        ->count()
    )->toBe(1);
});

it('re uses the socialite user row on subsequent logins', function () {
    $alice = User::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => null,
    ]);

    $oauthUser = fakeOauthUser('google-alice-id', 'alice@example.com', 'Alice');

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    // First login: creates the SocialiteUser row.
    $this->get('/admin/oauth/callback/google');

    Auth::logout();
    $this->flushSession();

    // Second login: should re-use the existing SocialiteUser row.
    $this->get('/admin/oauth/callback/google');

    expect(Auth::check())->toBeTrue();
    expect(Auth::user()->is($alice))->toBeTrue();

    expect(SocialiteUser::query()
        ->where('provider', 'google')
        ->where('provider_id', 'google-alice-id')
        ->count()
    )->toBe(1);
});

it('auto registers a user when email domain is on the allowlist', function () {
    Config::set('services.filament_socialite.domain_allowlist', ['example.com']);

    $oauthUser = fakeOauthUser('google-bob-id', 'bob@example.com', 'Bob');

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    expect(User::where('email', 'bob@example.com')->exists())->toBeFalse();

    $response = $this->get('/admin/oauth/callback/google');

    expect(Auth::check())->toBeTrue();
    expect(Auth::user()->email)->toBe('bob@example.com');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/admin');

    $bob = User::where('email', 'bob@example.com')->first();
    expect($bob)->not->toBeNull();

    expect(SocialiteUser::query()
        ->where('provider', 'google')
        ->where('provider_id', 'google-bob-id')
        ->where('user_id', $bob->id)
        ->count()
    )->toBe(1);
});

it('blocks login when email domain is not on the allowlist', function () {
    Config::set('services.filament_socialite.domain_allowlist', ['example.com']);

    $oauthUser = fakeOauthUser('google-mallory-id', 'mallory@evil.com', 'Mallory');

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $response = $this->get('/admin/oauth/callback/google');

    expect(Auth::check())->toBeFalse();
    expect(User::where('email', 'mallory@evil.com')->exists())->toBeFalse();
    expect(SocialiteUser::query()->where('provider_id', 'google-mallory-id')->count())->toBe(0);

    $response->assertStatus(302);
    $response->assertRedirect('/admin/login');
    $response->assertSessionHas('filament-socialite-login-error');
});

it('blocks auto registration when allowlist is empty', function () {
    Config::set('services.filament_socialite.domain_allowlist', []);

    $oauthUser = fakeOauthUser('google-stranger-id', 'stranger@somewhere.test', 'Stranger');

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $response = $this->get('/admin/oauth/callback/google');

    expect(Auth::check())->toBeFalse();
    expect(User::where('email', 'stranger@somewhere.test')->exists())->toBeFalse();
    expect(SocialiteUser::query()->where('provider_id', 'google-stranger-id')->count())->toBe(0);

    $response->assertStatus(302);
    $response->assertRedirect('/admin/login');
    $response->assertSessionHas('filament-socialite-login-error');
});
