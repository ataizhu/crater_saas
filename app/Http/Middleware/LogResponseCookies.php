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
                'value' => substr($cookie->getValue(), 0, 20) . '...',
                'domain' => $cookie->getDomain(),
                'path' => $cookie->getPath(),
                'secure' => $cookie->isSecure(),
                'httpOnly' => $cookie->isHttpOnly(),
                'sameSite' => $cookie->getSameSite(),
            ];
        }
        
        // Также проверяем Set-Cookie заголовки напрямую
        $setCookieHeaders = $response->headers->all('Set-Cookie');
        
        // Проверяем, есть ли crater_session в cookie
        $hasSessionCookie = false;
        $sessionCookieName = config('session.cookie');
        foreach ($cookies as $cookie) {
            if ($cookie['name'] === $sessionCookieName) {
                $hasSessionCookie = true;
                break;
            }
        }
        
        // Проверяем, есть ли crater_session в Set-Cookie заголовках
        $hasSessionCookieInHeaders = false;
        foreach ($setCookieHeaders as $header) {
            if (strpos($header, $sessionCookieName . '=') === 0) {
                $hasSessionCookieInHeaders = true;
                break;
            }
        }
        
        // Безопасно проверяем статус сессии
        $sessionId = null;
        $sessionIsDirty = false;
        if ($request->hasSession() && $request->session()->isStarted()) {
            try {
                $sessionId = $request->session()->getId();
                // isDirty() может не существовать в некоторых версиях Laravel
                if (method_exists($request->session(), 'isDirty')) {
                    $sessionIsDirty = $request->session()->isDirty();
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки при проверке сессии
            }
        }
        
        \Log::info('LogResponseCookies: Response cookies', [
            'cookies' => $cookies,
            'cookie_count' => count($cookies),
            'has_session_cookie' => $hasSessionCookie,
            'has_session_cookie_in_headers' => $hasSessionCookieInHeaders,
            'set_cookie_headers_count' => count($setCookieHeaders),
            'set_cookie_headers' => array_map(function($header) {
                return substr($header, 0, 100) . '...';
            }, $setCookieHeaders),
            'request_uri' => $request->getRequestUri(),
            'session_cookie_name' => $sessionCookieName,
            'session_id' => $sessionId,
            'session_is_dirty' => $sessionIsDirty,
        ]);
        
        return $response;
    }
}
