<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * The only demo account created by the seeders.
     */
    public const string DEFAULT_EMAIL = 'admin@autoblogix.test';

    /**
     * Default local-development password. Never used in production.
     */
    public const string DEFAULT_PASSWORD = 'password';

    /**
     * Create (or update) the single AutoBlogix admin user.
     */
    public function run(): void
    {
        $password = self::DEFAULT_PASSWORD;

        if (app()->environment('production')) {
            $password = (string) config('app.seed_admin_password');

            if ($password === '') {
                $this->command?->warn(
                    'UserSeeder skipped: set SEED_ADMIN_PASSWORD in .env to seed '.self::DEFAULT_EMAIL.' in production.'
                );

                return;
            }
        }

        User::updateOrCreate(
            ['email' => self::DEFAULT_EMAIL],
            [
                'name' => 'AutoBlogix Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Seeded admin user '.self::DEFAULT_EMAIL.'.');
    }
}
