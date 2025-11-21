#!/bin/bash
# Скрипт для исправления SANCTUM_STATEFUL_DOMAINS

echo "=== Исправление SANCTUM_STATEFUL_DOMAINS ==="
echo ""

ENV=${1:-production}

if [ "$ENV" = "production" ]; then
    CD_DIR="/var/www/crater"
    COMPOSE_CMD="docker compose"
    MAIN_DOMAIN="crater.billing.mycloud.kg"
    SANCTUM_DOMAINS="crater.billing.mycloud.kg,*.crater.billing.mycloud.kg"
else
    CD_DIR="/var/www/crater-dev"
    COMPOSE_CMD="docker compose -p crater-dev -f docker-compose.dev.yml"
    MAIN_DOMAIN="dev.crater.billing.mycloud.kg"
    SANCTUM_DOMAINS="dev.crater.billing.mycloud.kg,*.dev.crater.billing.mycloud.kg"
fi

cd "$CD_DIR" || exit 1

echo "Окружение: $ENV"
echo "Директория: $CD_DIR"
echo ""

echo "Текущее значение SANCTUM_STATEFUL_DOMAINS:"
grep SANCTUM_STATEFUL_DOMAINS .env || echo "  ✗ Не найдено"
echo ""

echo "Обновляем на: $SANCTUM_DOMAINS"
echo ""

# Обновляем или добавляем SANCTUM_STATEFUL_DOMAINS
if grep -q "^SANCTUM_STATEFUL_DOMAINS=" .env; then
    # Заменяем существующее значение
    sed -i "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=$SANCTUM_DOMAINS|" .env
    echo "✓ Обновлено существующее значение"
else
    # Добавляем новое значение
    echo "SANCTUM_STATEFUL_DOMAINS=$SANCTUM_DOMAINS" >> .env
    echo "✓ Добавлено новое значение"
fi

echo ""
echo "Проверка:"
grep SANCTUM_STATEFUL_DOMAINS .env
echo ""

echo "Очищаем кеш конфигурации..."
$COMPOSE_CMD exec -u root app php artisan config:clear || true
echo ""

echo "Проверка из Laravel:"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
\$domains = config('sanctum.stateful', []);
echo 'SANCTUM_STATEFUL_DOMAINS: ' . implode(', ', \$domains) . PHP_EOL;
" 2>&1
echo ""

echo "=== Готово ==="
echo "Перезапусти контейнеры:"
echo "  $COMPOSE_CMD restart app"

