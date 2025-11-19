# Руководство по разработке

## Архитектура мультитенантной системы

### Структура баз данных

Система использует **PostgreSQL схемы (schemas)** для изоляции тенантов:

1. **Центральная БД** (`crater_saas` или `crater_saas_dev`):
   - `tenants` - список всех тенантов
   - `domains` - домены/поддомены для каждого тенанта
   - `admin_users` - пользователи Filament админки (супер-админы)
   - `cache` - кеш для центрального домена
   - `personal_access_tokens` - токены для API

2. **Схемы тенантов** (например, `tenanttest`, `tenantabc`):
   - Каждый тенант имеет свою схему: `tenant{tenant_id}`
   - В схеме создаются все таблицы из `database/migrations/tenant/`
   - Полностью изолированные данные: `users`, `companies`, `invoices`, и т.д.

### Домены

- **Центральный домен** (админка Filament):
  - Production: `crater.billing.mycloud.kg/super-admin`
  - Dev: `dev.crater.billing.mycloud.kg/super-admin`

- **Тенантские поддомены**:
  - Production: `{tenant_id}.crater.billing.mycloud.kg`
  - Dev: `{tenant_id}.dev.crater.billing.mycloud.kg`
  - Пример: если `tenant_id = "test"`, то поддомен: `test.crater.billing.mycloud.kg`

## Процесс разработки

### 1. Создание нового тенанта

#### Вариант A: Через Filament админку (рекомендуется)

1. Зайдите на центральный домен:
   - Production: `http://crater.billing.mycloud.kg/super-admin/login`
   - Dev: `http://dev.crater.billing.mycloud.kg/super-admin/login`

2. Создайте супер-админа (если еще нет):
   ```bash
   # На сервере
   docker compose exec -u root app php artisan make:filament-user
   # Или для dev:
   docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan make:filament-user
   ```

3. В админке перейдите: **Тенанты** → **Создать**
   - `ID`: уникальный идентификатор тенанта (например, `client1`, `test`, `demo`)
   - `Название`: название компании/клиента
   - `Имя владельца`: имя пользователя-владельца
   - `Email владельца`: email для входа
   - `Пароль владельца`: пароль для входа (минимум 8 символов)

4. После сохранения:
   - Автоматически создастся поддомен: `{tenant_id}.{main_domain}`
   - Автоматически создастся схема PostgreSQL: `tenant{tenant_id}`
   - Автоматически выполнятся миграции в схеме тенанта
   - Автоматически создастся компания и owner пользователь
   - Автоматически заполнятся валюты и страны

#### Вариант B: Через командную строку

```bash
# На сервере (Production)
cd /var/www/crater
docker compose exec -u root app php artisan tinker

# В tinker:
$tenant = \App\Models\Tenant::create([
    'id' => 'test123',
    'name' => 'Test Company',
    'owner_name' => 'John Doe',
    'owner_email' => 'john@example.com',
    'owner_password' => 'password123',
]);

# Инициализация тенанта (создание схемы, миграции, пользователя)
\App\Jobs\InitializeTenantJob::dispatchSync($tenant);
```

#### Вариант C: Через Artisan команду

```bash
# 1. Создайте тенанта через tinker
docker compose exec -u root app php artisan tinker
# В tinker:
\App\Models\Tenant::create([
    'id' => 'test123',
    'name' => 'Test Company',
    'owner_name' => 'John Doe',
    'owner_email' => 'john@example.com',
    'owner_password' => 'password123',
]);

# 2. Инициализируйте тенанта
docker compose exec -u root app php artisan tenant:initialize test123
```

### 2. Доступ к тенанту

После создания тенанта:

1. **Поддомен создается автоматически:**
   - Production: `test123.crater.billing.mycloud.kg`
   - Dev: `test123.dev.crater.billing.mycloud.kg`

2. **DNS настройка:**
   - На сервере Nginx уже настроен wildcard: `*.crater.billing.mycloud.kg`
   - Если используете dev окружение, убедитесь что есть wildcard: `*.dev.crater.billing.mycloud.kg`

3. **Локальная разработка:**
   - Добавьте в `/etc/hosts`:
   ```bash
   127.0.0.1 test123.crater.test
   ```

4. **Вход в тенант:**
   - URL: `http://test123.crater.billing.mycloud.kg/login`
   - Email: `john@example.com` (тот что указали при создании)
   - Пароль: `password123` (тот что указали при создании)

### 3. Структура схемы тенанта

В схеме `tenant{id}` создаются следующие таблицы:
- `users` - пользователи тенанта
- `companies` - компании (обычно одна на тенант)
- `invoices` - счета
- `customers` - клиенты
- `items` - товары/услуги
- `currencies` - валюты
- `countries` - страны
- `abilities` - права доступа
- `roles` - роли
- `settings` - настройки
- И другие таблицы из `database/migrations/tenant/`

### 4. Работа с данными тенанта

#### Просмотр данных тенанта через tinker

