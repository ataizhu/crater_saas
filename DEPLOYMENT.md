# Инструкция по деплою

## Настройка GitHub Secrets

### 1. Генерация SSH ключа для GitHub Actions

На сервере выполните:

```bash
# Создайте SSH ключ специально для GitHub Actions
ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/github_actions -N ""

# Покажите публичный ключ - его нужно добавить в authorized_keys
cat ~/.ssh/github_actions.pub

# Добавьте публичный ключ в authorized_keys
cat ~/.ssh/github_actions.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys

# Покажите приватный ключ - его нужно добавить в GitHub Secrets
cat ~/.ssh/github_actions
```

### 2. Добавление секретов в GitHub

В GitHub: Settings → Secrets and variables → Actions → New repository secret

**Для продакшн и dev:**
- `SSH_HOST`: `31.3.216.186` (или IP вашего сервера)
- `SSH_USERNAME`: `root` (или ваш пользователь)
- `SSH_KEY`: (приватный ключ из шага 1)

## Архитектура окружений

**Важно:** На сервере настроены два полностью изолированных окружения:

- **Production:** `/var/www/crater` (ветка `master`)
  - PHP-FPM: порт `9000`
  - PostgreSQL: порт `54320`
  - База данных: `crater_saas`
  - Домен: `crater.billing.mycloud.kg`
  - Docker compose project: `crater` (по умолчанию)

- **Dev:** `/var/www/crater-dev` (ветка `dev`)
  - PHP-FPM: порт `9001`
  - PostgreSQL: порт `54321`
  - База данных: `crater_saas_dev`
  - Домен: `dev.crater.billing.mycloud.kg`
  - Docker compose project: `crater-dev`
  - Docker compose файл: `docker-compose.dev.yml`

## Настройка production окружения на сервере

```bash
# 1. Создайте директорию для production
mkdir -p /var/www/crater
cd /var/www/crater

# 2. Клонируйте репозиторий
git clone https://github.com/ataizhu/crater_saas.git .

# 3. Переключитесь на master ветку
git checkout master

# 4. Создайте .env для production
cp .env.example .env
# Отредактируйте .env - пример ниже

# 5. Настройте .env для production
cat > .env << 'EOF'
APP_NAME=Crater
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://crater.billing.mycloud.kg

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=crater_saas
DB_USERNAME=crater
DB_PASSWORD=crater

BROADCAST_DRIVER=log
CACHE_DRIVER=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIN_DOMAIN=crater.billing.mycloud.kg
SESSION_DOMAIN=.crater.billing.mycloud.kg
SANCTUM_STATEFUL_DOMAINS=crater.billing.mycloud.kg,*.crater.billing.mycloud.kg
EOF

# 6. Сгенерируйте ключ приложения
docker compose exec -u root app php artisan key:generate

# 7. Установите зависимости
docker compose exec -u root app composer install --no-dev --optimize-autoloader

# 8. Выполните миграции
docker compose exec -u root app php artisan migrate --force

# 9. Настройте права доступа
docker compose exec -u root app chmod -R 775 /var/www/storage
docker compose exec -u root app chmod -R 775 /var/www/bootstrap/cache
docker compose exec -u root app chown -R www-data:www-data /var/www/storage
docker compose exec -u root app chown -R www-data:www-data /var/www/bootstrap/cache
```

## Настройка dev окружения на сервере

