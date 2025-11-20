# Полный сброс БД и пересоздание проекта на сервере

## ⚠️ ВНИМАНИЕ: Эти команды удалят все данные в БД!

---

## Для DEV окружения (`/var/www/crater-dev`)

```bash
# 1. Перейти в директорию проекта
cd /var/www/crater-dev

# 2. Остановить контейнеры
docker compose -p crater-dev -f docker-compose.dev.yml down

# 3. Удалить БД (подключимся к PostgreSQL напрямую)
docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U crater -c "DROP DATABASE IF EXISTS crater_saas_dev;" || \
docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U postgres -c "DROP DATABASE IF EXISTS crater_saas_dev;" || \
echo "Database may not exist or already dropped"

# 4. Создать БД заново
docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U crater -c "CREATE DATABASE crater_saas_dev OWNER crater;" || \
docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U postgres -c "CREATE DATABASE crater_saas_dev OWNER crater;"

# 5. Получить последний код из Git
git fetch origin
git reset --hard origin/dev
git pull origin dev

# 6. Запустить контейнеры
docker compose -p crater-dev -f docker-compose.dev.yml up -d --build

# 7. Установить зависимости
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app composer install --no-dev --optimize-autoloader

# 8. Исправить права доступа
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# 9. Сгенерировать ключ приложения
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan key:generate --force

# 10. Очистить кеши перед миграциями
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan config:clear
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app rm -rf /var/www/bootstrap/cache/*.php

# 11. Создать схему admin в БД
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -d crater_saas_dev -c "CREATE SCHEMA IF NOT EXISTS admin;"
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -d crater_saas_dev -c "GRANT ALL ON SCHEMA admin TO crater;"
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -d crater_saas_dev -c "ALTER DATABASE crater_saas_dev SET search_path TO admin;"

# 12. Запустить миграции
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan migrate --force

# 13. Создать тестового админ-пользователя
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan admin:create "Admin" "admin@example.com" "testtest" || echo "Admin user may already exist"

# 14. Очистить все кеши после миграций
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan config:clear
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan cache:clear
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan route:clear
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan view:clear

# 15. Перезапустить контейнеры для применения всех изменений
docker compose -p crater-dev -f docker-compose.dev.yml restart app

echo "✅ Dev окружение пересоздано и готово к работе!"
```

**Или одной командой:**

```bash
cd /var/www/crater-dev && \
docker compose -p crater-dev -f docker-compose.dev.yml down && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U crater -c "DROP DATABASE IF EXISTS crater_saas_dev;" 2>/dev/null || \
docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U postgres -c "DROP DATABASE IF EXISTS crater_saas_dev;" 2>/dev/null || true && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U crater -c "CREATE DATABASE crater_saas_dev OWNER crater;" 2>/dev/null || \
docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U postgres -c "CREATE DATABASE crater_saas_dev OWNER crater;" && \
git fetch origin && git reset --hard origin/dev && git pull origin dev && \
docker compose -p crater-dev -f docker-compose.dev.yml up -d --build && \
sleep 5 && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app composer install --no-dev --optimize-autoloader && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app chmod -R 775 /var/www/storage /var/www/bootstrap/cache && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan key:generate --force && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan config:clear && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app rm -rf /var/www/bootstrap/cache/*.php && \
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -d crater_saas_dev -c "CREATE SCHEMA IF NOT EXISTS admin;" && \
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -d crater_saas_dev -c "GRANT ALL ON SCHEMA admin TO crater;" && \
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -d crater_saas_dev -c "ALTER DATABASE crater_saas_dev SET search_path TO admin;" && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan migrate --force && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan admin:create "Admin" "admin@example.com" "testtest" || true && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan config:clear && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan cache:clear && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan route:clear && \
docker compose -p crater-dev -f docker-compose.dev.yml exec -u root app php artisan view:clear && \
docker compose -p crater-dev -f docker-compose.dev.yml restart app && \
echo "✅ Dev окружение пересоздано!"
```

---

## Для PRODUCTION окружения (`/var/www/crater`)

