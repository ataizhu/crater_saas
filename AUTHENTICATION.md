# Документация по аутентификации и CSRF

## Обзор

Проект использует **Laravel Sanctum** для stateful аутентификации SPA (Single Page Application). Это означает, что аутентификация происходит через сессии и cookie, а не через токены API.

## Архитектура аутентификации

### Middleware Stack

Порядок middleware критически важен для правильной работы аутентификации:

**Группа `web`** (для стандартных веб-запросов):
```php
1. EncryptCookies
2. AddQueuedCookiesToResponse
3. StartSession
4. ShareErrorsFromSession
5. VerifyCsrfToken
6. SubstituteBindings
```

**Группа `api`** (для API запросов от SPA):
```php
1. EnsureFrontendRequestsAreStateful (ВАЖНО: первый!)
2. throttle:180,1
3. SubstituteBindings
```

`EnsureFrontendRequestsAreStateful` **сам добавляет** нужные middleware для stateful запросов:
- `EncryptCookies`
- `AddQueuedCookiesToResponse`
- `StartSession`
- `VerifyCsrfToken`

### Как работает EnsureFrontendRequestsAreStateful

1. Проверяет, является ли запрос stateful, проверяя `referer` или `origin` заголовок
2. Сопоставляет домен с `SANCTUM_STATEFUL_DOMAINS`
3. Если запрос stateful, добавляет middleware через внутренний pipeline
4. Устанавливает атрибут `sanctum` в запросе

## Настройка

### 1. Конфигурация Sanctum

Файл `config/sanctum.php`:

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', ...)),
```

### 2. Переменные окружения

Файл `.env`:

```env
# Session
SESSION_DRIVER=file
SESSION_DOMAIN=.crater.test  # Для локальной разработки
SESSION_COOKIE=crater_session

# Sanctum
SANCTUM_STATEFUL_DOMAINS=crater.test,*.crater.test,localhost,127.0.0.1

# Для продакшена:
# SESSION_DOMAIN=.crater.billing.mycloud.kg
# SANCTUM_STATEFUL_DOMAINS=crater.billing.mycloud.kg,*.crater.billing.mycloud.kg
```

### 3. CORS

Файл `config/cors.php`:

```php
'supports_credentials' => true,  // Обязательно для cookie
```

### 4. Axios на фронтенде

Файл `resources/scripts/plugins/axios.js`:

```javascript
axios.defaults.withCredentials = true;  // Обязательно для отправки cookie

