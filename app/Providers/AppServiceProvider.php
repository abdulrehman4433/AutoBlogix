<?php

namespace App\Providers;

use App\Models\Website;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Owner-scoped model binding: a website ID belonging to another user
         * resolves to 404 (existence is never revealed). Runs after the auth
         * middleware, so the session's user is always available here.
         */
        Route::bind('website', function (string $value): Website {
            $user = auth()->user();

            abort_if($user === null, 404);

            return $user->websites()->whereKey($value)->firstOrFail();
        });
    }
}
