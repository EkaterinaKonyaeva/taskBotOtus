<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../src/db.php';
$pdo = getPDO();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ВКЛ/ВЫКЛ или УДАЛЕНИЕ
    if (!empty($_POST['action']) && !empty($_POST['task_id'])) {
        $id = (int)$_POST['task_id'];

        if ($_POST['action'] === 'toggle') {
            $pdo->prepare("UPDATE tasks SET is_active = 1 - is_active WHERE id = :id")
                ->execute([':id' => $id]);
        } elseif ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM tasks WHERE id = :id")
                ->execute([':id' => $id]);
        }

        header('Location: index.php');
        exit;
    }

    // СОЗДАНИЕ НОВОЙ ЗАДАЧИ
    $userId  = (int)($_POST['user_id'] ?? 0);
    $title   = trim($_POST['title'] ?? '');
    $type    = $_POST['schedule_type'] ?? 'daily';
    $time    = $_POST['time_of_day'] ?? '09:00';
    $weekday = !empty($_POST['weekday']) ? (int)$_POST['weekday'] : null;
    $interval = !empty($_POST['custom_interval_minutes']) ? (int)$_POST['custom_interval_minutes'] : null;

    if ($userId && $title !== '') {
        date_default_timezone_set('Europe/Moscow'); // или твой часовой пояс

        $now = new DateTime();          // сейчас
        $nextRun = clone $now;

        // парсим время
        $parts = explode(':', $time);
        $h = (int)($parts[0] ?? 9);
        $m = (int)($parts[1] ?? 0);
        $nextRun->setTime($h, $m, 0);

        if ($type === 'daily') {
            // если уже прошло сегодня — переносим на завтра
            if ($nextRun <= $now) {
                $nextRun->modify('+1 day');
            }
        } elseif ($type === 'weekly') {
            // 1–7 (1 = Пн... 7 = Вс)
            if ($weekday < 1 || $weekday > 7) {
                $weekday = (int)$now->format('N'); // по умолчанию сегодня
            }
            // находим ближайший нужный день недели
            $todayWeekday = (int)$now->format('N');
            $daysToAdd = ($weekday - $todayWeekday + 7) % 7;
            if ($daysToAdd === 0 && $nextRun <= $now) {
                $daysToAdd = 7;
            }
            if ($daysToAdd > 0) {
                $nextRun->modify('+' . $daysToAdd . ' days');
            }
        } elseif ($type === 'custom') {
            // интервал в минутах — от текущего момента
            if ($interval < 1) {
                $interval = 60;
            }
            $nextRun = clone $now;
            $nextRun->modify('+' . $interval . ' minutes');
        } else {
            // по умолчанию daily
            if ($nextRun <= $now) {
                $nextRun->modify('+1 day');
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO tasks (user_id, title, schedule_type, time_of_day, weekday, custom_interval_minutes, next_run_at)
            VALUES (:uid, :title, :type, :tod, :weekday, :interval, :nrun)
        ");

        $stmt->execute([
            ':uid'     => $userId,
            ':title'   => $title,
            ':type'    => $type,
            ':tod'     => $time,
            ':weekday' => $weekday,
            ':interval'=> $interval,
            ':nrun'    => $nextRun->format('Y-m-d H:i:s'),
        ]);
    }

    header('Location: index.php');
    exit;
}

header('Location: index.php');
