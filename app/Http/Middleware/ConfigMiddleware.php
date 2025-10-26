<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Models\FileDisk;

class ConfigMiddleware
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

        if (\Storage::disk('local')->has('database_created')) {
            if ($request->has('file_disk_id')) {
                $file_disk = FileDisk::find($request->file_disk_id);
            } else {
                $file_disk = FileDisk::whereSetAsDefault(true)->first();
            }

            if ($file_disk) {
                $file_disk->setConfig();
            }
        }

        return $next($request);
    }
}
