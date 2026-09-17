<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:create-admin {email : The administrator email address} {--name= : The administrator name}')]
#[Description('Create or enable an administrator account')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User && $name === '') {
            $this->components->error('The --name option is required when creating a user.');

            return self::FAILURE;
        }

        $user ??= new User;
        $user->forceFill([
            'name' => $name !== '' ? $name : $user->name,
            'email' => $email,
            'is_admin' => true,
            'enabled' => true,
        ]);
        $user->save();

        $this->components->info('Administrator account is ready.');

        return self::SUCCESS;
    }
}
