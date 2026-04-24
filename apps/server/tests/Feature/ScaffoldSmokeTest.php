<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('boots the health endpoint', function () {
    $this->get('/up')->assertStatus(200);
});

it('runs every migration cleanly', function () {
    expect(DB::connection()->getDatabaseName())->not->toBeEmpty();

    $migrations = DB::table('migrations')->pluck('migration')->all();

    expect($migrations)->not->toBeEmpty();
});
