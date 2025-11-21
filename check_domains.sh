#!/bin/bash

# Быстрая проверка доменов
# ВНИМАНИЕ: Скрипт ТОЛЬКО ЧИТАЕТ данные, НЕ ВНОСИТ изменений
# Использование: ./check_domains.sh [dev|production]

ENV=${1:-dev}

if [ "$ENV" = "dev" ]; then
    COMPOSE_CMD="docker compose -p crater-dev -f docker-compose.dev.yml"
else
    COMPOSE_CMD="docker compose"
fi

echo "=== Проверка доменов ($ENV) ==="
echo ""

echo "Из .env:"
$COMPOSE_CMD exec -T app grep -E "^(MAIN_DOMAIN|SESSION_DOMAIN|SANCTUM_STATEFUL_DOMAINS)=" .env
echo ""

echo "Из Laravel config:"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
echo 'MAIN_DOMAIN: ' . config('app.main_domain');
echo 'SESSION_DOMAIN: ' . (config('session.domain') ?: 'null');
\$sanctum = config('sanctum.stateful');
echo 'SANCTUM_STATEFUL_DOMAINS: ' . (is_array(\$sanctum) ? implode(', ', \$sanctum) : 'не массив');
"

