#!/bin/bash
# Скрипт для проверки схемы тенанта в БД

echo "=== Проверка схемы тенанта в БД ==="
echo ""

ENV=${1:-production}

if [ "$ENV" = "production" ]; then
    CD_DIR="/var/www/crater"
    COMPOSE_CMD="docker compose"
    DB_NAME="crater_saas"
else
    CD_DIR="/var/www/crater-dev"
    COMPOSE_CMD="docker compose -p crater-dev -f docker-compose.dev.yml"
    DB_NAME="crater_saas_dev"
fi

cd "$CD_DIR" || exit 1

echo "Окружение: $ENV"
echo "База данных: $DB_NAME"
echo ""

echo "1. Проверка существования схемы тенанта:"
echo "----------------------------------------"
$COMPOSE_CMD exec -T db psql -U crater -d "$DB_NAME" -c "
SELECT schema_name 
FROM information_schema.schemata 
WHERE schema_name LIKE 'tenant%' 
ORDER BY schema_name;
" 2>&1
echo ""

echo "2. Проверка таблиц в схеме тенанта 'tenanttest':"
echo "------------------------------------------------"
$COMPOSE_CMD exec -T db psql -U crater -d "$DB_NAME" -c "
SET search_path TO tenanttest;
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'tenanttest' 
ORDER BY table_name 
LIMIT 20;
" 2>&1
echo ""

echo "3. Проверка пользователей в схеме тенанта:"
echo "------------------------------------------"
$COMPOSE_CMD exec -T db psql -U crater -d "$DB_NAME" -c "
SET search_path TO tenanttest;
SELECT COUNT(*) as users_count FROM users;
" 2>&1
echo ""

echo "4. Проверка инициализации тенанта через Laravel:"
echo "------------------------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
DB::statement('SET search_path TO admin');
\$tenant = DB::table('tenants')->where('id', 'test')->first();
if (\$tenant) {
    echo 'Tenant найден: ' . \$tenant->id . PHP_EOL;
    try {
        \$tenantModel = \App\Models\Tenant::find('test');
        if (\$tenantModel) {
            echo 'Tenant model загружен' . PHP_EOL;
            \$tenantModel->run(function () {
                echo 'Tenant инициализирован' . PHP_EOL;
                echo 'Current schema: ' . DB::getDefaultConnection() . PHP_EOL;
                echo 'Search path: ' . DB::selectOne('SHOW search_path')->search_path . PHP_EOL;
                \$usersCount = DB::table('users')->count();
                echo 'Users in tenant: ' . \$usersCount . PHP_EOL;
            });
        } else {
            echo '✗ Tenant model НЕ найден!' . PHP_EOL;
        }
    } catch (\Exception \$e) {
        echo '✗ Ошибка при инициализации: ' . \$e->getMessage() . PHP_EOL;
    }
} else {
    echo '✗ Tenant НЕ найден в БД!' . PHP_EOL;
}
" 2>&1
echo ""

echo "5. Проверка последних запросов к тенанту в логах:"
echo "-------------------------------------------------"
LOG_FILE="storage/logs/laravel-$(date +%Y-%m-%d).log"
if [ -f "$LOG_FILE" ]; then
    echo "Последние записи с 'tenant' или 'test.crater':"
    $COMPOSE_CMD exec -T app tail -100 "$LOG_FILE" | grep -i "tenant\|test\.crater" | tail -10 || echo "Записей не найдено"
else
    echo "Файл логов не найден"
fi
echo ""

echo "=== Проверка завершена ==="

