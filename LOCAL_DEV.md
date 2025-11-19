# Инструкция по локальной разработке

## Быстрый старт

### 1. Проверьте .env для локальной разработки

Создайте или обновите `.env` файл:

```env
APP_NAME=Crater
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://crater.test

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=crater_saas
DB_USERNAME=crater
DB_PASSWORD=crater

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=cookie
SESSION_LIFETIME=120

MAIN_DOMAIN=crater.test
SESSION_DOMAIN=.crater.test
SANCTUM_STATEFUL_DOMAINS=crater.test,*.crater.test,localhost,127.0.0.1
```

### 2. Убедитесь, что домены добавлены в /etc/hosts

```bash
# macOS/Linux
sudo nano /etc/hosts

# Добавьте (если нет):
127.0.0.1 crater.test
127.0.0.1 *.crater.test

# Или одной командой:
echo "127.0.0.1 crater.test" | sudo tee -a /etc/hosts
echo "127.0.0.1 *.crater.test" | sudo tee -a /etc/hosts
```

**Важно:** После добавления поддоменов тенантов в `/etc/hosts` они будут работать автоматически благодаря wildcard записи.

### 3. Запустите Docker контейнеры

```bash
docker compose up -d --build
```

### 4. Установите зависимости и настройте Laravel

```bash
# Composer зависимости
docker compose exec app composer install

# Сгенерируйте ключ приложения (если нет)
docker compose exec app php artisan key:generate

# Запустите миграции для центральной БД
docker compose exec app php artisan migrate --force

# Очистите кеш
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
```

### 5. Создайте первого супер-админа (Filament)

```bash
docker compose exec app php artisan make:filament-user
# Введите email и пароль
```

### 6. Соберите фронтенд (если нужно)

```bash
docker compose exec app npm install
docker compose exec app npm run build
```

## Доступ к приложению

- **Центральная админка (Filament):** http://crater.test/super-admin/login
- **Тенант админка:** http://test.crater.test/admin/login (после создания тенанта `test`)
- **База данных:** localhost:54320
  - Пользователь: `crater`
  - Пароль: `crater`
  - БД: `crater_saas`

## Работа с Git

### Разработка новой фичи

```bash
git checkout dev
# Делайте изменения...
git add .
git commit -m "feature: описание"
git push origin dev
# → Автоматический деплой на dev сервер
```

### Мердж в продакшн

```bash
# Через Pull Request: dev → master в GitHub
# После мерджа → автодеплой на продакшн
```

## Создание и работа с тенантами локально

### 1. Создание тенанта через админку

1. Зайдите в админку: http://crater.test/super-admin/login
2. Перейдите: **Тенанты** → **Создать**
3. Заполните форму:
   - **ID**: `test` (уникальный идентификатор)
   - **Название**: `Test Company`
   - **Имя владельца**: `John Doe`
   - **Email владельца**: `john@example.com`
   - **Пароль владельца**: `password123`
4. Нажмите **Создать**

**После создания:**
- Автоматически создастся поддомен: `test.crater.test`
- Автоматически создастся схема PostgreSQL: `tenanttest`
- Автоматически выполнятся миграции и создастся пользователь

### 2. Доступ к тенанту

1. **Добавьте поддомен в /etc/hosts (если еще нет):**
   ```bash
   echo "127.0.0.1 test.crater.test" | sudo tee -a /etc/hosts
   ```

2. **Откройте в браузере:**
   - URL: http://test.crater.test/login
   - Email: `john@example.com`
   - Пароль: `password123`

### 3. Создание тенанта через командную строку

```bash
# Запустите tinker
docker compose exec app php artisan tinker

# В tinker создайте тенанта:
$tenant = \App\Models\Tenant::create([
    'id' => 'demo',
    'name' => 'Demo Company',
    'owner_name' => 'Jane Doe',
    'owner_email' => 'jane@example.com',
    'owner_password' => 'password123',
]);

# Инициализируйте тенанта (создание схемы, миграции, пользователя)
\App\Jobs\InitializeTenantJob::dispatchSync($tenant);
```

**Или через Artisan команду:**

```bash
# 1. Создайте тенанта через tinker
docker compose exec app php artisan tinker
# В tinker:
\App\Models\Tenant::create([
    'id' => 'demo',
    'name' => 'Demo Company',
    'owner_name' => 'Jane Doe',
    'owner_email' => 'jane@example.com',
    'owner_password' => 'password123',
]);

# 2. Инициализируйте тенанта
docker compose exec app php artisan tenant:initialize demo
```

### 4. Работа с данными тенанта

#### Просмотр всех тенантов:
```bash
docker compose exec app php artisan tinker
# В tinker:
\App\Models\Tenant::all();
```

#### Работа с данными конкретного тенанта:
```bash
docker compose exec app php artisan tinker

# В tinker:
$tenant = \App\Models\Tenant::find('test');

# Инициализируйте контекст тенанта
tenancy()->initialize($tenant);

# Теперь все запросы идут в схему тенанта
\Crater\Models\User::all(); // Пользователи тенанта
\Crater\Models\Company::all(); // Компании тенанта
\Crater\Models\Customer::count(); // Количество клиентов

# Закройте контекст тенанта
tenancy()->end();
```

