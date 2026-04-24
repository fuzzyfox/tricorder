<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user by email', function () {
    $this->artisan('tricorder:make-admin', ['email' => 'dri@example.com'])
        ->assertExitCode(0);

    expect(User::where('email', 'dri@example.com')->count())->toBe(1);
});

it('is idempotent', function () {
    $this->artisan('tricorder:make-admin', ['email' => 'dri@example.com'])
        ->assertExitCode(0);

    $this->artisan('tricorder:make-admin', ['email' => 'dri@example.com'])
        ->assertExitCode(0);

    expect(User::where('email', 'dri@example.com')->count())->toBe(1);
});