```bash
# 1. Создайте директорию для dev
mkdir -p /var/www/crater-dev
cd /var/www/crater-dev

# 2. Клонируйте репозиторий
git clone https://github.com/ataizhu/crater_saas.git .

# 3. Переключитесь на dev ветку
git checkout dev

# 4. Создайте .env для dev
cp .env.example .env
# Отредактируйте .env - пример ниже

# 5. Настройте .env для dev
cat > .env << 'EOF'
APP_NAME=Crater
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://dev.crater.billing.mycloud.kg

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=crater_saas_dev
DB_USERNAME=crater
DB_PASSWORD=crater

BROADCAST_DRIVER=log
CACHE_DRIVER=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIN_DOMAIN=dev.crater.billing.mycloud.kg
SESSION_DOMAIN=.dev.crater.billing.mycloud.kg
SANCTUM_STATEFUL_DOMAINS=dev.crater.billing.mycloud.kg,*.dev.crater.billing.mycloud.kg
EOF

# 6. Запустите контейнеры с dev конфигурацией
docker compose -p crater-dev -f docker-compose.dev.yml up -d --build

# 7. Установите зависимости
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app composer install --no-dev --optimize-autoloader

# 8. Сгенерируйте ключ приложения
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan key:generate

# 9. Выполните миграции
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan migrate --force

# 10. Настройте права доступа
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app chmod -R 775 /var/www/storage
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app chmod -R 775 /var/www/bootstrap/cache
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app chown -R www-data:www-data /var/www/storage
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app chown -R www-data:www-data /var/www/bootstrap/cache
```

## Настройка Nginx на сервере

### Production домен

```bash
cat > /etc/nginx/sites-available/crater.billing.mycloud.kg << 'EOF'
server {
    listen 80;
    server_name crater.billing.mycloud.kg *.crater.billing.mycloud.kg;

    root /var/www/crater/public;
    index index.php index.html;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;  # Production PHP-FPM порт
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/public$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param HTTP_HOST $host;
        fastcgi_read_timeout 300;
    }
}
EOF

ln -sf /etc/nginx/sites-available/crater.billing.mycloud.kg /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default  # Удалите дефолтный сайт если есть
nginx -t
systemctl reload nginx
```

### Dev домен

```bash
cat > /etc/nginx/sites-available/dev.crater.billing.mycloud.kg << 'EOF'
server {
    listen 80;
    server_name dev.crater.billing.mycloud.kg *.dev.crater.billing.mycloud.kg;

    root /var/www/crater-dev/public;
    index index.php index.html;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9001;  # Dev PHP-FPM порт
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/public$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param HTTP_HOST $host;
        fastcgi_read_timeout 300;
    }
}
EOF

ln -sf /etc/nginx/sites-available/dev.crater.billing.mycloud.kg /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

## Автоматический деплой через GitHub Actions

### Как это работает

1. **Push в `dev` ветку:**
   - Автоматически деплоится на `/var/www/crater-dev`
   - Обновляет код из `dev` ветки
   - Пересобирает и перезапускает контейнеры с `docker-compose.dev.yml`
   - Запускает миграции
   - Очищает кеши

2. **Push в `master` ветку:**
   - Автоматически деплоится на `/var/www/crater`
   - Обновляет код из `master` ветки
   - Пересобирает и перезапускает контейнеры
   - Запускает миграции
   - Очищает кеши

### Что происходит при деплое

```bash
# Пример того, что делает GitHub Actions workflow:

cd /var/www/crater  # или /var/www/crater-dev
git fetch origin
git reset --hard origin/master  # или origin/dev
docker compose up -d --build  # или docker compose -p crater-dev -f docker-compose.dev.yml up -d --build
docker compose exec -u root app php artisan migrate --force || true
docker compose exec -u root app php artisan config:clear || true
docker compose exec -u root app php artisan config:cache || true
docker compose exec -u root app php artisan route:cache || true
docker compose exec -u root app php artisan view:cache || true
```

### Важные моменты для автоматического деплоя

✅ **Что работает автоматически:**
- Обновление кода из Git
- Пересборка Docker образов (если изменился Dockerfile)
- Перезапуск контейнеров
- Запуск миграций (если они есть)
- Очистка и пересборка кешей

⚠️ **Что нужно делать вручную (если изменилось):**
- Изменения в `.env` файле — нужно редактировать вручную на сервере
- Изменения в Nginx конфигурации — нужно обновлять вручную на сервере
- Создание новых баз данных — выполняется автоматически через миграции
- Изменения в DNS — настраиваются отдельно

### Рекомендуемый workflow

1. **Локальная разработка:**
   ```bash
   git checkout dev
   # Делайте изменения
   git commit -m "feature: ..."
   git push origin dev
   # Автоматический деплой на dev сервер
   ```

2. **Тестирование на dev сервере:**
   - Проверьте на `dev.crater.billing.mycloud.kg`
   - Убедитесь, что все работает

3. **Мердж в production:**
   ```bash
   git checkout master
   git merge dev
   git push origin master
   # Автоматический деплой на production сервер
   ```

4. **Проверка production:**
   - Проверьте на `crater.billing.mycloud.kg`
   - Мониторьте логи на ошибки

## Мониторинг и логи

### Проверка статуса контейнеров

```bash
# Production
cd /var/www/crater
docker compose ps

