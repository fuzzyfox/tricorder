<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates a passwordless user by default', function () {
    $email = 'alice@example.com';

    $exitCode = Artisan::call('tricorder:make-admin', ['email' => $email]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Log in at');
    expect($output)->toContain('Google');
    expect($output)->toContain($email);

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();
    expect($user->password)->toBeNull();
});

it('creates a passworded user with the flag', function () {
    $email = 'bob@example.com';

    $exitCode = Artisan::call('tricorder:make-admin', ['email' => $email, '--with-password' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('email:');
    expect($output)->toContain('password:');

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();
    expect($user->password)->not->toBeNull();
});

it('is idempotent in passwordless mode', function () {
    $email = 'carol@example.com';

    $this->artisan('tricorder:make-admin', ['email' => $email])
        ->assertExitCode(0);

    expect(User::where('email', $email)->first()->password)->toBeNull();

    $this->artisan('tricorder:make-admin', ['email' => $email])
        ->assertExitCode(0);

    expect(User::where('email', $email)->count())->toBe(1);
    expect(User::where('email', $email)->first()->password)->toBeNull();
});

it('does not overwrite an existing password when run passwordless', function () {
    $email = 'dave@example.com';
    $existingHash = Hash::make('s3cret-existing-password');

    User::create([
        'name' => 'Dave',
        'email' => $email,
        'password' => $existingHash,
    ]);

    $this->artisan('tricorder:make-admin', ['email' => $email])
        ->assertExitCode(0);

    $user = User::where('email', $email)->first();
    expect($user->password)->toBe($existingHash);
});

it('rotates the password when run with flag again', function () {
    $email = 'eve@example.com';

    $extractPlaintext = function (string $output): string {
        expect($output)->toMatch('/password:\s+(.+)/');
        preg_match('/password:\s+(\S+)/', $output, $matches);

        return $matches[1];
    };

    $exitFirst = Artisan::call('tricorder:make-admin', ['email' => $email, '--with-password' => true]);
    expect($exitFirst)->toBe(0);
    $firstOutput = Artisan::output();
    $firstPlaintext = $extractPlaintext($firstOutput);
    $firstHash = User::where('email', $email)->first()->password;

    $exitSecond = Artisan::call('tricorder:make-admin', ['email' => $email, '--with-password' => true]);
    expect($exitSecond)->toBe(0);
    $secondOutput = Artisan::output();
    $secondPlaintext = $extractPlaintext($secondOutput);
    $secondHash = User::where('email', $email)->first()->password;

    expect($firstPlaintext)->not->toBe($secondPlaintext);
    expect($firstHash)->not->toBe($secondHash);
});
