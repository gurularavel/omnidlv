<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Creates a platform admin, or promotes an existing user, and prints a new password once. */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-superadmin {email} {--name=Platform admin}';

    protected $description = 'Create a platform admin (or promote a user) and print a new password';

    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));
        $password = Str::password(16, symbols: false);

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        if ($user->trashed()) {
            $user->restore();
        }
        $user->name ??= $this->option('name');
        $user->forceFill(['password' => $password, 'is_super' => true])->save();

        $this->info("Platform admin: {$email}");
        $this->line("Password: {$password}");
        $this->line('Sign in at '.route('login').' and open '.route('admin.dashboard'));

        return self::SUCCESS;
    }
}
