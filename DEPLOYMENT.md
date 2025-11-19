# Инструкция по деплою

## Настройка GitHub Secrets

### 1. Генерация SSH ключа для GitHub Actions

На сервере выполните:

```bash
# Создайте SSH ключ специально для GitHub Actions
ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/github_actions -N ""

# Покажите публичный ключ - его нужно добавить в authorized_keys
cat ~/.ssh/github_actions.pub

# Добавьте публичный ключ в authorized_keys
cat ~/.ssh/github_actions.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys

# Покажите приватный ключ - его нужно добавить в GitHub Secrets
cat ~/.ssh/github_actions
```

### 2. Добавление секретов в GitHub

В GitHub: Settings → Secrets and variables → Actions → New repository secret

**Для продакшн (master ветка):**
- `PROD_HOST`: `31.3.216.186`
- `PROD_USERNAME`: `root`
- `PROD_SSH_KEY`: (приватный ключ из шага 1)

**Для dev (dev ветка):**
- `DEV_HOST`: `31.3.216.186` (или отдельный IP если есть)
- `DEV_USERNAME`: `root`
- `DEV_SSH_KEY`: (тот же приватный ключ или другой)

## Настройка dev окружения на сервере

**Важно:** Dev и Production работают в отдельных директориях:
- **Production:** `/var/www/crater` (ветка `master`)
- **Dev:** `/var/www/crater-dev` (ветка `dev`)

### Настройка dev окружения

```bash
# Создайте директорию для dev
mkdir -p /var/www/crater-dev
cd /var/www/crater-dev

# Клонируйте репозиторий
git clone https://github.com/ataizhu/crater_saas.git .

# Переключитесь на dev ветку
git checkout dev

# Создайте .env для dev
cp .env.example .env
# Отредактируйте .env - измените домен на dev.crater.billing.mycloud.kg

# Пример .env для dev:
# APP_ENV=testing
# APP_DEBUG=true
# APP_URL=http://dev.crater.billing.mycloud.kg
# MAIN_DOMAIN=dev.crater.billing.mycloud.kg
# SESSION_DOMAIN=.dev.crater.billing.mycloud.kg

# Запустите контейнеры с dev конфигурацией
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build

# Выполните миграции
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -u root app php artisan migrate --force

# Настройте Nginx для dev домена (если нужен отдельный домен)
# Или используйте порт 8080: http://crater.billing.mycloud.kg:8080
```

### Различия dev и production

| Параметр | Production | Dev |
|----------|-----------|-----|
| Директория | `/var/www/crater` | `/var/www/crater-dev` |
| Ветка Git | `master` | `dev` |
| PHP-FPM порт | `9000` | `9001` |
| PostgreSQL порт | `54320` | `54321` |
| Nginx порт | `80` | `8080` |
| APP_ENV | `production` | `testing` |
| APP_DEBUG | `false` | `true` |

## Workflow

1. **Локальная разработка:**
   ```bash
   git checkout dev
   # Делайте изменения
   git commit -m "feature: ..."
   git push origin dev
   # Автоматический деплой на dev сервер
   ```

2. **Тестирование на dev сервере:**
   - Проверьте на dev.crater.billing.mycloud.kg

3. **Мердж в main через Pull Request:**
   - Создайте PR: dev → master
   - После одобрения и мерджа → автоматический деплой на продакшн

4. **Прямой мердж (если нужно быстро):**
   ```bash
   git checkout master
   git merge dev
   git push origin master
   # Автоматический деплой на продакшн
   ```

## Настройка локальной разработки

### 1. Обновить .env для локальной разработки

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://crater.test
MAIN_DOMAIN=crater.test
SESSION_DOMAIN=.crater.test
```

### 2. Добавить домены в /etc/hosts

```bash
sudo nano /etc/hosts
# Добавьте:
127.0.0.1 crater.test
127.0.0.1 *.crater.test
```

### 3. Запустить локально

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app npm run build
```

## Полезные команды

### Проверка статуса деплоя
GitHub → Actions → проверьте статус последнего деплоя

### Откат деплоя (если что-то пошло не так)
```bash
cd /var/www/crater
git reset --hard HEAD~1
docker compose exec -u root app php artisan config:clear
docker compose exec -u root app php artisan cache:clear
```

