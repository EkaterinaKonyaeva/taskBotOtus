<?php
require __DIR__ . '/../../vendor/autoload.php';
use App\Database\Database;
use App\Domain\Repositories\TaskRepository;
use App\Telegram\TelegramService;

// boot
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

$pdo = Database::connection();
$taskRepo = new TaskRepository();
$tasks = $taskRepo->getEnabledTasks();
$ts = new TelegramService();

$now = new DateTime('now');

foreach ($tasks as $task) {
    $shouldSend = false;
    $type = $task['schedule_type'];
    $nextRun = $task['next_run'];

    if ($type === 'once'){
        if ($nextRun && new DateTime($nextRun) <= $now){
            $shouldSend = true;
            $taskRepo->disable($task['id']);
        }
    } elseif ($type === 'daily'){
        if ($nextRun){
            if (new DateTime($nextRun) <= $now) $shouldSend = true;
        } else {
            $today = new DateTime('today 09:00');
            if ($today <= $now) $shouldSend = true;
        }
    } elseif ($type === 'weekly'){
        if ($nextRun && new DateTime($nextRun) <= $now) $shouldSend = true;
    }

    if ($shouldSend && $task['telegram_id']){
        $ts->sendMessage($task['telegram_id'], "Напоминание: {$task['title']}");
        $pdo->prepare('INSERT INTO reminders_sent (task_id) VALUES (?)')->execute([$task['id']]);

        if ($type === 'daily' && $nextRun){
            $dt = new DateTime($nextRun);
            $dt->modify('+1 day');
            $taskRepo->updateNextRun($task['id'], $dt->format('Y-m-d H:i:s'));
        }
        if ($type === 'weekly' && $nextRun){
            $dt = new DateTime($nextRun);
            $dt->modify('+7 day');
            $taskRepo->updateNextRun($task['id'], $dt->format('Y-m-d H:i:s'));
        }
    }
}

echo "Worker executed at " . (new DateTime())->format('Y-m-d H:i:s') . "\n";
