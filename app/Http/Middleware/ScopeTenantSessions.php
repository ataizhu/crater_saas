<?php

namespace Crater\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ScopeTenantSessions
{
    public static $tenantIdKey = '_tenant_id';

    public function handle(Request $request, Closure $next)
    {
        if (! tenancy()->initialized) {
            // Если tenancy не инициализирован - пропускаем
            return $next($request);
        }

        $currentTenantId = tenant()->getTenantKey();
        $sessionTenantId = $request->session()->get(static::$tenantIdKey);

        if ($sessionTenantId && $sessionTenantId !== $currentTenantId) {
            // Если в сессии другой тенант - только очищаем tenant_id
            // НЕ очищаем всю сессию, чтобы не потерять авторизацию
            $request->session()->forget(static::$tenantIdKey);
            $request->session()->forget('login_web_*'); // Очищаем старую авторизацию
        }

        // Устанавливаем текущий tenant_id в сессию
        $request->session()->put(static::$tenantIdKey, $currentTenantId);

        return $next($request);
    }
}

