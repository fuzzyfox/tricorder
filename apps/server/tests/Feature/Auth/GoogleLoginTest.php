<?php

use App\Models\SocialiteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
