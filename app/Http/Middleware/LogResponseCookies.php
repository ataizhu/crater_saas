<?php

namespace Crater\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LogResponseCookies
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
        $response = $next($request);
        
        // Логируем cookie, которые будут отправлены в ответе
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[] = [
                'name' => $cookie->getName(),
                'domain' => $cookie->getDomain(),
                'path' => $cookie->getPath(),
                'secure' => $cookie->isSecure(),
                'httpOnly' => $cookie->isHttpOnly(),
                'sameSite' => $cookie->getSameSite(),
            ];
        }
        
        \Log::info('LogResponseCookies: Response cookies', [
            'cookies' => $cookies,
            'cookie_count' => count($cookies),
            'request_uri' => $request->getRequestUri(),
            'session_cookie_name' => config('session.cookie'),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
        ]);
        
        return $response;
    }
}
