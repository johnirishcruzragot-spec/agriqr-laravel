<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('agriqr:create-admin', function () {
    if (User::query()->exists()) {
        $this->error('An account already exists. Sign in with an administrator account to add team members.');

        return Command::FAILURE;
    }

    $name = $this->ask('Administrator name');
    $email = strtolower((string) $this->ask('Administrator email'));
    $username = (string) $this->ask('Username', Str::before($email, '@'));
    $password = (string) $this->secret('Password (at least 12 characters)');
    $confirmation = (string) $this->secret('Confirm password');

    $validator = Validator::make([
        'name' => $name,
        'email' => $email,
        'username' => $username,
        'password' => $password,
        'confirmation' => $confirmation,
    ], [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:254', 'unique:users,email'],
        'username' => ['required', 'string', 'max:50', 'unique:users,username'],
        'password' => ['required', 'string', 'min:12'],
        'confirmation' => ['required', 'same:password'],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return Command::FAILURE;
    }

    User::create([
        'name' => $name,
        'email' => $email,
        'username' => $username,
        'password' => Hash::make($password),
        'role' => 'super_admin',
        'is_active' => true,
    ]);

    $this->info('Administrator account created. Sign in at /login.');

    return Command::SUCCESS;
})->purpose('Create the first AgriQR administrator account');