```bash
# На сервере
docker compose exec -u root app php artisan tinker

# В tinker - работа с центральной БД:
$tenant = \App\Models\Tenant::find('test123');
$tenant->domains; // Поддомены тенанта

# Работа с данными тенанта:
tenancy()->initialize($tenant);
\Crater\Models\User::all(); // Пользователи тенанта
\Crater\Models\Company::all(); // Компании тенанта
tenancy()->end();
```

#### Прямой доступ к схеме тенанта через psql

```bash
# Production
docker compose exec db psql -U crater -d crater_saas

# Dev
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -d crater_saas_dev

# В psql:
\dn  # Список схем
\dt tenanttest.*  # Таблицы в схеме tenanttest
SELECT * FROM tenanttest.users;  # Пользователи тенанта
SELECT * FROM tenanttest.companies;  # Компании тенанта
```

### 5. Миграции для тенантов

#### Создание новой миграции для тенантов

```bash
# Создайте миграцию в папке tenant/
php artisan make:migration add_field_to_invoices_table --path=database/migrations/tenant
```

#### Применение миграций

**Для всех тенантов:**
```bash
# Production
docker compose exec -u root app php artisan tenants:migrate

# Dev
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan tenants:migrate
```

**Для конкретного тенанта:**
```bash
# Production
docker compose exec -u root app php artisan tenants:migrate --tenants=test123

# Dev
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan tenants:migrate --tenants=test123
```

**Вручную для конкретного тенанта:**
```bash
docker compose exec -u root app php artisan tinker
# В tinker:
tenancy()->initialize(\App\Models\Tenant::find('test123'));
\Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
tenancy()->end();
```

### 6. Сидирование данных для тенантов

#### Для всех тенантов:
```bash
# Production
docker compose exec -u root app php artisan tenants:seed

# Dev
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan tenants:seed
```

#### Для конкретного тенанта:
```bash
# Production
docker compose exec -u root app php artisan tenants:seed --tenants=test123

# Dev
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan tenants:seed --tenants=test123
```

### 7. Работа с несколькими окружениями

**Важно:** Тенанты в production и dev окружениях полностью изолированы:

- **Production тенанты:**
  - БД: `crater_saas`
  - Схемы: `tenant{id}` в `crater_saas`
  - Домены: `{id}.crater.billing.mycloud.kg`

- **Dev тенанты:**
  - БД: `crater_saas_dev`
  - Схемы: `tenant{id}` в `crater_saas_dev`
  - Домены: `{id}.dev.crater.billing.mycloud.kg`

**Создание тенанта для тестирования:**

1. Создайте тенанта в dev окружении через админку: `dev.crater.billing.mycloud.kg/super-admin`
2. Протестируйте на: `test123.dev.crater.billing.mycloud.kg`
3. Если всё ок, создайте тот же тенант в production: `crater.billing.mycloud.kg/super-admin`

### 8. Полезные команды

#### Список всех тенантов:
```bash
docker compose exec -u root app php artisan tenants:list
```

#### Инициализация существующего тенанта (если что-то пошло не так):
```bash
docker compose exec -u root app php artisan tenant:initialize {tenant_id}
```

#### Удаление тенанта (удалит схему и все данные):
```bash
docker compose exec -u root app php artisan tinker
# В tinker:
$tenant = \App\Models\Tenant::find('test123');
$tenant->delete(); // Удалит схему автоматически
```

#### Просмотр схем в БД:
```bash
# Production
docker compose exec db psql -U crater -d crater_saas -c "\dn"

# Dev
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -d crater_saas_dev -c "\dn"
```

#### Просмотр таблиц в схеме тенанта:
```bash
docker compose exec db psql -U crater -d crater_saas -c "\dt tenanttest.*"
```

### 9. Типичный workflow разработки

1. **Локальная разработка:**
   ```bash
   # Создайте тенанта локально
   php artisan tinker
   # В tinker: создайте тенанта и инициализируйте
   
   # Работайте с кодом
   # Тестируйте на localhost
   ```

2. **Тестирование на dev сервере:**
   ```bash
   # Запушьте изменения в dev ветку
   git push origin dev
   
   # Создайте тенанта в dev окружении
   # Зайдите на dev.crater.billing.mycloud.kg/super-admin
   # Создайте тенанта через админку
   
   # Протестируйте на {id}.dev.crater.billing.mycloud.kg
   ```

3. **Деплой в production:**
   ```bash
   # Мердж dev → master
   git checkout master
   git merge dev
   git push origin master
   
   # Создайте тенанта в production (если нужно)
   # Зайдите на crater.billing.mycloud.kg/super-admin
   ```

### 10. Важные моменты

1. **ID тенанта** должен быть уникальным и содержать только буквы, цифры и подчеркивания
2. **Поддомен создается автоматически**: `{tenant_id}.{main_domain}`
3. **Схема создается автоматически**: `tenant{tenant_id}`
4. **Все миграции из `database/migrations/tenant/` выполняются в схеме тенанта**
5. **Данные полностью изолированы** между тенантами
6. **Production и Dev тенанты независимы** - тенант `test` в production и `test` в dev - это разные тенанты

