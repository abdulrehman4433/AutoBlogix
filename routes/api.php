<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WordPressApiController;
use App\Http\Middleware\VerifyWordPressSignature;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | WordPress API Routes
 |--------------------------------------------------------------------------
 |
 | Called by the AutoBlogix WordPress plugin. Every request must carry the
 | X-ABX-* HMAC headers (verified by the signature middleware, which runs
 | after the named throttle). No session, no CSRF: these routes are stateless
 | and render JSON (api/* always renders JSON in this app).
 |
 */

Route::prefix('v1/wordpress')->group(function (): void {
    $signature = VerifyWordPressSignature::class;

    Route::post('/connect', [WordPressApiController::class, 'connect'])
        ->middleware(['throttle:wordpress-connect', $signature])
        ->name('api.wordpress.connect');

    Route::post('/verify', [WordPressApiController::class, 'verify'])
        ->middleware(['throttle:wordpress-verify', $signature])
        ->name('api.wordpress.verify');

    Route::post('/disconnect', [WordPressApiController::class, 'disconnect'])
        ->middleware(['throttle:wordpress-api', $signature])
        ->name('api.wordpress.disconnect');

    Route::post('/heartbeat', [WordPressApiController::class, 'heartbeat'])
        ->middleware(['throttle:wordpress-api', $signature])
        ->name('api.wordpress.heartbeat');

    Route::post('/publish-result', [WordPressApiController::class, 'publishResult'])
        ->middleware(['throttle:wordpress-publish-result', $signature])
        ->name('api.wordpress.publish-result');
});
