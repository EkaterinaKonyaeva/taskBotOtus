<?php
namespace App\Telegram;
class TelegramService {
    private $token;
    public function __construct(){
        $this->token = getenv('TELEGRAM_BOT_TOKEN');
    }
    public function sendMessage($chatId, $text){
        if (!$this->token) return false;
        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $data = ['chat_id' => $chatId, 'text' => $text];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp;
    }
}
