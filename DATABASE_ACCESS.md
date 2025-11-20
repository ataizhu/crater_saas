# Удаленный доступ к базе данных

## Данные для подключения

### Production окружение

```
Хост: 31.3.216.186 (или ваш IP сервера)
Порт: 54320
База данных: crater_saas
Пользователь: crater
Пароль: crater
Схема: admin (для центральных данных)
```

### Dev окружение

```
Хост: 31.3.216.186 (или ваш IP сервера)
Порт: 54321
База данных: crater_saas_dev
Пользователь: crater
Пароль: crater
Схема: admin (для центральных данных)
```

## Настройка удаленного доступа на сервере

### 1. Проверка, что порты открыты

```bash
# На сервере проверьте, что порты слушаются
netstat -tulpn | grep -E "54320|54321"

# Должно быть:
# tcp  0  0 0.0.0.0:54320  LISTEN  <docker>
# tcp  0  0 0.0.0.0:54321  LISTEN  <docker>
```

### 2. Настройка firewall (если используется)

```bash
# UFW (Ubuntu)
sudo ufw allow 54320/tcp
sudo ufw allow 54321/tcp

# firewalld (CentOS/RHEL)
sudo firewall-cmd --permanent --add-port=54320/tcp
sudo firewall-cmd --permanent --add-port=54321/tcp
sudo firewall-cmd --reload

# iptables
sudo iptables -A INPUT -p tcp --dport 54320 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 54321 -j ACCEPT
```

### 3. Настройка PostgreSQL для удаленного доступа

PostgreSQL в Docker по умолчанию слушает на `0.0.0.0`, но нужно настроить `pg_hba.conf`:

```bash
# Production
cd /var/www/crater
docker compose exec db sh -c "echo 'host all all 0.0.0.0/0 md5' >> /var/lib/postgresql/data/pg_hba.conf"
docker compose restart db

# Dev
cd /var/www/crater-dev
docker compose -p crater-dev -f docker-compose.dev.yml exec db sh -c "echo 'host all all 0.0.0.0/0 md5' >> /var/lib/postgresql/data/pg_hba.conf"
docker compose -p crater-dev -f docker-compose.dev.yml restart db
```

**Безопасность:** Для production рекомендуется ограничить доступ по IP:
```bash
# Разрешить только с вашего IP
docker compose exec db sh -c "echo 'host all all YOUR_IP/32 md5' >> /var/lib/postgresql/data/pg_hba.conf"
```

### 4. Проверка подключения

```bash
# С вашего локального компьютера
psql -h 31.3.216.186 -p 54320 -U crater -d crater_saas

# Или для dev
psql -h 31.3.216.186 -p 54321 -U crater -d crater_saas_dev
```

## Подключение через клиенты

### DBeaver / pgAdmin / DataGrip

**Production:**
- Host: `31.3.216.186`
- Port: `54320`
- Database: `crater_saas`
- Username: `crater`
- Password: `crater`
- Schema: `admin` (для центральных данных)

**Dev:**
- Host: `31.3.216.186`
- Port: `54321`
- Database: `crater_saas_dev`
- Username: `crater`
- Password: `crater`
- Schema: `admin` (для центральных данных)

### Схемы тенантов

Для доступа к данным тенантов используйте схемы:
- `tenanttest9` - данные тенанта test9
- `tenanttest10` - данные тенанта test10
- И т.д.

Пример запроса:
```sql
-- Центральные данные
SELECT * FROM admin.tenants;
SELECT * FROM admin.domains;

-- Данные тенанта test9
SELECT * FROM tenanttest9.users;
SELECT * FROM tenanttest9.companies;
```

## Безопасность

⚠️ **Важно:**
1. Используйте VPN или SSH туннель для production
2. Ограничьте доступ по IP в `pg_hba.conf`
3. Используйте сильные пароли
4. Рассмотрите использование SSL для подключений

### SSH туннель (рекомендуется для production)

```bash
# Создайте SSH туннель
ssh -L 54320:localhost:54320 root@31.3.216.186

# Затем подключайтесь через localhost
psql -h localhost -p 54320 -U crater -d crater_saas
```

## Полезные команды

```bash
# Список схем
\dn

# Список таблиц в схеме admin
\dt admin.*

# Список таблиц в схеме тенанта
\dt tenanttest9.*

# Переключение на схему
SET search_path TO admin;
-- или
SET search_path TO tenanttest9;
```

