<?php
require_once __DIR__ . '/db.php';

class TelegramBot
{
    private string $token;
    private string $apiUrl;
    private PDO $pdo;

    public function __construct()
    {
        // ВАЖНО: config.php теперь на уровень выше
        $config = require __DIR__ . '/../config.php';

        $this->token = $config['bot_token'];
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
        $this->pdo = getPDO();
    }


    public function handleUpdate(array $update): void
    {
        // Сообщения
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }

        // Коллбеки пока не используем, но можно расширить позже
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
            return;
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text   = trim($message['text'] ?? '');

        $user = $this->getOrCreateUser($message);
        $userId = (int)$user['id'];

        if ($text === '' && isset($message['entities'])) {
            $this->sendMessage($chatId, "Я понимаю только текстовые команды. Напиши /help, чтобы увидеть список.");
            return;
        }

        // Команды
        if (strpos($text, '/start') === 0) {
            $this->handleStart($chatId);
            return;
        }

        if (strpos($text, '/help') === 0) {
            $this->handleHelp($chatId);
            return;
        }

        if (strpos($text, '/profile') === 0) {
            $this->handleProfile($chatId, $user);
            return;
        }

        if (strpos($text, '/add') === 0) {
            $this->handleAddCommand($chatId, $userId, $text);
            return;
        }

        if (strpos($text, '/tasks') === 0) {
            $this->sendUserTasks($chatId, $userId);
            return;
        }

        if (strpos($text, '/done') === 0) {
            $this->handleDoneCommand($chatId, $userId, $text);
            return;
        }

        if (strpos($text, '/snooze') === 0) {
            $this->handleSnoozeCommand($chatId, $userId, $text);
            return;
        }

        // Остальное: показываем подсказку
        $this->handleHelp($chatId);
    }

    private function handleCallback(array $callback): void
    {
        // На будущее, если захочешь сделать inline-кнопки типа "✅ Выполнено"
        $callbackId = $callback['id'] ?? null;
        if ($callbackId) {
            $this->apiRequest('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => 'Функционал будет расширен админом 😉',
                'show_alert' => false,
            ]);
        }
    }

    private function handleStart(int|string $chatId): void
    {
        $text =
"Привет! Я бот-напоминалка по задачам и привычкам.

Я умею:
- регистрировать пользователей (это произошло, когда ты написал /start)
- добавлять задачи и привычки с расписанием
- присылать напоминания
- отмечать выполнение и откладывать задачи

Команды:
/help — список команд
/add — добавить задачу или привычку
/tasks — показать твои задачи и привычки
/done ID — отметить задачу как выполненную
/snooze ID — отложить напоминание
/profile — информация о твоём профиле";

        $this->sendMessage($chatId, $text);
    }

    private function handleHelp(int|string $chatId): void
    {
        $text =
"Команды бота:

/add 09:00 daily Задача
  — ежедневная задача в 09:00

/add 21:30 weekly 7 Убрать комнату
  — еженедельно, день недели 7 (воскресенье), время 21:30

/add 60 custom Попить воды
  — напоминание каждые 60 минут

/tasks
  — показать список твоих задач и привычек

/done ID
  — отметить задачу/привычку как выполненную (ID из /tasks)

/snooze ID
  — отложить ближайшее напоминание на 10 минут

/profile
  — информация о твоём профиле";

        $this->sendMessage($chatId, $text);
    }

    private function handleProfile(int|string $chatId, array $user): void
    {
        $text = "Профиль пользователя:\n";
        $text .= "Telegram ID: " . $user['telegram_id'] . "\n";
        if (!empty($user['username'])) {
            $text .= "Username: @" . $user['username'] . "\n";
        }
        if (!empty($user['first_name'])) {
            $text .= "Имя: " . $user['first_name'] . "\n";
        }
        $text .= "Дата регистрации: " . $user['created_at'];

        $this->sendMessage($chatId, $text);
    }

    // /add команда: /add 09:00 daily Помыть посуду
    // /add 21:30 weekly 3 Тренировка
    // /add 60 custom Попить воды
    private function handleAddCommand(int|string $chatId, int $userId, string $text): void
    {
        $parts = preg_split('/\s+/', $text, 5);

        if (count($parts) < 4) {
            $help =
"Использование команды /add:

1) Ежедневно:
   /add 09:00 daily Помыть посуду

2) Еженедельно (N — день недели 1-7, где 1=Пн, 7=Вс):
   /add 21:30 weekly 7 Убрать комнату