```bash
# 1. Перейти в директорию проекта
cd /var/www/crater

# 2. Остановить контейнеры
docker compose down

# 3. Удалить БД (подключимся к PostgreSQL напрямую)
docker compose exec -T db psql -U crater -c "DROP DATABASE IF EXISTS crater_saas;" || \
docker compose exec -T db psql -U postgres -c "DROP DATABASE IF EXISTS crater_saas;" || \
echo "Database may not exist or already dropped"

# 4. Создать БД заново
docker compose exec -T db psql -U crater -c "CREATE DATABASE crater_saas OWNER crater;" || \
docker compose exec -T db psql -U postgres -c "CREATE DATABASE crater_saas OWNER crater;"

# 5. Получить последний код из Git
git fetch origin
git reset --hard origin/master
git pull origin master

# 6. Запустить контейнеры
docker compose up -d --build

# 7. Установить зависимости
docker compose exec -u root app composer install --no-dev --optimize-autoloader

# 8. Исправить права доступа
docker compose exec -u root app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
docker compose exec -u root app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# 9. Сгенерировать ключ приложения
docker compose exec -u root app php artisan key:generate --force

# 10. Очистить кеши перед миграциями
docker compose exec -u root app php artisan config:clear
docker compose exec -u root app rm -rf /var/www/bootstrap/cache/*.php

# 11. Создать схему admin в БД
docker compose exec db psql -U crater -d crater_saas -c "CREATE SCHEMA IF NOT EXISTS admin;"
docker compose exec db psql -U crater -d crater_saas -c "GRANT ALL ON SCHEMA admin TO crater;"
docker compose exec db psql -U crater -d crater_saas -c "ALTER DATABASE crater_saas SET search_path TO admin;"

# 12. Запустить миграции
docker compose exec -u root app php artisan migrate --force

# 13. Создать тестового админ-пользователя
docker compose exec -u root app php artisan admin:create "Admin" "admin@example.com" "testtest" || echo "Admin user may already exist"

# 14. Очистить все кеши после миграций
docker compose exec -u root app php artisan config:clear
docker compose exec -u root app php artisan cache:clear
docker compose exec -u root app php artisan route:clear
docker compose exec -u root app php artisan view:clear

# 15. Перезапустить контейнеры для применения всех изменений
docker compose restart app

echo "✅ Production окружение пересоздано и готово к работе!"
```

**Или одной командой:**

```bash
cd /var/www/crater && \
docker compose down && \
docker compose exec -T db psql -U crater -c "DROP DATABASE IF EXISTS crater_saas;" 2>/dev/null || \
docker compose exec -T db psql -U postgres -c "DROP DATABASE IF EXISTS crater_saas;" 2>/dev/null || true && \
docker compose exec -T db psql -U crater -c "CREATE DATABASE crater_saas OWNER crater;" 2>/dev/null || \
docker compose exec -T db psql -U postgres -c "CREATE DATABASE crater_saas OWNER crater;" && \
git fetch origin && git reset --hard origin/master && git pull origin master && \
docker compose up -d --build && \
sleep 5 && \
docker compose exec -u root app composer install --no-dev --optimize-autoloader && \
docker compose exec -u root app chmod -R 775 /var/www/storage /var/www/bootstrap/cache && \
docker compose exec -u root app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache && \
docker compose exec -u root app php artisan key:generate --force && \
docker compose exec -u root app php artisan config:clear && \
docker compose exec -u root app rm -rf /var/www/bootstrap/cache/*.php && \
docker compose exec db psql -U crater -d crater_saas -c "CREATE SCHEMA IF NOT EXISTS admin;" && \
docker compose exec db psql -U crater -d crater_saas -c "GRANT ALL ON SCHEMA admin TO crater;" && \
docker compose exec db psql -U crater -d crater_saas -c "ALTER DATABASE crater_saas SET search_path TO admin;" && \
docker compose exec -u root app php artisan migrate --force && \
docker compose exec -u root app php artisan admin:create "Admin" "admin@example.com" "testtest" || true && \
docker compose exec -u root app php artisan config:clear && \
docker compose exec -u root app php artisan cache:clear && \
docker compose exec -u root app php artisan route:clear && \
docker compose exec -u root app php artisan view:clear && \
docker compose restart app && \
echo "✅ Production окружение пересоздано!"
```

---

## Альтернативный способ: Удаление БД через psql напрямую (если контейнеры остановлены)

Если контейнеры остановлены, можно подключиться к PostgreSQL напрямую:

### Для DEV:

```bash
# Запустить только DB контейнер
cd /var/www/crater-dev
docker compose -p crater-dev -f docker-compose.dev.yml up -d db

# Удалить БД
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -c "DROP DATABASE IF EXISTS crater_saas_dev;"

# Создать БД заново
docker compose -p crater-dev -f docker-compose.dev.yml exec db psql -U crater -c "CREATE DATABASE crater_saas_dev OWNER crater;"

# Затем продолжить с шага 5 из инструкции выше
```

### Для PRODUCTION:

```bash
# Запустить только DB контейнер
cd /var/www/crater
docker compose up -d db

# Удалить БД
docker compose exec db psql -U crater -c "DROP DATABASE IF EXISTS crater_saas;"

# Создать БД заново
docker compose exec db psql -U crater -c "CREATE DATABASE crater_saas OWNER crater;"

# Затем продолжить с шага 5 из инструкции выше
```

---

## Важные замечания

1. ⚠️ **Все данные будут удалены!** Убедитесь, что у вас есть бэкап, если нужны данные.
2. После удаления БД нужно пересоздать её и запустить миграции заново.
3. Все тенанты будут удалены вместе с БД.
4. После пересоздания нужно создать новых тенантов через админ-панель Filament.
