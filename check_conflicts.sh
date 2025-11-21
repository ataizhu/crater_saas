#!/bin/bash
# Скрипт для проверки конфликтов между production и dev окружениями

echo "=== Проверка конфликтов между Production и Dev ==="
echo ""

echo "1. Проверка портов PHP-FPM:"
echo "----------------------------"
echo "Production должен использовать порт 9000"
echo "Dev должен использовать порт 9001"
echo ""
netstat -tulpn | grep -E "9000|9001" || echo "Порты не найдены"
echo ""

echo "2. Проверка конфигурации nginx на сервере:"
echo "-------------------------------------------"
if [ -f /etc/nginx/sites-available/crater.billing.mycloud.kg ]; then
    echo "✓ Production nginx config существует"
    echo "  PHP-FPM порт:"
    grep -E "fastcgi_pass.*900" /etc/nginx/sites-available/crater.billing.mycloud.kg || echo "  ✗ Не найден fastcgi_pass"
    echo "  Server name:"
    grep -E "server_name" /etc/nginx/sites-available/crater.billing.mycloud.kg || echo "  ✗ Не найден server_name"
else
    echo "✗ Production nginx config НЕ найден!"
fi
echo ""

if [ -f /etc/nginx/sites-available/dev.crater.billing.mycloud.kg ]; then
    echo "✓ Dev nginx config существует"
    echo "  PHP-FPM порт:"
    grep -E "fastcgi_pass.*900" /etc/nginx/sites-available/dev.crater.billing.mycloud.kg || echo "  ✗ Не найден fastcgi_pass"
    echo "  Server name:"
    grep -E "server_name" /etc/nginx/sites-available/dev.crater.billing.mycloud.kg || echo "  ✗ Не найден server_name"
else
    echo "✗ Dev nginx config НЕ найден!"
fi
echo ""

echo "3. Проверка Docker контейнеров:"
echo "-------------------------------"
echo "Production контейнеры:"
cd /var/www/crater 2>/dev/null && docker compose ps || echo "  ✗ Не удалось проверить production"
echo ""

echo "Dev контейнеры:"
cd /var/www/crater-dev 2>/dev/null && docker compose -p crater-dev -f docker-compose.dev.yml ps || echo "  ✗ Не удалось проверить dev"
echo ""

echo "4. Проверка баз данных:"
echo "----------------------"
echo "Production БД:"
cd /var/www/crater 2>/dev/null && docker compose exec -T db psql -U crater -d crater_saas -c "SELECT current_database(), current_schema();" 2>/dev/null || echo "  ✗ Не удалось подключиться"
echo ""

echo "Dev БД:"
cd /var/www/crater-dev 2>/dev/null && docker compose -p crater-dev -f docker-compose.dev.yml exec -T db psql -U crater -d crater_saas_dev -c "SELECT current_database(), current_schema();" 2>/dev/null || echo "  ✗ Не удалось подключиться"
echo ""

echo "5. Проверка центральных доменов в tenancy config:"
echo "------------------------------------------------"
cd /var/www/crater 2>/dev/null && docker compose exec -T app php artisan tinker --execute="
echo 'Central domains:' . PHP_EOL;
\$domains = config('tenancy.central_domains', []);
foreach (\$domains as \$domain) {
    echo '  - ' . \$domain . PHP_EOL;
}
" 2>/dev/null || echo "  ✗ Не удалось проверить"
echo ""

echo "6. Проверка тенантов в production:"
echo "----------------------------------"
cd /var/www/crater 2>/dev/null && docker compose exec -T app php artisan tinker --execute="
DB::statement('SET search_path TO admin');
\$tenants = DB::table('tenants')->get(['id', 'name']);
\$domains = DB::table('domains')->get(['domain', 'tenant_id']);
echo 'Tenants: ' . \$tenants->count() . PHP_EOL;
foreach (\$tenants as \$tenant) {
    echo '  - ' . \$tenant->id . ' (' . (\$tenant->name ?? 'no name') . ')' . PHP_EOL;
    \$tenantDomains = \$domains->where('tenant_id', \$tenant->id);
    foreach (\$tenantDomains as \$domain) {
        echo '    Domain: ' . \$domain->domain . PHP_EOL;
    }
}
" 2>/dev/null || echo "  ✗ Не удалось проверить"
echo ""

echo "=== Проверка завершена ==="