// Автоматически добавляет X-XSRF-TOKEN заголовок
axios.interceptors.request.use(function (config) {
  const method = config.method?.toUpperCase();
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    const csrfToken = getCookie('XSRF-TOKEN');
    if (csrfToken) {
      config.headers.common['X-XSRF-TOKEN'] = csrfToken;
    }
  }
  return config;
});
```

## CSRF защита

### Как работает

1. **Cookie `XSRF-TOKEN`**: Laravel автоматически устанавливает этот cookie при первом запросе
2. **Не зашифрован**: `XSRF-TOKEN` добавлен в `$except` в `EncryptCookies`, чтобы JavaScript мог его прочитать
3. **Заголовок `X-XSRF-TOKEN`**: Axios автоматически читает cookie и отправляет в заголовке

### Кастомный VerifyCsrfToken

Файл `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected function getTokenFromRequest($request)
{
    $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

    // Обработка X-XSRF-TOKEN заголовка
    if (! $token && $header = $request->header('X-XSRF-TOKEN')) {
        try {
            // Пытаемся расшифровать (если cookie был зашифрован)
            $token = \Illuminate\Cookie\CookieValuePrefix::remove(
                $this->encrypter->decrypt($header, static::serialized())
            );
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Если расшифровка не удалась, используем как есть
            // (для незашифрованных cookie)
            $token = $header;
        }
    }

    return $token;
}
```

### Исключения из CSRF

Роуты, которые не требуют CSRF токена (в `$except`):
- `/login` - страница логина
- `/auth/logout` - выход
- `/api/v1/auth/login` - API логин
- `/api/v1/auth/logout` - API выход
- `/api/v1/auth/password/email` - восстановление пароля
- `/api/v1/auth/reset/password` - сброс пароля
- `/api/v1/*/customer/auth/password/email` - восстановление пароля клиента
- `/api/v1/*/customer/auth/reset/password` - сброс пароля клиента

## Сессии

### Драйвер сессий

Используется `file` драйвер (не `cookie`), чтобы Laravel мог устанавливать отдельный cookie для сессии.

### Cookie сессии

- **Имя**: `crater_session` (настраивается в `config/session.php`)
- **Домен**: `.crater.test` (для локальной разработки)
- **HttpOnly**: `true` (для безопасности)
- **SameSite**: `lax` (для корректной работы с поддоменами)

### Важные моменты

1. **`SESSION_DRIVER=file`**: Используется файловый драйвер, а не cookie
2. **`EncryptCookies`**: Сессионный cookie **зашифрован** (не в `$except`)
3. **`XSRF-TOKEN`**: Cookie **не зашифрован** (в `$except`) для чтения JavaScript

## Процесс логина

### 1. Получение CSRF cookie

Фронтенд делает GET запрос к `/sanctum/csrf-cookie`:
- Устанавливается cookie `XSRF-TOKEN`
- Устанавливается cookie `crater_session` (если еще не установлен)

### 2. Отправка логина

Фронтенд делает POST запрос к `/login` (или `/api/v1/auth/login`):
- Axios автоматически читает `XSRF-TOKEN` из cookie
- Отправляет его в заголовке `X-XSRF-TOKEN`
- Отправляет учетные данные

### 3. Обработка логина

Backend (`LoginController`):
- Проверяет CSRF токен
- Проверяет учетные данные
- Вызывает `Auth::login($user)` - это автоматически:
  - Помечает сессию как dirty
  - Сохраняет ID пользователя в сессии
  - Регенерирует сессию (если нужно)

### 4. Установка cookie

`StartSession` middleware:
- Видит, что сессия изменена (dirty)
- Устанавливает cookie `crater_session` в ответе
- Сохраняет сессию в файл

## Мультитенантность

### Сессии для тенантов

Каждый тенант имеет свою сессию. Middleware `ScopeTenantSessions`:
- Проверяет, совпадает ли `tenant_id` в сессии с текущим тенантом
- Если не совпадает, очищает старую авторизацию
- Устанавливает новый `tenant_id` в сессии

### Изоляция

- Центральный домен (`crater.test`): сессия для админки Filament
- Тенантские поддомены (`test.crater.test`): отдельная сессия для каждого тенанта

## Решенные проблемы

### Проблема 1: 401 Unauthorized после логина

**Причина**: `EnsureFrontendRequestsAreStateful` был не первым в группе `api`, или дублировались middleware.

**Решение**: Убрать дублирующие middleware из группы `api`, оставить только `EnsureFrontendRequestsAreStateful` первым.

### Проблема 2: CSRF token mismatch

**Причина**: `XSRF-TOKEN` cookie был зашифрован, JavaScript не мог его прочитать.

**Решение**: 
1. Добавить `XSRF-TOKEN` в `$except` в `EncryptCookies`
2. Исправить логику в `VerifyCsrfToken::getTokenFromRequest()` для обработки незашифрованных cookie

### Проблема 3: Session cookie не устанавливается

**Причина**: `SESSION_DRIVER=cookie` не устанавливает отдельный cookie для сессии.

**Решение**: Изменить на `SESSION_DRIVER=file`.

### Проблема 4: 400 Bad Request - Cookie Too Large

**Причина**: Накопление старых cookie в браузере.

**Решение**: 
1. Увеличить Nginx буферы в `docker-compose/nginx/nginx.conf`
2. Добавить логику удаления старых session_id-подобных cookie в `EncryptCookies`

## Отладка

### Проверка сессии

```bash
docker compose exec app php artisan tinker
>>> session()->getId()
>>> session()->isStarted()
>>> Auth::check()
```

### Проверка CSRF токена

```bash
# В браузере DevTools → Application → Cookies
# Должен быть cookie XSRF-TOKEN

# В Network tab → Request Headers
# Должен быть заголовок X-XSRF-TOKEN
```

### Логи

```bash
# Просмотр логов Laravel
docker compose logs -f app

# Просмотр логов Nginx
docker compose logs -f nginx

# Просмотр логов приложения
docker compose exec app tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

## Ключевые файлы

- `app/Http/Kernel.php` - порядок middleware
- `app/Http/Middleware/VerifyCsrfToken.php` - логика CSRF
- `app/Http/Middleware/EncryptCookies.php` - шифрование cookie
- `config/sanctum.php` - конфигурация Sanctum
- `config/session.php` - конфигурация сессий
- `config/cors.php` - конфигурация CORS
- `resources/scripts/plugins/axios.js` - настройка Axios

## Частые ошибки

### 1. "CSRF token mismatch"

**Решение**:
- Убедитесь, что `XSRF-TOKEN` в `$except` в `EncryptCookies`
- Проверьте, что Axios отправляет заголовок `X-XSRF-TOKEN`
- Проверьте, что `withCredentials: true` установлен в Axios

### 2. "401 Unauthorized"

**Решение**:
- Убедитесь, что `EnsureFrontendRequestsAreStateful` первый в группе `api`
- Проверьте, что домен в `SANCTUM_STATEFUL_DOMAINS`
- Проверьте, что сессия устанавливается (cookie `crater_session`)

### 3. "400 Bad Request - Cookie Too Large"

**Решение**:
- Очистите cookie в браузере
- Увеличьте Nginx буферы в `docker-compose/nginx/nginx.conf`
- Проверьте, нет ли дублирующихся cookie

