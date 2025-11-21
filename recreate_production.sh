#!/bin/bash
# Скрипт для пересоздания production окружения с нуля на основе dev

set -e

echo "=== Пересоздание Production окружения ==="
echo "ВНИМАНИЕ: Это удалит все данные production!"
read -p "Продолжить? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Отменено."
    exit 1
fi

cd /var/www/crater || exit 1

echo "1. Останавливаем контейнеры..."
docker compose down -v || true

echo "1.1. Проверяем, что внешний nginx не конфликтует с портом 80..."
if lsof -i :80 > /dev/null 2>&1; then
    echo "✓ Внешний nginx работает на порту 80 (это нормально для production)"
else
    echo "⚠ Порт 80 свободен - возможно, нужно настроить внешний nginx"
fi

echo "2. Удаляем старую директорию (опционально, можно закомментировать для сохранения данных)..."
# cd /var/www && rm -rf crater || true
# mkdir -p /var/www/crater
# cd /var/www/crater

echo "3. Клонируем репозиторий (если директория была удалена)..."
# git clone https://github.com/ataizhu/crater_saas.git . || true

echo "4. Переключаемся на master ветку..."
git fetch origin
git checkout master || git checkout -b master
git reset --hard origin/master

echo "5. Копируем .env из dev (если нужно) или создаем новый..."
if [ -f /var/www/crater-dev/.env ]; then
    echo "Копируем .env из dev..."
    cp /var/www/crater-dev/.env /var/www/crater/.env
    # Обновляем специфичные для production значения
    sed -i 's/APP_ENV=testing/APP_ENV=production/' /var/www/crater/.env
    sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' /var/www/crater/.env
    sed -i 's/DB_DATABASE=crater_saas_dev/DB_DATABASE=crater_saas/' /var/www/crater/.env
    sed -i 's/MAIN_DOMAIN=dev\.crater\.billing\.mycloud\.kg/MAIN_DOMAIN=crater.billing.mycloud.kg/' /var/www/crater/.env
    sed -i 's/SESSION_DOMAIN=\.dev\.crater\.billing\.mycloud\.kg/SESSION_DOMAIN=.crater.billing.mycloud.kg/' /var/www/crater/.env
    sed -i 's/SANCTUM_STATEFUL_DOMAINS=dev\.crater\.billing\.mycloud\.kg/SANCTUM_STATEFUL_DOMAINS=crater.billing.mycloud.kg/' /var/www/crater/.env
    sed -i 's/APP_URL=http:\/\/dev\.crater\.billing\.mycloud\.kg/APP_URL=http:\/\/crater.billing.mycloud.kg/' /var/www/crater/.env
else
    echo "Создаем новый .env..."
    cp /var/www/crater/.env.example /var/www/crater/.env || true
fi

echo "6. Собираем контейнеры..."
docker compose up -d --build

echo "7. Устанавливаем зависимости..."
docker compose exec -u root app composer install --no-dev --optimize-autoloader

echo "8. Исправляем права..."
docker compose exec -u root app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
docker compose exec -u root app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

echo "9. Генерируем ключ приложения..."
docker compose exec -u root app php artisan key:generate --force

echo "10. Создаем схему admin..."
docker compose exec db psql -U crater -d crater_saas -c "CREATE SCHEMA IF NOT EXISTS admin;" || true
docker compose exec db psql -U crater -d crater_saas -c "GRANT ALL ON SCHEMA admin TO crater;" || true
docker compose exec db psql -U crater -d crater_saas -c "ALTER DATABASE crater_saas SET search_path TO admin;" || true

echo "11. Запускаем миграции..."
docker compose exec -u root app php artisan migrate --force

echo "12. Создаем admin пользователя..."
docker compose exec -u root app php artisan admin:create "Admin" "admin@example.com" "testtest" --update || true

echo "13. Устанавливаем npm зависимости (только если нужно)..."
if [ ! -d "/var/www/crater/node_modules" ]; then
    echo "Устанавливаем npm зависимости..."
    docker compose exec -u root app npm install
    docker compose exec -u root app npm run build
fi

echo "14. Очищаем кеши..."
docker compose exec -u root app php artisan config:clear
docker compose exec -u root app php artisan cache:clear
docker compose exec -u root app php artisan route:clear
docker compose exec -u root app php artisan view:clear

echo "15. Создаем файл логов..."
docker compose exec -u root app touch /var/www/storage/logs/laravel-$(date +%Y-%m-%d).log
docker compose exec -u root app chmod 666 /var/www/storage/logs/laravel-$(date +%Y-%m-%d).log
docker compose exec -u root app chown www-data:www-data /var/www/storage/logs/laravel-$(date +%Y-%m-%d).log

echo "16. Перезапускаем контейнеры..."
docker compose restart

echo "=== Production окружение пересоздано! ==="
echo "Проверьте логи: docker compose exec -T app tail -f storage/logs/laravel-$(date +%Y-%m-%d).log"

