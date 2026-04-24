<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeAdminUser extends Command
{
    protected $signature = 'tricorder:make-admin {email : Email address for the panel user} {--name=Admin : Display name for the panel user} {--with-password : Generate, hash, store, and print a one-time password (break-glass)}';

    protected $description = 'Create or update a Filament panel user. Defaults to passwordless (Google login); use --with-password for break-glass.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->option('name');
        $withPassword = (bool) $this->option('with-password');

        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        if ($isNew) {
            $user->name = $name;
        }

        if ($withPassword) {
            $password = Str::password(24);
            $user->password = Hash::make($password);
            $user->save();

            $this->line("email:    {$email}");
            $this->line("password: {$password}");

            return self::SUCCESS;
        }

        // Passwordless mode: leave any existing password untouched.
        if ($isNew) {
            $user->password = null;
        }
        $user->save();

        $baseUrl = rtrim((string) config('app.url'), '/');
        $this->line("Log in at {$baseUrl}/admin with Google using {$email}");

        return self::SUCCESS;
    }
}
