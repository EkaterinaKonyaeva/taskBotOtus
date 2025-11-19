<?php
namespace App\Telegram;
use App\Domain\Repositories\UserRepository;
use App\Domain\Repositories\TaskRepository;

class WebhookController {
    public function handle(){
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) { http_response_code(400); echo 'no payload'; return; }
        $ts = new TelegramService();
        $userRepo = new UserRepository();
        if (isset($payload['message'])){
            $m = $payload['message'];
            $chatId = $m['chat']['id'];
            $text = $m['text'] ?? '';
            if (strpos($text, '/start') === 0){
                $exists = $userRepo->findByTelegramId($chatId);
                if (!$exists) {
                    $userRepo->createFromTelegram($chatId, $m['from']['username'] ?? null);
                    $ts->sendMessage($chatId, 'Привет! Ты зарегистрирован.');
                } else {
                    $ts->sendMessage($chatId, 'Привет снова!');
                }
                http_response_code(200);
                echo 'ok';
                return;
            }
            if (strpos($text, '/task') === 0){
                $raw = trim(substr($text,5));
                $parts = array_map('trim', explode('|', $raw));
                $title = $parts[0] ?? 'Задача';
                $next = $parts[1] ?? null;
                $type = $parts[2] ?? 'once';
                $user = $userRepo->findByTelegramId($chatId);
                if (!$user){ $ts->sendMessage($chatId, 'Отправьте /start чтобы зарегистрироваться'); return; }
                $taskRepo = new TaskRepository();
                $taskRepo->create($user['id'], $title, $next, $type);
                $ts->sendMessage($chatId, "Задача создана: {$title}");
                echo 'ok';
                return;
            }
            $ts->sendMessage($chatId, "Команды:\n/start\n/task Title|YYYY-MM-DD HH:MM|daily|weekly|once");
            echo 'ok';
            return;
        }
        echo 'ok';
    }
}
