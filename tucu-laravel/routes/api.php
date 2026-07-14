<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MemberAuthController;
use App\Http\Controllers\Api\MemberPortalController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\PrayerRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api automatically by Laravel's
| RouteServiceProvider (bootstrap/app.php in Laravel 11).
|
*/

// ── PUBLIC ROUTES ──────────────────────────────────────────────
Route::post('/members/register', [MemberAuthController::class, 'register']);
Route::post('/members/login', [MemberAuthController::class, 'login']);

Route::get('/events', [EventController::class, 'index']);
Route::post('/events/register', [EventController::class, 'register'])
    ->middleware(\App\Http\Middleware\OptionalSanctumAuth::class);

Route::post('/prayer', [PrayerRequestController::class, 'store'])
    ->middleware(\App\Http\Middleware\OptionalSanctumAuth::class);

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware(\App\Http\Middleware\OptionalSanctumAuth::class);

Route::post('/contact', [ContactController::class, 'store']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);


// ── MEMBER PORTAL ROUTES (protected — member can only access own data) ──
Route::middleware('auth:sanctum')->prefix('members')->group(function () {
    Route::post('/logout', [MemberAuthController::class, 'logout']);

    Route::get('/{id}/profile', [MemberPortalController::class, 'profile'])
        ->where('id', '[0-9]+');
    Route::put('/{id}/profile', [MemberPortalController::class, 'updateProfile'])
        ->where('id', '[0-9]+');

    Route::get('/{id}/events', [MemberPortalController::class, 'events'])
        ->where('id', '[0-9]+');

    Route::get('/{id}/prayers', [MemberPortalController::class, 'prayers'])
        ->where('id', '[0-9]+');

    Route::put('/{id}/newsletter', [MemberPortalController::class, 'updateNewsletter'])
        ->where('id', '[0-9]+');
});


// ── ADMIN ROUTES (protected — requires admin guard) ─────────────
Route::middleware('auth:admin-sanctum')->prefix('admin')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout']);

    Route::get('/stats', [AdminController::class, 'stats']);

    Route::get('/members', [AdminController::class, 'members']);
    Route::get('/members/{id}', [AdminController::class, 'memberShow']);
    Route::put('/members/{id}', [AdminController::class, 'memberUpdate']);
    Route::put('/members/{id}/status', [AdminController::class, 'memberStatus']);
    Route::delete('/members/{id}', [AdminController::class, 'memberDelete']);

    Route::get('/prayers', [AdminController::class, 'prayers']);
    Route::put('/prayers/{id}/status', [AdminController::class, 'prayerStatus']);

    Route::get('/registrations', [AdminController::class, 'registrations']);
    Route::put('/registrations/{id}/status', [AdminController::class, 'registrationStatus']);

    Route::get('/messages', [AdminController::class, 'messages']);
    Route::put('/messages/{id}/read', [AdminController::class, 'messageRead']);

    Route::get('/subscribers', [AdminController::class, 'subscribers']);
});
