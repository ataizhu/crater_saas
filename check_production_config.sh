#!/bin/bash
# Скрипт для проверки конфигурации production окружения

echo "=== Проверка конфигурации Production ==="
echo ""

cd /var/www/crater || { echo "❌ Директория /var/www/crater не найдена"; exit 1; }

echo "1. Проверка .env файла:"
echo "----------------------"
if [ ! -f .env ]; then
    echo "❌ .env файл не найден!"
else
    echo "✅ .env файл существует"
    echo ""
    echo "Критические переменные:"
    grep -E "^APP_URL=|^MAIN_DOMAIN=|^SESSION_DOMAIN=|^SANCTUM_STATEFUL_DOMAINS=|^SESSION_SECURE_COOKIE=|^SESSION_DRIVER=|^APP_ENV=" .env | sed 's/=.*/=***/' || echo "⚠️  Некоторые переменные не найдены"
fi

echo ""
echo "2. Проверка конфигурации Laravel:"
echo "---------------------------------"
docker compose exec app php artisan tinker --execute="
echo 'APP_URL: ' . config('app.url');
echo 'MAIN_DOMAIN: ' . config('app.main_domain');
echo 'SESSION_DOMAIN: ' . config('session.domain');
echo 'SESSION_SECURE_COOKIE: ' . (config('session.secure') ? 'true' : 'false');
echo 'SESSION_DRIVER: ' . config('session.driver');
echo 'APP_ENV: ' . config('app.env');
echo 'APP_DEBUG: ' . (config('app.debug') ? 'true' : 'false');
"

echo ""
echo "3. Проверка Sanctum stateful domains:"
echo "-------------------------------------"
docker compose exec app php artisan tinker --execute="
\$domains = config('sanctum.stateful', []);
echo 'Stateful domains (' . count(\$domains) . '):';
foreach (\$domains as \$domain) {
    echo '  - ' . \$domain;
}
"

echo ""
echo "4. Проверка центральных доменов (tenancy):"
echo "-------------------------------------------"
docker compose exec app php artisan tinker --execute="
\$domains = config('tenancy.central_domains', []);
echo 'Central domains (' . count(\$domains) . '):';
foreach (\$domains as \$domain) {
    echo '  - ' . \$domain;
}
"

echo ""
echo "5. Проверка CSRF токена (тест запроса):"
echo "---------------------------------------"
echo "Попробуем получить CSRF токен:"
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt http://crater.billing.mycloud.kg/admin/login | grep -o 'csrf-token[^>]*content="[^"]*"' | head -1 || echo "⚠️  CSRF токен не найден в HTML"

echo ""
echo "6. Проверка cookies в ответе:"
echo "----------------------------"
echo "Тестовый запрос для проверки cookies:"
curl -s -I -c /tmp/cookies.txt http://crater.billing.mycloud.kg/admin/login | grep -i "set-cookie" || echo "⚠️  Set-Cookie заголовки не найдены"

echo ""
echo "7. Проверка сессий (storage):"
echo "----------------------------"
docker compose exec app ls -la /var/www/storage/framework/sessions/ | head -5 || echo "⚠️  Сессии не найдены"

echo ""
echo "=== Проверка завершена ==="

