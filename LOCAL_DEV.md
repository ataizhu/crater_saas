# Инструкция по локальной разработке

## Быстрый старт

### 1. Проверьте .env для локальной разработки

Убедитесь, что в `.env` установлены:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://crater.test
MAIN_DOMAIN=crater.test
SESSION_DOMAIN=.crater.test
SANCTUM_STATEFUL_DOMAINS=crater.test,*.crater.test,localhost,127.0.0.1
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=crater_saas
DB_USERNAME=crater
DB_PASSWORD=crater
CACHE_DRIVER=file
SESSION_DRIVER=cookie
```

### 2. Убедитесь, что домены добавлены в /etc/hosts

```bash
sudo nano /etc/hosts
# Добавьте (если нет):
127.0.0.1 crater.test
127.0.0.1 *.crater.test
```

### 3. Запустите Docker контейнеры

```bash
docker compose up -d --build
```

### 4. Установите зависимости и настройте Laravel

```bash
# Composer зависимости
docker compose exec app composer install

# Сгенерируйте ключ приложения (если нет)
docker compose exec app php artisan key:generate

# Запустите миграции
docker compose exec app php artisan migrate --force

# Очистите кеш
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
```

### 5. Соберите фронтенд (если нужно)

```bash
docker compose exec app npm install
docker compose exec app npm run build
```

### 6. Создайте первого админа

```bash
docker compose exec app php artisan make:filament-user
```

## Доступ к приложению

- **Центральная админка (Filament):** http://crater.test/super-admin/login
- **Тенант админка:** http://test.crater.test/admin/login (после создания тенанта)
- **База данных:** localhost:54320

## Работа с Git

### Разработка новой фичи

```bash
git checkout dev
# Делайте изменения...
git add .
git commit -m "feature: описание"
git push origin dev
# → Автоматический деплой на dev сервер
```

### Мердж в продакшн

```bash
# Через Pull Request: dev → master в GitHub
# После мерджа → автодеплой на продакшн
```

## Полезные команды

```bash
# Остановить/запустить
docker compose down
docker compose up -d

# Логи
docker compose logs -f app

# Очистка кеша
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
```
