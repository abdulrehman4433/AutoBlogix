<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_exactly_one_admin_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());

        $user = User::where('email', UserSeeder::DEFAULT_EMAIL)->firstOrFail();

        $this->assertSame('AutoBlogix Admin', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check(UserSeeder::DEFAULT_PASSWORD, $user->password));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
    }

    public function test_seeder_refuses_default_password_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.seed_admin_password' => null]);

        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_seeder_uses_configured_password_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.seed_admin_password' => 'a-strong-production-secret']);

        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $user = User::where('email', UserSeeder::DEFAULT_EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check('a-strong-production-secret', $user->password));
        $this->assertFalse(Hash::check(UserSeeder::DEFAULT_PASSWORD, $user->password));
    }
}
