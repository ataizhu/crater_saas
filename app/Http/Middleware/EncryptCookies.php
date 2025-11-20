<?php

namespace Crater\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * Indicates if cookies should be serialized.
     *
     * @var bool
     */
    protected static $serialize = false;

    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        'XSRF-TOKEN', // Laravel автоматически расшифровывает этот cookie для CSRF
        // НЕ добавляем crater_session - он должен шифроваться для безопасности
    ];
    
    /**
     * Decrypt the cookies on the request.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function decrypt($request)
    {
        $cookiesBefore = array_keys($request->cookies->all());
        $sessionCookieName = config('session.cookie');
        
        // Оптимизация: проверяем cookies только если их много (больше 4) или при POST/PUT/PATCH/DELETE
        $method = $request->getMethod();
        $shouldCheck = count($cookiesBefore) > 4 || in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
        
        if (!$shouldCheck) {
            return parent::decrypt($request);
        }
        
        // Список разрешенных cookie, которые должны оставаться
        $allowedCookies = [
            $sessionCookieName,
            'XSRF-TOKEN',
            'remember_web_59ba36addc2b2f9401580f014c7f58ea4e30989d', // Laravel remember token (пример)
        ];
        
        // Удаляем старые/недействительные cookie автоматически
        $cookiesToRemove = [];
        foreach ($cookiesBefore as $cookieName) {
            $shouldRemove = false;
            
            // 1. Удаляем cookie, которые похожи на session_id (40 символов, не разрешенные)
            if (strlen($cookieName) === 40 && !in_array($cookieName, $allowedCookies)) {
                $shouldRemove = true;
            }
            
            // 2. Удаляем cookie с именами, похожими на старые session cookies (32-40 символов)
            if (preg_match('/^[a-zA-Z0-9]{32,40}$/', $cookieName) && 
                !in_array($cookieName, $allowedCookies) &&
                strpos($cookieName, 'remember_') !== 0) {
                $shouldRemove = true;
            }
            
            // Пропускаем текущий session cookie
            if ($cookieName === $sessionCookieName) {
                continue;
            }
            
            if ($shouldRemove) {
                $cookiesToRemove[] = $cookieName;
                $request->cookies->remove($cookieName);
            }
        }
        
        if (!empty($cookiesToRemove)) {
            \Log::info('EncryptCookies: Automatically removing invalid/old cookies', [
                'removed_cookies' => $cookiesToRemove,
                'session_cookie_name' => $sessionCookieName,
                'total_cookies_before' => count($cookiesBefore),
                'total_cookies_after' => count($cookiesBefore) - count($cookiesToRemove),
            ]);
        }
        
        $result = parent::decrypt($request);
        
        return $result;
    }
    
    /**
     * Encrypt the cookies on an outgoing response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function encrypt($response)
    {
        // Оптимизация: проверяем cookies в ответе только если их много или это POST/PUT/PATCH/DELETE
        $cookiesInResponse = $response->headers->getCookies();
        if (count($cookiesInResponse) <= 2) {
            // Если cookies мало (обычно только crater_session и XSRF-TOKEN), пропускаем проверку
            return parent::encrypt($response);
        }
        
        $sessionCookieName = config('session.cookie');
        $allowedCookies = [
            $sessionCookieName,
            'XSRF-TOKEN',
        ];
        
        $cookiesToRemove = [];
        
        // Удаляем старые/недействительные cookie из ответа автоматически
        foreach ($cookiesInResponse as $cookie) {
            $cookieName = $cookie->getName();
            
            // Удаляем cookie, которые похожи на старые session cookies
            if (!in_array($cookieName, $allowedCookies) &&
                preg_match('/^[a-zA-Z0-9]{32,40}$/', $cookieName) &&
                strpos($cookieName, 'remember_') !== 0) {
                $cookiesToRemove[] = $cookieName;
                // Устанавливаем cookie с истекшим сроком для всех возможных доменов
                $domains = array_filter([
                    config('session.domain'),
                    '.' . parse_url(config('app.url'), PHP_URL_HOST),
                ]);
                foreach (array_unique($domains) as $domain) {
                    if ($domain) {
                        $response->headers->removeCookie($cookieName, '/', $domain);
                    }
                }
            }
        }
        
        if (!empty($cookiesToRemove)) {
            \Log::info('EncryptCookies: Automatically removing invalid cookies from response', [
                'removed_cookies' => $cookiesToRemove,
                'session_cookie_name' => $sessionCookieName,
            ]);
        }
        
        $result = parent::encrypt($response);
        
        return $result;
    }
}
