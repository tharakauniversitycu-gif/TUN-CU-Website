<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The frontend (index.html, member.html, admin.html) is served as static
| files from the /public directory. These routes simply ensure the root
| and friendly paths resolve correctly when visiting the Laravel server
| directly (e.g. http://localhost:8000/, /member, /admin).
|
| In production, it's recommended to serve these static files via Nginx
| directly, or from a separate static host / CDN, pointing the frontend's
| API calls at this Laravel backend's domain.
|
*/

Route::get('/', function () {
    return file_exists(public_path('index.html'))
        ? response()->file(public_path('index.html'))
        : response('TUCU API is running. Frontend not found in /public.', 200);
});

Route::get('/member', function () {
    return response()->file(public_path('member.html'));
});

Route::get('/admin', function () {
    return response()->file(public_path('admin.html'));
});

Route::get('/library', fn () => response()->file(public_path('library.html')));
Route::get('/library/books/{book}', fn () => response()->file(public_path('library.html')))->whereNumber('book');
Route::get('/library/books/{book}/read', fn () => response()->file(public_path('library.html')))->whereNumber('book');
