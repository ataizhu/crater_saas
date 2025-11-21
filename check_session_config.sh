#!/bin/bash
# Скрипт для проверки конфигурации сессий

echo "=== Проверка конфигурации сессий ==="
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
echo ""

echo "1. Проверка .env переменных:"
echo "----------------------------"
echo "SESSION_DOMAIN:"
grep SESSION_DOMAIN .env || echo "  ✗ Не найдено"
echo ""
echo "SANCTUM_STATEFUL_DOMAINS:"
grep SANCTUM_STATEFUL_DOMAINS .env || echo "  ✗ Не найдено"
echo ""
echo "SESSION_COOKIE:"
grep SESSION_COOKIE .env || echo "  ✗ Не найдено"
echo ""

echo "2. Проверка конфигурации из Laravel:"
echo "-------------------------------------"
$COMPOSE_CMD exec -T app php artisan tinker --execute="
echo 'SESSION_DOMAIN: ' . config('session.domain') . PHP_EOL;
echo 'SANCTUM_STATEFUL_DOMAINS: ' . implode(', ', config('sanctum.stateful', [])) . PHP_EOL;
echo 'SESSION_COOKIE: ' . config('session.cookie') . PHP_EOL;
echo 'SESSION_DRIVER: ' . config('session.driver') . PHP_EOL;
" 2>&1
echo ""

echo "3. Тест работы cookies между доменами:"
echo "--------------------------------------"
echo "Проверь в браузере (DevTools → Application → Cookies):"
echo "  - Должен быть cookie с domain: .crater.billing.mycloud.kg (с точкой в начале)"
echo "  - Cookie должен быть доступен на всех поддоменах"
echo ""

echo "=== Проверка завершена ==="

