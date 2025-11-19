<?php
require __DIR__ . '/../src/bootstrap.php';
$controller = new App\Telegram\WebhookController();
$controller->handle();
