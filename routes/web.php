<?php

use App\Http\Controllers\AiContentController;
use App\Http\Controllers\AiProviderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PostPublishController;
use App\Http\Controllers\PostsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebsiteCredentialController;
use App\Http\Controllers\WebsitesController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/websites/create', [WebsitesController::class, 'create'])->name('websites.create');
    Route::post('/websites', [WebsitesController::class, 'store'])->name('websites.store');

    Route::resource('websites', WebsitesController::class)
        ->except(['create', 'store']);

    Route::post('/websites/{website}/credentials/rotate', [WebsiteCredentialController::class, 'rotate'])
        ->name('websites.credentials.rotate');
    Route::post('/websites/{website}/credentials/revoke', [WebsiteCredentialController::class, 'revoke'])
        ->name('websites.credentials.revoke');

    Route::get('/posts/create', [PostsController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostsController::class, 'store'])->name('posts.store');

    Route::resource('posts', PostsController::class)
        ->except(['create', 'store']);

    Route::post('/posts/{post}/publish', [PostPublishController::class, 'store'])
        ->name('posts.publish');

    Route::get('/ai-content', [AiContentController::class, 'show'])->name('ai.generate');
    Route::post('/ai-content', [AiContentController::class, 'store'])->name('ai.store');
    Route::post('/posts/{post}/generate', [AiContentController::class, 'generate'])
        ->name('posts.generate');

    Route::get('/ai-providers', [AiProviderController::class, 'show'])->name('ai.providers');
    Route::post('/ai-providers', [AiProviderController::class, 'store'])->name('ai.providers.store');
    Route::post('/ai-providers/{provider}/activate', [AiProviderController::class, 'activate'])
        ->name('ai.providers.activate');
    Route::delete('/ai-providers/{provider}', [AiProviderController::class, 'destroy'])
        ->name('ai.providers.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
