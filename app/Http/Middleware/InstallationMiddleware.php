<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Models\Setting;

class InstallationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Skip for Filament admin panel, assets and Livewire requests
        if ($request->is('super-admin') || $request->is('super-admin/*') || 
            $request->is('filament/*') || $request->is('vendor/livewire/*') ||
            $request->is('livewire/*')) {
            return $next($request);
        }

        if (! \Storage::disk('local')->has('database_created')) {
            return redirect('/installation');
        }

        if (\Storage::disk('local')->has('database_created')) {
            if (Setting::getSetting('profile_complete') !== 'COMPLETED') {
                return redirect('/installation');
            }
        }

        return $next($request);
    }
}
