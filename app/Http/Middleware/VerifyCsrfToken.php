<?php

namespace Crater\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        // Web routes
        'login',
        'auth/logout',
        
        // API routes (для stateful SPA)
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/password/email',
        'api/v1/auth/reset/password',
        
        // Customer auth routes
        'api/v1/*/customer/auth/password/email',
        'api/v1/*/customer/auth/reset/password',
    ];

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = $request->session()->token();

        $match = hash_equals($sessionToken, $token);

        // Логирование для диагностики CSRF проблем на production
        if (!$match && config('app.debug', false) || env('LOG_CSRF_FAILURES', false)) {
            \Log::warning('CSRF token mismatch', [
                'uri' => $request->getUri(),
                'method' => $request->getMethod(),
                'has_token' => !empty($token),
                'token_length' => $token ? strlen($token) : 0,
                'has_session_token' => !empty($sessionToken),
                'session_token_length' => $sessionToken ? strlen($sessionToken) : 0,
                'session_id' => $request->session()->getId(),
                'has_x_xsrf_token' => $request->hasHeader('X-XSRF-TOKEN'),
                'has_x_csrf_token' => $request->hasHeader('X-CSRF-TOKEN'),
                'has_token_input' => $request->has('_token'),
                'host' => $request->getHost(),
                'referer' => $request->header('referer'),
                'origin' => $request->header('origin'),
            ]);
        }

        return $match;
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function getTokenFromRequest($request)
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        // Laravel автоматически обрабатывает X-XSRF-TOKEN заголовок
        // Если XSRF-TOKEN cookie зашифрован, Laravel расшифрует его с помощью CookieValuePrefix
        // Если XSRF-TOKEN cookie не зашифрован (в $except), используем значение как есть
        if (! $token && $header = $request->header('X-XSRF-TOKEN')) {
            try {
                // Стандартная логика Laravel: пытаемся расшифровать с CookieValuePrefix
                $token = \Illuminate\Cookie\CookieValuePrefix::remove(
                    $this->encrypter->decrypt($header, static::serialized())
                );
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Если расшифровка не удалась, возможно cookie не был зашифрован
                // Используем значение как есть (для незашифрованных cookie)
                $token = $header;
            }
        }

        return $token;
    }
}
