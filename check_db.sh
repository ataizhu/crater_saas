#!/bin/bash

# Проверка подключения к БД
# ВНИМАНИЕ: Скрипт ТОЛЬКО ЧИТАЕТ данные, НЕ ВНОСИТ изменений
# Использование: ./check_db.sh [dev|production]

ENV=${1:-dev}

if [ "$ENV" = "dev" ]; then
    COMPOSE_CMD="docker compose -p crater-dev -f docker-compose.dev.yml"
    EXPECTED_DB="crater_saas_dev"
else
    COMPOSE_CMD="docker compose"
    EXPECTED_DB="crater_saas"
fi

echo "=== Проверка БД ($ENV) ==="
echo "Ожидаемая БД: $EXPECTED_DB"
echo ""

$COMPOSE_CMD exec -T app php artisan tinker --execute="
echo '1. Подключение к БД:' . PHP_EOL;
try {
    \$db = DB::selectOne('SELECT current_database() as db');
    echo '   Текущая БД: ' . \$db->db . PHP_EOL;
    echo '   Ожидаемая: $EXPECTED_DB' . PHP_EOL;
    echo '   Статус: ' . (\$db->db === '$EXPECTED_DB' ? '✓ OK' : '✗ НЕ СОВПАДАЕТ') . PHP_EOL;
} catch (Exception \$e) {
    echo '   ✗ ОШИБКА: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL . '2. Search path:' . PHP_EOL;
try {
    \$searchPath = DB::selectOne(\"SELECT current_setting('search_path') as search_path\");
    echo '   Текущий: ' . \$searchPath->search_path . PHP_EOL;
} catch (Exception \$e) {
    echo '   ✗ ОШИБКА: ' . \$e->getMessage() . PHP_EOL;
}

echo PHP_EOL . '3. Схема admin:' . PHP_EOL;
try {
    \$adminExists = DB::selectOne(\"SELECT EXISTS(SELECT 1 FROM information_schema.schemata WHERE schema_name = 'admin') as exists\");
    echo '   Существует: ' . (\$adminExists->exists ? '✓ ДА' : '✗ НЕТ') . PHP_EOL;
    
    if (\$adminExists->exists) {
        // Чтение без изменения search_path
        \$usersCount = DB::table('admin.admin_users')->count();
        echo '   Admin users: ' . \$usersCount . PHP_EOL;
    }
} catch (Exception \$e) {
    echo '   ✗ ОШИБКА: ' . \$e->getMessage() . PHP_EOL;
}
"