#### Прямой доступ к схеме через psql:
```bash
docker compose exec db psql -U crater -d crater_saas

# В psql:
\dn  # Список всех схем
\dt tenanttest.*  # Таблицы в схеме tenanttest
SELECT * FROM tenanttest.users;  # Пользователи тенанта
SELECT * FROM tenanttest.companies;  # Компании тенанта
```

### 5. Миграции для тенантов

#### Создание новой миграции:
```bash
docker compose exec app php artisan make:migration add_field_to_table --path=database/migrations/tenant
```

#### Применение миграций для всех тенантов:
```bash
docker compose exec app php artisan tenants:migrate
```

#### Применение миграций для конкретного тенанта:
```bash
docker compose exec app php artisan tenants:migrate --tenants=test
```

### 6. Сидирование данных для тенантов

#### Для всех тенантов:
```bash
docker compose exec app php artisan tenants:seed
```

#### Для конкретного тенанта:
```bash
docker compose exec app php artisan tenants:seed --tenants=test
```

### 7. Список всех тенантов
```bash
docker compose exec app php artisan tenants:list
```

### 8. Удаление тенанта
```bash
docker compose exec app php artisan tinker

# В tinker:
$tenant = \App\Models\Tenant::find('test');
$tenant->delete(); // Удалит схему и все данные автоматически
```

## Полезные команды

### Docker

```bash
# Остановить/запустить
docker compose down
docker compose up -d

# Пересобрать контейнеры
docker compose up -d --build

# Логи
docker compose logs -f app
docker compose logs -f db

# Войти в контейнер
docker compose exec app bash
docker compose exec db psql -U crater -d crater_saas
```

### Laravel

```bash
# Очистка всех кешей
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear

# Статус миграций
docker compose exec app php artisan migrate:status

# Tinker (интерактивная консоль)
docker compose exec app php artisan tinker

# Список всех Artisan команд
docker compose exec app php artisan list
```

### База данных

```bash
# Подключение к PostgreSQL
docker compose exec db psql -U crater -d crater_saas

# Просмотр всех схем
docker compose exec db psql -U crater -d crater_saas -c "\dn"

# Просмотр таблиц в схеме тенанта
docker compose exec db psql -U crater -d crater_saas -c "\dt tenanttest.*"

# Просмотр пользователей тенанта
docker compose exec db psql -U crater -d crater_saas -c "SELECT * FROM tenanttest.users;"
```

## Структура локальной разработки

### Файловая структура

```
/var/www (внутри Docker контейнера)
├── database/
│   ├── migrations/          # Миграции для центральной БД
│   └── migrations/tenant/   # Миграции для схем тенантов
├── storage/
│   └── tenant{id}/         # Файлы тенантов (изолированные)
└── bootstrap/cache/        # Кеш конфигурации
```

### Структура базы данных

**Центральная БД (`crater_saas`):**
- Схема `public`:
  - `tenants` - список всех тенантов
  - `domains` - домены тенантов
  - `admin_users` - пользователи Filament админки
  - `cache` - кеш центрального домена

- Схемы тенантов:
  - `tenanttest` - данные тенанта `test`
  - `tenantdemo` - данные тенанта `demo`
  - И т.д.

## Типичный workflow локальной разработки

1. **Запустите проект:**
   ```bash
   docker compose up -d
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan make:filament-user
   ```

2. **Создайте тестового тенанта:**
   - Зайдите: http://crater.test/super-admin/login
   - Создайте тенанта через админку

3. **Тестируйте на тенанте:**
   - Откройте: http://test.crater.test/login
   - Войдите и протестируйте функционал

4. **Делайте изменения в коде:**
   ```bash
   # Редактируйте файлы локально
   # Изменения автоматически синхронизируются через Docker volumes
   ```

5. **Тестируйте изменения:**
   - Обновите страницу в браузере
   - Проверьте логи: `docker compose logs -f app`

6. **Пушьте в dev:**
   ```bash
   git checkout dev
   git add .
   git commit -m "feature: ..."
   git push origin dev
   ```

## Частые проблемы

### 1. Проблемы с /etc/hosts на macOS

Wildcard `*.crater.test` может не работать. Добавьте каждый поддомен отдельно:

```bash
127.0.0.1 crater.test
127.0.0.1 test.crater.test
127.0.0.1 demo.crater.test
# И т.д.
```

### 2. Ошибка "Tenant could not be identified"

Проверьте, что:
- Поддомен добавлен в `/etc/hosts`
- В `.env` правильно указан `MAIN_DOMAIN=crater.test`
- Nginx контейнер запущен: `docker compose ps`

### 3. Ошибка подключения к БД

Проверьте, что:
- Контейнер `db` запущен: `docker compose ps`
- В `.env` правильно указан `DB_HOST=db` (имя контейнера, не localhost)

### 4. Кеш не очищается

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose restart app  # Перезапустите контейнер
```
