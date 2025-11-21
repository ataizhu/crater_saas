#!/bin/bash
# Скрипт для проверки доступа к тенантам

echo "=== Проверка доступа к тенантам ==="
echo ""

ENV=${1:-production}

if [ "$ENV" = "production" ]; then
    CD_DIR="/var/www/crater"
    COMPOSE_CMD="docker compose"
else
    CD_DIR="/var/www/crater-dev"
    COMPOSE_CMD="docker compose -p crater-dev -f docker-compose.dev.yml"
fi

cd "$CD_DIR" || exit 1

echo "Окружение: $ENV"
echo "Директория: $CD_DIR"
echo ""

echo "1. Проверка тенантов и их доменов:"
echo "-----------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
DB::statement('SET search_path TO admin');
\$tenants = DB::table('tenants')->get(['id', 'name']);
\$domains = DB::table('domains')->get(['domain', 'tenant_id']);
echo 'Всего тенантов: ' . \$tenants->count() . PHP_EOL;
foreach (\$tenants as \$tenant) {
    echo PHP_EOL . 'Tenant ID: ' . \$tenant->id . PHP_EOL;
    echo '  Name: ' . (\$tenant->name ?? 'нет') . PHP_EOL;
    \$tenantDomains = \$domains->where('tenant_id', \$tenant->id);
    echo '  Domains (' . \$tenantDomains->count() . '):' . PHP_EOL;
    foreach (\$tenantDomains as \$domain) {
        echo '    - ' . \$domain->domain . PHP_EOL;
    }
}
" 2>&1
echo ""

echo "2. Проверка центральных доменов:"
echo "--------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
\$domains = config('tenancy.central_domains', []);
echo 'Central domains (' . count(\$domains) . '):' . PHP_EOL;
foreach (\$domains as \$domain) {
    echo '  - ' . \$domain . PHP_EOL;
}
" 2>&1
echo ""

echo "3. Проверка последних ошибок в логах:"
echo "-------------------------------------"
LOG_FILE="storage/logs/laravel-$(date +%Y-%m-%d).log"
if [ -f "$LOG_FILE" ]; then
    echo "Последние ошибки из $LOG_FILE:"
    $COMPOSE_CMD exec -T app tail -50 "$LOG_FILE" | grep -i "error\|exception\|tenant" | tail -20 || echo "Ошибок не найдено"
else
    echo "Файл логов не найден: $LOG_FILE"
fi
echo ""

echo "4. Проверка nginx конфигурации для поддоменов:"
echo "----------------------------------------------"
if [ -f /etc/nginx/sites-available/crater.billing.mycloud.kg ]; then
    echo "Production nginx config:"
    grep -A 5 "server_name.*\*" /etc/nginx/sites-available/crater.billing.mycloud.kg || echo "  Wildcard не найден"
fi
if [ -f /etc/nginx/sites-available/dev.crater.billing.mycloud.kg ]; then
    echo "Dev nginx config:"
    grep -A 5 "server_name.*\*" /etc/nginx/sites-available/dev.crater.billing.mycloud.kg || echo "  Wildcard не найден"
fi
echo ""

echo "5. Тест определения тенанта по домену:"
echo "--------------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
\$testDomain = 'test.crater.billing.mycloud.kg';
echo 'Тестируем домен: ' . \$testDomain . PHP_EOL;
DB::statement('SET search_path TO admin');
\$domain = DB::table('domains')->where('domain', \$testDomain)->first();
if (\$domain) {
    echo '✓ Домен найден в БД' . PHP_EOL;
    echo '  Tenant ID: ' . \$domain->tenant_id . PHP_EOL;
    \$tenant = DB::table('tenants')->where('id', \$domain->tenant_id)->first();
    if (\$tenant) {
        echo '✓ Тенант найден' . PHP_EOL;
    } else {
        echo '✗ Тенант НЕ найден!' . PHP_EOL;
    }
} else {
    echo '✗ Домен НЕ найден в БД!' . PHP_EOL;
}
" 2>&1
echo ""

echo "=== Проверка завершена ==="

