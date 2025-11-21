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
        
        // Livewire routes (Filament использует Livewire)
        'livewire/*',
        'admin/livewire/*',
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
        if (!$match && (config('app.debug', false) || env('LOG_CSRF_FAILURES', false))) {
            $xXsrfHeader = $request->header('X-XSRF-TOKEN');
            $xCsrfHeader = $request->header('X-CSRF-TOKEN');
            $tokenInput = $request->input('_token');
            
            \Log::warning('CSRF token mismatch', [
                'uri' => $request->getUri(),
                'method' => $request->getMethod(),
                'has_token' => !empty($token),
                'token_preview' => $token ? substr($token, 0, 10) . '...' . substr($token, -10) : null,
                'token_length' => $token ? strlen($token) : 0,
                'has_session_token' => !empty($sessionToken),
                'session_token_preview' => $sessionToken ? substr($sessionToken, 0, 10) . '...' . substr($sessionToken, -10) : null,
                'session_token_length' => $sessionToken ? strlen($sessionToken) : 0,
                'session_id' => $request->session()->getId(),
                'has_x_xsrf_token' => $request->hasHeader('X-XSRF-TOKEN'),
                'x_xsrf_token_preview' => $xXsrfHeader ? substr($xXsrfHeader, 0, 20) . '...' : null,
                'has_x_csrf_token' => $request->hasHeader('X-CSRF-TOKEN'),
                'x_csrf_token_preview' => $xCsrfHeader ? substr($xCsrfHeader, 0, 20) . '...' : null,
                'has_token_input' => $request->has('_token'),
                'token_input_preview' => $tokenInput ? substr($tokenInput, 0, 20) . '...' : null,
                'host' => $request->getHost(),
                'referer' => $request->header('referer'),
                'origin' => $request->header('origin'),
                'cookies' => array_keys($request->cookies->all()),
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
        // Используем стандартный метод Laravel для получения токена
        // Он автоматически обрабатывает X-XSRF-TOKEN, X-CSRF-TOKEN и _token
        
        // Laravel автоматически обрабатывает:
        // 1. X-XSRF-TOKEN заголовок (читает из cookie XSRF-TOKEN)
        // 2. X-CSRF-TOKEN заголовок
        // 3. _token input
        
        // Вызываем родительский метод, который правильно обрабатывает все случаи
        $token = parent::getTokenFromRequest($request);
        
        // Если родительский метод не нашел токен, пробуем альтернативные способы
        if (!$token) {
            // Проверяем заголовки напрямую (без расшифровки, для отладки)
            if ($header = $request->header('X-XSRF-TOKEN')) {
                $token = $header;
            } elseif ($header = $request->header('X-CSRF-TOKEN')) {
                $token = $header;
            } elseif ($input = $request->input('_token')) {
                $token = $input;
            }
        }
        
        return $token;
    }
}
