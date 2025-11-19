

#  Функционал

- Регистрация пользователей через `/start`
- Добавление задач и привычек с повторением:
    - ежедневно (`daily`)
    - еженедельно (`weekly`)
    - по интервалу в минутах (`custom`)
- Автоматические напоминания через cron или Docker scheduler
- Поддержка нескольких пользователей
- Команды:
    - `/tasks`
    - `/done ID`
    - `/snooze ID`
    - `/profile`
- Веб-админка (красивая тёмная тема):
    - создаёт задачи пользователям
    - редактирует/удаляет задачи
    - пауза/включение задач
    - просмотр всех пользователей

---


# ⚙️ Настройка проекта (БЕЗ Docker)

## 1. Клонирование проекта

```bash
git clone <repo-url> tg_reminder_bot
cd tg_reminder_bot
```

---

## 2. Настрой config.php

```php
'db' => [
    'host'    => 'localhost',
    'dbname'  => 'tg_reminder',
    'user'    => 'tg_user',
    'pass'    => 'tg_password',
    'charset' => 'utf8mb4',
],

'bot_token' => 'YOUR_TELEGRAM_BOT_TOKEN',

'admin' => [
    'login'    => 'admin',
    'password' => 'admin123',
],
```

---


## 4. Nginx конфигурация

```nginx
server {
    listen 80;
    server_name your-domain.example;

    root /var/www/tg_reminder_bot/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

---

## 5. Webhook Telegram

```bash
BOT="YOUR_TELEGRAM_BOT_TOKEN"
curl -X POST "https://api.telegram.org/bot$BOT/setWebhook" \
  -d "url=https://your-domain.example/webhook.php"
```

---

## 6. Cron (рассылка уведомлений)

```bash
crontab -e
```

Добавить:

```
* * * * * /usr/bin/php /var/www/tg_reminder_bot/cron.php > /dev/null 2>&1
```

---

#  Запуск через Docker

## 1. В config.php поставить host = db

```php
'host' => 'db',
```

---

## 2. Запуск всех контейнеров

```bash
docker compose up -d --build
```

После запуска:

| Сервис | Адрес |
|--------|--------|
| Веб-приложение | http://localhost:8080 |
| Админка | http://localhost:8080/admin |
| PHP | контейнер `tg_app` |
| Cron | контейнер `tg_scheduler` |
| База | контейнер `tg_db` |

---

## 3. Инициализация БД

```bash
docker compose exec db mysql -u root -proot
```

Вставить SQL, указанный выше.

---

## 4. Cron в Docker

Контейнер `scheduler` каждые 60 секунд выполняет:

```
php /var/www/html/cron.php
```

Системный cron на хосте не нужен.

---

# Команды бота

### `/start`
Регистрация пользователя

### `/help`
Список команд

### `/add`
Добавление задачи  
Примеры:

```
/add 09:00 daily Зарядка
/add 21:30 weekly 7 Уборка
/add 60 custom Попить воду
```

### `/tasks`
Показать задачи

### `/done 5`
Отметить выполненной

### `/snooze 5`
Отложить на 10 минут

### `/profile`
Профиль

---

#  Админка

URL:

```
https://your-domain.example/admin/
```

Доступы:

```php
'admin' => [
    'login' => 'admin',
    'password' => 'admin123'
]
```

Позволяет:

- Создавать задачи
- Управлять пользователями
- Включать/выключать задачи
- Удалять их
- Смотреть расписание


