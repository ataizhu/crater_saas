<?php

declare(strict_types=1);

use Crater\Http\Middleware\ScopeTenantSessions;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// Web routes для тенантов
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    ScopeTenantSessions::class, // Auto-flush session when switching tenants
])->group(function () {
    require base_path('routes/web.php');
});

// API routes для тенантов
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    ScopeTenantSessions::class, // Auto-flush session when switching tenants
])->prefix('api')->group(function () {
    require base_path('routes/api.php');
});
