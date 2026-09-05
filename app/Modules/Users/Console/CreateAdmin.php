<?php

namespace App\Modules\Users\Console;

use App\Modules\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=}';

    protected $description = 'Create an administrator account';

    public function handle(): int
    {
        $name     = $this->option('name') ?: $this->ask('Name');
        $email    = $this->option('email') ?: $this->ask('Email address');
        $password = $this->secret('Password (at least 8 characters)');

        try {
            validator(
                ['name' => $name, 'email' => $email, 'password' => $password],
                [
                    'name'     => ['required', 'string', 'max:255'],
                    'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
                    'password' => ['required', 'string', Password::defaults()],
                ],
            )->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $this->info('Administrator account created.');

        return self::SUCCESS;
    }
}
