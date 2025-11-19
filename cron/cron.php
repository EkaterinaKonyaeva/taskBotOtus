<?php
require_once __DIR__ . '/src/TaskService.php';

$service = new TaskService();
$service->processDueTasks();
