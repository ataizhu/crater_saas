<?php

namespace Crater\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogSanctumState
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $referer = $request->headers->get('referer');
        $origin = $request->headers->get('origin');
        $statefulDomains = config('sanctum.stateful', []);
        
        // Проверяем, как EnsureFrontendRequestsAreStateful определяет stateful запрос
        $isStatefulBySanctum = \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::fromFrontend($request);
        
        \Log::info('SanctumState: Checking request', [
            'host' => $host,
            'referer' => $referer,
            'origin' => $origin,
            'stateful_domains_config' => $statefulDomains,
            'is_stateful_by_sanctum' => $isStatefulBySanctum,
            'request_uri' => $request->getRequestUri(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'session_started' => $request->hasSession() && $request->session()->isStarted(),
            'authenticated_web' => \Auth::guard('web')->check(),
            'authenticated_sanctum' => \Auth::guard('sanctum')->check(),
            'sanctum_attribute' => $request->attributes->get('sanctum', false),
        ]);
        
        return $next($request);
    }
}

