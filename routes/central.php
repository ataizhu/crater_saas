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

// Redirects
Route::get('/', function () {
    return redirect('/super-admin/login');
});

Route::get('/super-admin', function () {
    return redirect('/super-admin/login');
});

