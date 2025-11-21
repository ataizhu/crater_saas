#!/bin/bash

# Скрипт для проверки конфигурации доменов и БД на серверах
# ВНИМАНИЕ: Скрипт ТОЛЬКО ЧИТАЕТ данные, НЕ ВНОСИТ изменений
# Использование: ./check_server_config.sh [dev|production]

ENV=${1:-dev}

echo "=== Проверка конфигурации для окружения: $ENV ==="
echo ""

# Определяем контейнер в зависимости от окружения
if [ "$ENV" = "dev" ]; then
    COMPOSE_CMD="docker compose -p crater-dev -f docker-compose.dev.yml"
    CONTAINER="crater-dev-app"
else
    COMPOSE_CMD="docker compose"
    CONTAINER="app"
fi

echo "1. Проверка .env переменных:"
echo "----------------------------"
$COMPOSE_CMD exec -T app grep -E "MAIN_DOMAIN|SESSION_DOMAIN|SANCTUM_STATEFUL_DOMAINS|APP_URL|DB_HOST|DB_DATABASE" .env | sort
echo ""

echo "2. Проверка конфигурации из Laravel (config cache):"
echo "---------------------------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
echo 'MAIN_DOMAIN: ' . config('app.main_domain') . PHP_EOL;
echo 'SESSION_DOMAIN: ' . config('session.domain') . PHP_EOL;
echo 'SANCTUM_STATEFUL_DOMAINS: ' . implode(', ', config('sanctum.stateful')) . PHP_EOL;
echo 'APP_URL: ' . config('app.url') . PHP_EOL;
echo 'DB_HOST: ' . config('database.connections.pgsql.host') . PHP_EOL;
echo 'DB_DATABASE: ' . config('database.connections.pgsql.database') . PHP_EOL;
"
echo ""

echo "3. Проверка подключения к БД:"
echo "------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
try {
    \$result = DB::selectOne('SELECT current_database() as db, current_setting(\'search_path\') as search_path');
    echo 'Текущая БД: ' . \$result->db . PHP_EOL;
    echo 'Search path: ' . \$result->search_path . PHP_EOL;
    
    // Проверка схемы admin
    \$adminExists = DB::selectOne(\"SELECT EXISTS(SELECT 1 FROM information_schema.schemata WHERE schema_name = 'admin') as exists\");
    echo 'Схема admin существует: ' . (\$adminExists->exists ? 'ДА' : 'НЕТ') . PHP_EOL;
    
    // Проверка таблицы admin_users (только чтение, без изменения search_path)
    \$usersCount = DB::table('admin.admin_users')->count();
    echo 'Количество admin_users: ' . \$usersCount . PHP_EOL;
} catch (Exception \$e) {
    echo 'ОШИБКА: ' . \$e->getMessage() . PHP_EOL;
}
"
echo ""

echo "4. Проверка центральных доменов (tenancy):"
echo "-------------------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
\$domains = config('tenancy.central_domains');
echo 'Центральные домены:' . PHP_EOL;
foreach (\$domains as \$domain) {
    echo '  - ' . \$domain . PHP_EOL;
}
"
echo ""

echo "5. Проверка текущего домена запроса (если доступен):"
echo "----------------------------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
\$request = request();
if (\$request) {
    echo 'Host: ' . \$request->getHost() . PHP_EOL;
    echo 'Scheme: ' . \$request->getScheme() . PHP_EOL;
    echo 'HTTP Host: ' . (\$request->header('Host') ?: 'не установлен') . PHP_EOL;
}
"
echo ""

echo "6. Проверка кеша конфигурации:"
echo "-------------------------------"
$COMPOSE_CMD exec -T app ls -la bootstrap/cache/config.php 2>/dev/null && echo "Кеш конфигурации существует" || echo "Кеш конфигурации отсутствует"
echo ""

echo "=== Проверка завершена ==="