3) По интервалу (custom, каждые N минут):
   /add 60 custom Попить воды";

            $this->sendMessage($chatId, $help);
            return;
        }

        // /add 09:00 daily Название...
        // /add 21:30 weekly 3 Название...
        // /add 60 custom Название...
        $command = $parts[0]; // /add

        if ($parts[2] === 'daily') {
            // /add 09:00 daily Название...
            if (count($parts) < 4) {
                $this->sendMessage($chatId, "Неверный формат для daily. Пример:\n/add 09:00 daily Помыть посуду");
                return;
            }
            $timeStr = $parts[1];      // 09:00
            $scheduleType = 'daily';
            $weekday = null;
            $interval = null;
            $title = implode(' ', array_slice($parts, 3));
            $kind = 'habit'; // по умолчанию считаем привычкой
        } elseif ($parts[2] === 'weekly') {
            // /add 21:30 weekly 3 Название...
            if (count($parts) < 5) {
                $this->sendMessage($chatId, "Неверный формат для weekly. Пример:\n/add 21:30 weekly 3 Тренировка");
                return;
            }
            $timeStr = $parts[1];
            $scheduleType = 'weekly';
            $weekday = (int)$parts[3];
            if ($weekday < 1 || $weekday > 7) {
                $this->sendMessage($chatId, "День недели должен быть от 1 до 7 (1=Пн, 7=Вс).");
                return;
            }
            $interval = null;
            $title = $parts[4];
            $kind = 'task';
        } elseif ($parts[2] === 'custom') {
            // /add 60 custom Название...
            $interval = (int)$parts[1];
            if ($interval < 1) {
                $this->sendMessage($chatId, "Интервал в минутах должен быть положительным числом.");
                return;
            }
            $scheduleType = 'custom';
            $timeStr = date('H:i'); // просто текущее время
            $weekday = null;
            $title = implode(' ', array_slice($parts, 3));
            $kind = 'habit';
        } else {
            $this->sendMessage($chatId, "Неизвестный тип расписания: {$parts[2]}.\nРазрешены: daily, weekly, custom.");
            return;
        }

        if ($title === '') {
            $this->sendMessage($chatId, "Название задачи/привычки не может быть пустым.");
            return;
        }

        // Рассчитываем next_run_at
        date_default_timezone_set('Europe/Moscow'); // при необходимости поменяй
        $now = new DateTime();
        $nextRun = clone $now;

        if ($scheduleType === 'daily' || $scheduleType === 'weekly') {
            [$h, $m] = array_pad(explode(':', $timeStr), 2, '0');
            $h = (int)$h;
            $m = (int)$m;
            $nextRun->setTime($h, $m, 0);

            if ($scheduleType === 'daily') {
                if ($nextRun <= $now) {
                    $nextRun->modify('+1 day');
                }
            } elseif ($scheduleType === 'weekly') {
                $todayWeekday = (int)$now->format('N'); // 1-7
                $targetWeekday = $weekday ?? $todayWeekday;
                $daysToAdd = ($targetWeekday - $todayWeekday + 7) % 7;
                if ($daysToAdd === 0 && $nextRun <= $now) {
                    $daysToAdd = 7;
                }
                if ($daysToAdd > 0) {
                    $nextRun->modify('+' . $daysToAdd . ' days');
                }
            }
        } elseif ($scheduleType === 'custom') {
            $nextRun = clone $now;
            $nextRun->modify('+' . $interval . ' minutes');
        }

        // Сохраняем в БД
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (user_id, kind, title, schedule_type, time_of_day, weekday, custom_interval_minutes, next_run_at)
            VALUES (:uid, :kind, :title, :type, :tod, :weekday, :interval, :nrun)
        ");

        $stmt->execute([
            ':uid'     => $userId,
            ':kind'    => $kind,
            ':title'   => $title,
            ':type'    => $scheduleType,
            ':tod'     => $timeStr,
            ':weekday' => $weekday,
            ':interval'=> $interval,
            ':nrun'    => $nextRun->format('Y-m-d H:i:s'),
        ]);

        $this->sendMessage($chatId, "Задача/привычка добавлена ✅\n\nНазвание: {$title}\nТип: {$scheduleType}");
    }

    private function handleDoneCommand(int|string $chatId, int $userId, string $text): void
    {
        $parts = preg_split('/\s+/', $text, 3);
        if (count($parts) < 2) {
            $this->sendMessage($chatId, "Использование:\n/done ID\n\nID можно посмотреть в списке /tasks");
            return;
        }

        $taskId = (int)$parts[1];
        if ($taskId < 1) {
            $this->sendMessage($chatId, "Некорректный ID задачи.");
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $taskId, ':uid' => $userId]);
        $task = $stmt->fetch();

        if (!$task) {
            $this->sendMessage($chatId, "Задача с таким ID не найдена.");
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare("UPDATE tasks SET last_completed_at = :now WHERE id = :id")
            ->execute([':now' => $now, ':id' => $taskId]);

        $this->sendMessage($chatId, "Задача/привычка отмечена как выполненная ✅\n\n{$task['title']}");
    }

    private function handleSnoozeCommand(int|string $chatId, int $userId, string $text): void
    {
        $parts = preg_split('/\s+/', $text, 3);
        if (count($parts) < 2) {
            $this->sendMessage($chatId, "Использование:\n/snooze ID\n\nID можно посмотреть в списке /tasks");
            return;
        }

        $taskId = (int)$parts[1];
        if ($taskId < 1) {
            $this->sendMessage($chatId, "Некорректный ID задачи.");
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $taskId, ':uid' => $userId]);
        $task = $stmt->fetch();

        if (!$task) {
            $this->sendMessage($chatId, "Задача с таким ID не найдена.");
            return;
        }

        $now = new DateTime();
        $now->modify('+10 minutes'); // отложить на 10 минут

        $this->pdo->prepare("UPDATE tasks SET next_run_at = :nr WHERE id = :id")
            ->execute([':nr' => $now->format('Y-m-d H:i:s'), ':id' => $taskId]);

        $this->sendMessage($chatId, "Напоминание отложено на 10 минут ⏰\n\n{$task['title']}");
    }

    private function getOrCreateUser(array $message): array
    {
        $chat = $message['chat'];
        $telegramId = $chat['id'];
        $username = $chat['username'] ?? null;
        $firstName = $chat['first_name'] ?? null;

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE telegram_id = :tid");
        $stmt->execute([':tid' => $telegramId]);
        $user = $stmt->fetch();
        if ($user) {
            return $user;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO users (telegram_id, username, first_name)
            VALUES (:tid, :username, :first_name)
        ");
        $stmt->execute([
            ':tid' => $telegramId,
            ':username' => $username,
            ':first_name' => $firstName,
        ]);

        $id = $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        $data = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        $this->apiRequest('sendMessage', $data);
    }

    private function apiRequest(string $method, array $data): void
    {
        $ch = curl_init($this->apiUrl . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function sendUserTasks(int|string $chatId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tasks
            WHERE user_id = :uid
            ORDER BY time_of_day
        ");
        $stmt->execute([':uid' => $userId]);

        $tasks = $stmt->fetchAll();
        if (!$tasks) {
            $this->sendMessage($chatId, "У тебя пока нет задач и привычек.\nДобавь через /add.");
            return;
        }

        $lines = ["Твои задачи и привычки:"];
        foreach ($tasks as $task) {
            $line = "#{$task['id']} — " . $task['title'];

            $line .= " | тип: " . $task['schedule_type'];

            if ($task['schedule_type'] === 'daily') {
                $line .= " | ежедневно в " . substr($task['time_of_day'], 0, 5);
            } elseif ($task['schedule_type'] === 'weekly') {
                $line .= " | еженедельно (день {$task['weekday']}) в " . substr($task['time_of_day'], 0, 5);
            } elseif ($task['schedule_type'] === 'custom') {
                $line .= " | каждые {$task['custom_interval_minutes']} мин";
            }

            if (!empty($task['last_completed_at'])) {
                $line .= " | последний раз выполнена: " . $task['last_completed_at'];
            }

            $lines[] = $line;
        }

        $this->sendMessage($chatId, implode("\n", $lines));
    }
}
