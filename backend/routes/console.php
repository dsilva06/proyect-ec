<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('make:admin {email} {--password=} {--name=Admin User}', function () {
    $email = $this->argument('email');
    $name = $this->option('name') ?? 'Admin User';
    $password = $this->option('password') ?: $this->secret('Password');

    if (! $password) {
        $this->error('Password is required.');
        return self::FAILURE;
    }

    $user = User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password_hash' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
        ]
    );

    $this->info("Admin user ready: {$user->email}");

    return self::SUCCESS;
})->purpose('Create or update an admin user.');