# Dev
cd /var/www/crater-dev
docker compose -p crater-dev -f docker-compose.dev.yml ps
```

### Просмотр логов

```bash
# Production логи
cd /var/www/crater
docker compose logs app | tail -50
docker compose logs db | tail -50

# Dev логи
cd /var/www/crater-dev
docker compose -p crater-dev -f docker-compose.dev.yml logs app | tail -50
docker compose -p crater-dev -f docker-compose.dev.yml logs db | tail -50

# Laravel логи
docker compose exec -u root app tail -50 /var/www/storage/logs/laravel-$(date +%Y-%m-%d).log
```

### Проверка портов

```bash
# Проверьте, что все порты слушаются правильно
netstat -tulpn | grep -E "9000|9001|54320|54321"

# Должно быть:
# 9000 - Production PHP-FPM
# 9001 - Dev PHP-FPM
# 54320 - Production PostgreSQL
# 54321 - Dev PostgreSQL
```

## Различия production и dev

| Параметр | Production | Dev |
|----------|-----------|-----|
| Директория | `/var/www/crater` | `/var/www/crater-dev` |
| Ветка Git | `master` | `dev` |
| PHP-FPM порт | `9000` | `9001` |
| PostgreSQL порт | `54320` | `54321` |
| База данных | `crater_saas` | `crater_saas_dev` |
| Nginx порт | `80` (внешний) | `8080` (внутренний, не используется) |
| APP_ENV | `production` | `testing` |
| APP_DEBUG | `false` | `true` |
| Домен | `crater.billing.mycloud.kg` | `dev.crater.billing.mycloud.kg` |
| Docker compose | `docker-compose.yml` | `docker-compose.dev.yml` |
| Project name | `crater` (по умолчанию) | `crater-dev` |

## Полезные команды

### Проверка статуса деплоя
GitHub → Actions → проверьте статус последнего деплоя

### Откат деплоя (если что-то пошло не так)
```bash
# Production
cd /var/www/crater
git reset --hard HEAD~1
docker compose exec -u root app php artisan config:clear
docker compose exec -u root app php artisan cache:clear

# Dev
cd /var/www/crater-dev
git reset --hard HEAD~1
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan config:clear
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan cache:clear
```

### Перезапуск контейнеров
```bash
# Production
cd /var/www/crater
docker compose restart

# Dev
cd /var/www/crater-dev
docker compose -p crater-dev -f docker-compose.dev.yml restart
```

### Очистка всех кешей
```bash
# Production
cd /var/www/crater
docker compose exec -u root app php artisan config:clear
docker compose exec -u root app php artisan cache:clear
docker compose exec -u root app php artisan route:clear
docker compose exec -u root app php artisan view:clear

# Dev
cd /var/www/crater-dev
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan config:clear
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan cache:clear
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan route:clear
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan view:clear
```

## Важные замечания

1. **Не редактируйте код напрямую на сервере** — все изменения должны идти через Git, чтобы не потерять их при следующем деплое.

2. **`.env` файлы не отслеживаются в Git** — если изменили переменные окружения, сохраните их отдельно или обновите вручную на сервере после деплоя.

3. **Миграции выполняются автоматически** при каждом деплое, но используйте `|| true` в workflow, чтобы деплой не падал на ошибках миграций.

4. **Два окружения полностью изолированы** — изменения в dev не влияют на production и наоборот.

5. **Порты не конфликтуют** — production и dev используют разные порты для PHP-FPM и PostgreSQL.
