<?php
// TaskService.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/TelegramBot.php';
	
class TaskService
{
    private PDO $pdo;
    private TelegramBot $bot;

    public function __construct()
    {
        $this->pdo = getPDO();
        $this->bot = new TelegramBot();
    }

    public function processDueTasks(): void
    {
        $now = date('Y-m-d H:i:s');

        $sql = "
            SELECT t.*, u.telegram_id
            FROM tasks t
            JOIN users u ON t.user_id = u.id
            WHERE t.is_active = 1
              AND t.next_run_at <= :now
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':now' => $now]);
        $tasks = $stmt->fetchAll();

        foreach ($tasks as $task) {
            $this->sendReminder($task);
            $this->rescheduleTask($task);
        }
    }

    private function sendReminder(array $task): void
    {
        $chatId = $task['telegram_id'];
        $text = "Напоминание: " . $task['title'];
        $this->bot->sendMessage($chatId, $text);
    }

    private function rescheduleTask(array $task): void
    {
        $nextRun = new DateTime($task['next_run_at']);

        switch ($task['schedule_type']) {
            case 'daily':
                $nextRun->modify('+1 day');
                break;
            case 'weekly':
                $nextRun->modify('+7 days');
                break;
            case 'custom':
                $minutes = (int)$task['custom_interval_minutes'];
                if ($minutes < 1) {
                    $minutes = 60;
                }
                $nextRun->modify('+' . $minutes . ' minutes');
                break;
        }

        $stmt = $this->pdo->prepare("UPDATE tasks SET next_run_at = :nr WHERE id = :id");
        $stmt->execute([
            ':nr' => $nextRun->format('Y-m-d H:i:s'),
            ':id' => $task['id'],
        ]);
    }
}
