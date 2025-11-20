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
        $statefulDomains = config('sanctum.stateful', []);
        $isStateful = false;
        
        foreach ($statefulDomains as $domain) {
            if (Str::is($domain, $host)) {
                $isStateful = true;
                break;
            }
        }
        
        \Log::info('SanctumState: Checking request', [
            'host' => $host,
            'stateful_domains_config' => $statefulDomains,
            'is_stateful_request' => $isStateful,
            'request_uri' => $request->getRequestUri(),
            'session_id' => $request->session()->getId(),
            'session_exists' => $request->session()->exists(),
            'authenticated_web' => \Auth::guard('web')->check(),
            'authenticated_sanctum' => \Auth::guard('sanctum')->check(),
        ]);
        
        return $next($request);
    }
}

