<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminLibraryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DocketController;
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
*/

// ── PUBLIC ROUTES ──────────────────────────────────────────────
Route::get('/dockets', [DocketController::class, 'index']);
Route::get('/calendars/current', [DocketController::class, 'calendars']);

Route::post('/members/register', [MemberAuthController::class, 'register'])->middleware('throttle:member-login');
Route::post('/members/login', [MemberAuthController::class, 'login'])->middleware('throttle:member-login');

Route::get('/events', [EventController::class, 'index']);
Route::post('/events/register', [EventController::class, 'register'])
    ->middleware(\App\Http\Middleware\OptionalSanctumAuth::class);

Route::post('/prayer', [PrayerRequestController::class, 'store'])
    ->middleware(\App\Http\Middleware\OptionalSanctumAuth::class);

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware(\App\Http\Middleware\OptionalSanctumAuth::class);

Route::post('/contact', [ContactController::class, 'store']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::prefix('library')->middleware(\App\Http\Middleware\LibraryAccess::class)->group(function () {
    $c = \App\Http\Controllers\Api\LibraryController::class;
    Route::get('categories', [$c, 'categories']);
    Route::get('books', [$c, 'index']);
    Route::get('books/{book}', [$c, 'show'])->whereNumber('book');
    Route::get('books/{book}/cover', [$c, 'cover'])->whereNumber('book');
    Route::middleware(['auth:sanctum', \App\Http\Middleware\LibraryAccess::class.':member', 'throttle:library-member'])->group(function () use ($c) {
        Route::get('me/books', [$c, 'shelf']);
        Route::get('me/books/{book}', [$c, 'memberBook'])->whereNumber('book');
        Route::match(['put', 'delete'], 'me/books/{book}/like', [$c, 'like'])->whereNumber('book');
        Route::put('me/books/{book}/progress', [$c, 'progress'])->whereNumber('book');
        Route::get('books/{book}/content', [$c, 'content'])->whereNumber('book');
        Route::post('books/{book}/download', [$c, 'download'])->whereNumber('book');
    });
});


// ── MEMBER PORTAL ROUTES (protected — member can only access own data) ──
Route::middleware('auth:sanctum')->prefix('members')->group(function () {
    Route::post('/logout', [MemberAuthController::class, 'logout']);

    Route::get('/{id}/profile', [MemberPortalController::class, 'profile'])
        ->where('id', '[0-9]+');
    Route::put('/{id}/profile', [MemberPortalController::class, 'updateProfile'])
        ->where('id', '[0-9]+');

    Route::post('/{id}/confirm-year', [MemberPortalController::class, 'confirmYear'])
        ->where('id', '[0-9]+');

    Route::get('/{id}/announcements', [MemberPortalController::class, 'announcements'])
        ->where('id', '[0-9]+');
    Route::post('/{id}/announcements', [MemberPortalController::class, 'storeAnnouncement'])
        ->where('id', '[0-9]+');

    Route::get('/{id}/associate-hub', [MemberPortalController::class, 'associateHub'])
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

    Route::get('/library', [AdminLibraryController::class, 'index']);
    Route::post('/library/categories', [AdminLibraryController::class, 'storeCategory']);
    Route::post('/library/books', [AdminLibraryController::class, 'store']);
    Route::put('/library/books/bulk-status', [AdminLibraryController::class, 'bulkStatus']);
    Route::put('/library/books/{book}', [AdminLibraryController::class, 'update'])->whereNumber('book');
    Route::post('/library/books/{book}/editions', [AdminLibraryController::class, 'addEdition'])->whereNumber('book');
    Route::put('/library/books/{book}/status', [AdminLibraryController::class, 'setStatus'])->whereNumber('book');

    Route::post('/academic-years/rollover', [AdminController::class, 'academicYearRollover']);

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
