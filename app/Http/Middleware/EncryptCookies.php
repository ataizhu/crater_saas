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
        
        // Удаляем старые cookie с именами = session_id (они не нужны)
        // Эти cookie могли быть созданы ранее из-за багов
        $cookiesToRemove = [];
        foreach ($cookiesBefore as $cookieName) {
            // Если это не стандартные cookie и не XSRF-TOKEN, и имя похоже на session_id (40 символов)
            if ($cookieName !== $sessionCookieName && 
                $cookieName !== 'XSRF-TOKEN' && 
                strlen($cookieName) === 40) {
                $cookiesToRemove[] = $cookieName;
                $request->cookies->remove($cookieName);
            }
        }
        
        if (!empty($cookiesToRemove)) {
            \Log::warning('EncryptCookies: Removing old session_id cookies', [
                'removed_cookies' => $cookiesToRemove,
                'session_cookie_name' => $sessionCookieName,
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
        $sessionCookieName = config('session.cookie');
        $cookiesBefore = [];
        $cookiesToRemove = [];
        
        // Логируем cookie ДО обработки
        foreach ($response->headers->getCookies() as $cookie) {
            $cookieName = $cookie->getName();
            $cookiesBefore[] = $cookieName;
            
            // Если это не стандартные cookie и не XSRF-TOKEN, и имя похоже на session_id (40 символов)
            // И имя НЕ равно session_cookie_name (crater_session)
            if ($cookieName !== $sessionCookieName && 
                $cookieName !== 'XSRF-TOKEN' && 
                strlen($cookieName) === 40 &&
                preg_match('/^[a-zA-Z0-9]{40}$/', $cookieName)) {
                $cookiesToRemove[] = $cookieName;
            }
        }
        
        // Удаляем старые cookie из ответа
        foreach ($cookiesToRemove as $cookieName) {
            $response->headers->removeCookie($cookieName);
        }
        
        if (!empty($cookiesToRemove)) {
            \Log::warning('EncryptCookies: Removing old session_id cookies from response', [
                'removed_cookies' => $cookiesToRemove,
                'session_cookie_name' => $sessionCookieName,
            ]);
        }
        
        $result = parent::encrypt($response);
        
        return $result;
    }
}
