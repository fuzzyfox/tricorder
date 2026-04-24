<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeAdminUser extends Command
{
    protected $signature = 'tricorder:make-admin {email : Email address for the panel user} {--name=Admin : Display name for the panel user}';

    protected $description = 'Create or update a Filament panel user and print a one-time password.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->option('name');
        $password = Str::password(24);

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );

        $this->line("email:    {$email}");
        $this->line("password: {$password}");

        return self::SUCCESS;
    }
}
