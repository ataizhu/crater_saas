<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Domain Routes
|--------------------------------------------------------------------------
|
| Routes for the main/central domain where Filament admin panel is located.
| Tenant routes are handled separately in routes/tenant.php
|
*/

// Filament routes registered automatically via FilamentServiceProvider
// Filament base path is 'admin', so login is at /admin/login
// We redirect /login to /admin/login for user convenience

// Redirect /login to /admin/login for Filament
// Note: This route may be overridden by routes/web.php for tenants
// But on central domain, PreventAccessFromCentralDomains middleware should block tenant routes
Route::get('/login', function () {
    return redirect('/admin/login', 301);
})->middleware('web')->name('filament.login.redirect');

// Redirects
Route::get('/', function () {
    return redirect('/login');
});

