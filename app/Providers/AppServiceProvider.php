<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\Website;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        /*
         * Same owner scoping for posts: another user's post ID resolves to
         * 404 (existence is never revealed).
         */
        Route::bind('post', function (string $value): BlogPost {
            $user = auth()->user();

            abort_if($user === null, 404);

            return $user->blogPosts()->whereKey($value)->firstOrFail();
        });

        $this->registerWordPressApiRateLimiters();
    }

    /**
     * Named rate limiters for the WordPress API: every endpoint is limited
     * per API key (the real identity) and per source IP (abuse guard).
     * Connect, verify and publish-result carry the strictest budgets.
     */
    private function registerWordPressApiRateLimiters(): void
    {
        RateLimiter::for('wordpress-connect', function (Request $request) {
            return [
                Limit::perMinute(10)->by('wp-connect-ip:'.$request->ip()),
                Limit::perMinute(10)->by('wp-connect-key:'.Str::lower((string) $request->header('X-ABX-Key'))),
            ];
        });

        RateLimiter::for('wordpress-verify', function (Request $request) {
            return [
                Limit::perMinute(30)->by('wp-verify-ip:'.$request->ip()),
                Limit::perMinute(30)->by('wp-verify-key:'.Str::lower((string) $request->header('X-ABX-Key'))),
            ];
        });

        RateLimiter::for('wordpress-api', function (Request $request) {
            return [
                Limit::perMinute(120)->by('wp-api-ip:'.$request->ip()),
                Limit::perMinute(60)->by('wp-api-key:'.Str::lower((string) $request->header('X-ABX-Key'))),
            ];
        });

        RateLimiter::for('wordpress-publish-result', function (Request $request) {
            return [
                Limit::perMinute(60)->by('wp-result-ip:'.$request->ip()),
                Limit::perMinute(60)->by('wp-result-key:'.Str::lower((string) $request->header('X-ABX-Key'))),
            ];
        });
    }
}
