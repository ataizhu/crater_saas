#!/bin/bash
# Скрипт для тестирования доступа к тенанту

echo "=== Тестирование доступа к тенанту ==="
echo ""

ENV=${1:-production}
TENANT_DOMAIN=${2:-"test.crater.billing.mycloud.kg"}

if [ "$ENV" = "production" ]; then
    CD_DIR="/var/www/crater"
    COMPOSE_CMD="docker compose"
    BASE_DOMAIN="crater.billing.mycloud.kg"
else
    CD_DIR="/var/www/crater-dev"
    COMPOSE_CMD="docker compose -p crater-dev -f docker-compose.dev.yml"
    BASE_DOMAIN="dev.crater.billing.mycloud.kg"
    TENANT_DOMAIN="test.dev.crater.billing.mycloud.kg"
fi

cd "$CD_DIR" || exit 1

echo "Окружение: $ENV"
echo "Тестируемый домен: $TENANT_DOMAIN"
echo ""

echo "1. Симуляция запроса к тенанту через Laravel:"
echo "----------------------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
\$request = \Illuminate\Http\Request::create('http://$TENANT_DOMAIN/', 'GET');
\$request->headers->set('Host', '$TENANT_DOMAIN');
app()->instance('request', \$request);

echo 'Host: ' . \$request->getHost() . PHP_EOL;
echo 'URL: ' . \$request->url() . PHP_EOL;

// Проверяем, является ли домен центральным
\$centralDomains = config('tenancy.central_domains', []);
\$isCentral = in_array(\$request->getHost(), \$centralDomains);
echo 'Is central domain: ' . (\$isCentral ? 'YES' : 'NO') . PHP_EOL;

// Проверяем домен в БД
DB::statement('SET search_path TO admin');
\$domain = DB::table('domains')->where('domain', \$request->getHost())->first();
if (\$domain) {
    echo '✓ Domain found in DB' . PHP_EOL;
    echo '  Tenant ID: ' . \$domain->tenant_id . PHP_EOL;
    
    // Пробуем инициализировать тенанта
    try {
        \$tenant = \App\Models\Tenant::find(\$domain->tenant_id);
        if (\$tenant) {
            echo '✓ Tenant model found' . PHP_EOL;
            \$tenant->run(function () {
                echo '✓ Tenant initialized' . PHP_EOL;
                echo '  Schema: ' . DB::getDefaultConnection() . PHP_EOL;
            });
        }
    } catch (\Exception \$e) {
        echo '✗ Error initializing tenant: ' . \$e->getMessage() . PHP_EOL;
    }
} else {
    echo '✗ Domain NOT found in DB!' . PHP_EOL;
}
" 2>&1
echo ""

echo "2. Проверка маршрутов для тенанта:"
echo "----------------------------------"
$COMPOSE_CMD exec -T app php artisan route:list --path=login 2>&1 | head -20
echo ""

echo "3. Тест HTTP запроса через curl (если доступен):"
echo "-------------------------------------------------"
echo "Попытка запроса к http://$TENANT_DOMAIN/..."
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" "http://$TENANT_DOMAIN/" 2>&1 || echo "curl не доступен или домен не резолвится"
echo ""

echo "4. Проверка последних ошибок в логах:"
echo "-------------------------------------"
LOG_FILE="storage/logs/laravel-$(date +%Y-%m-%d).log"
if [ -f "$LOG_FILE" ]; then
    echo "Последние 30 строк логов:"
    $COMPOSE_CMD exec -T app tail -30 "$LOG_FILE" 2>&1 | tail -20
else
    echo "Файл логов не найден"
fi
echo ""

echo "=== Тестирование завершено ==="
echo ""
echo "Попробуй открыть в браузере: http://$TENANT_DOMAIN/"
echo "И проверь логи в реальном времени:"
echo "  docker compose exec -T app tail -f storage/logs/laravel-$(date +%Y-%m-%d).log"

