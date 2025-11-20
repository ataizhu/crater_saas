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
        
        // Также проверяем Set-Cookie заголовки напрямую
        $setCookieHeaders = $response->headers->get('Set-Cookie', []);
        if (!is_array($setCookieHeaders)) {
            $setCookieHeaders = [$setCookieHeaders];
        }
        
        \Log::info('LogResponseCookies: Response cookies', [
            'cookies' => $cookies,
            'cookie_count' => count($cookies),
            'set_cookie_headers' => $setCookieHeaders,
            'set_cookie_count' => count($setCookieHeaders),
            'request_uri' => $request->getRequestUri(),
            'session_cookie_name' => config('session.cookie'),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'session_is_dirty' => $request->hasSession() ? $request->session()->isDirty() : false,
        ]);
        
        return $response;
    }
}
