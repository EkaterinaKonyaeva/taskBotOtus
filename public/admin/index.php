<?php
session_start();

$config = require __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/db.php';
$pdo = getPDO();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// --- ЛОГИН ---
if (!isset($_SESSION['admin_logged_in'])) {

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $login = $_POST['login'] ?? '';
        $pass  = $_POST['password'] ?? '';

        if ($login === $config['admin']['login'] && $pass === $config['admin']['password']) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    }

    ?>
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <title>Вход в админку</title>
        <style>
            body {
                margin: 0;
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background: #0f172a;
                color: #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .card {
                background: #111827;
                padding: 32px;
                border-radius: 16px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.5);
                width: 100%;
                max-width: 420px;
            }
            h1 {
                margin-top: 0;
                margin-bottom: 16px;
                font-size: 24px;
                text-align: center;
            }
            .error {
                color: #f97373;
                margin-bottom: 12px;
                text-align: center;
            }
            label {
                display: block;
                margin-bottom: 8px;
                font-size: 14px;
            }
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 10px 12px;
                border-radius: 8px;
                border: 1px solid #374151;
                background: #030712;
                color: #e5e7eb;
                margin-top: 4px;
                box-sizing: border-box;
            }
            button {
                width: 100%;
                padding: 10px 12px;
                border-radius: 999px;
                border: none;
                margin-top: 16px;
                background: linear-gradient(135deg, #22c55e, #16a34a);
                color: #ecfdf5;
                font-weight: 600;
                cursor: pointer;
                transition: transform .1s ease, box-shadow .1s ease, opacity .2s;
            }
            button:hover {
                transform: translateY(-1px);
                box-shadow: 0 10px 20px rgba(34,197,94,0.3);
                opacity: .95;
            }
            .hint {
                margin-top: 16px;
                font-size: 12px;
                color: #9ca3af;
                text-align: center;
            }
        </style>
    </head>
    <body>
    <div class="card">
        <h1>Админ-панель бота</h1>
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <label>
                Логин
                <input type="text" name="login" autocomplete="username">
            </label>
            <label>
                Пароль
                <input type="password" name="password" autocomplete="current-password">
            </label>
            <button type="submit">Войти</button>
        </form>
        <div class="hint">
            Используй данные, указанные в config.php → ['admin'].
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// --- ЕСЛИ УЖЕ В СИСТЕМЕ ---

$stmt = $pdo->query("
    SELECT t.*, u.telegram_id, u.first_name
    FROM tasks t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC
");
$tasks = $stmt->fetchAll();

$stmtUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmtUsers->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Админка: задачи</title>
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #020617;
            color: #e5e7eb;
        }
        header {
            background: linear-gradient(135deg, #0ea5e9, #22c55e);
            color: #ecfeff;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header h1 {
            margin: 0;
            font-size: 20px;
        }
        header a {
            color: #ecfeff;
            text-decoration: none;
            font-size: 14px;
        }
        .container {
            max-width: 1100px;
            margin: 24px auto 40px;
            padding: 0 16px;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.4fr);
            gap: 24px;
        }
        .card {
            background: #020617;
            border-radius: 16px;
            border: 1px solid #1f2937;
            padding: 16px 18px;
            box-shadow: 0 18px 35px rgba(0,0,0,0.6);
        }
        .card h2 {
            margin: 0 0 12px;
            font-size: 18px;
        }
        label {
            display: block;
            font-size: 13px;
            margin-bottom: 8px;
        }
        select, input[type="text"], input[type="number"], input[type="time"] {
            width: 100%;
            box-sizing: border-box;
            margin-top: 4px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid #374151;
            background: #020617;
            color: #e5e7eb;
            font-size: 13px;
        }
        button {
            padding: 8px 14px;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: transform .1s ease, box-shadow .1s ease, opacity .15s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #22c55e);
            color: #ecfdf5;
            margin-top: 10px;
            width: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(34,197,94,0.35);
            opacity: .96;
        }
        .btn-small {
            padding: 4px 10px;
            font-size: 11px;
            margin-right: 4px;
        }
        .btn-toggle {
            background: #1e293b;
            color: #e5e7eb;
        }
        .btn-toggle-active {
            background: #22c55e;
            color: #022c22;
        }
        .btn-delete {
            background: #b91c1c;
            color: #fee2e2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 8px 6px;
            border-bottom: 1px solid #1f2937;
            vertical-align: top;
        }
        th {
            text-align: left;
            font-weight: 600;
            color: #9ca3af;
        }
        tr:hover td {
            background: #020617;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .badge-daily {
            background: rgba(34,197,94,0.15);
            color: #4ade80;
        }
        .badge-weekly {
            background: rgba(59,130,246,0.18);
            color: #93c5fd;
        }
        .badge-custom {
            background: rgba(251,191,36,0.18);
            color: #facc15;
        }
        .muted {
            color: #6b7280;
            font-size: 11px;
        }
        @media (max-width: 900px) {
            .container {
                grid-template-columns: minmax(0,1fr);
            }
        }
    </style>
</head>
<body>
<header>
    <h1>Админка бота-напоминалки</h1>
    <a href="index.php?logout=1">Выйти</a>
</header>

<div class="container">
    <div class="card">
        <h2>Создать задачу / привычку</h2>
        <form method="post" action="tasks.php">
            <label>
                Пользователь:
                <select name="user_id">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= htmlspecialchars($u['first_name'] ?: $u['telegram_id']) ?>
                            (<?= $u['telegram_id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Название задачи / привычки:
                <input type="text" name="title" required placeholder="Например: Выпить стакан воды">
            </label>

            <label>
                Тип расписания:
                <select name="schedule_type">
                    <option value="daily">Ежедневно</option>
                    <option value="weekly">Еженедельно</option>
                    <option value="custom">По интервалу (мин.)</option>
                </select>
            </label>

            <label>
                Время (HH:MM) — для daily/weekly:
                <input type="time" name="time_of_day" value="09:00">
            </label>

            <label>
                День недели (1–7, для weekly, 1=Пн):
                <input type="number" name="weekday" min="1" max="7">
            </label>

            <label>
                Интервал в минутах (для custom):
                <input type="number" name="custom_interval_minutes" min="1">
            </label>

            <button type="submit" class="btn-primary">Создать</button>
            <p class="muted">Пользователи также могут создавать задачи самостоятельно через команду /add в боте.</p>
        </form>
    </div>

    <div class="card">
        <h2>Список задач</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Пользователь</th>
                <th>Название</th>
                <th>Расписание</th>
                <th>Next run</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($tasks as $t): ?>
                <tr>
                    <td>#<?= (int)$t['id'] ?></td>
                    <td>
                        <?= htmlspecialchars($t['first_name'] ?: $t['telegram_id']) ?><br>
                        <span class="muted"><?= $t['telegram_id'] ?></span>
                    </td>
                    <td><?= htmlspecialchars($t['title']) ?></td>
                    <td>
                        <?php if ($t['schedule_type'] === 'daily'): ?>
                            <span class="badge badge-daily">daily</span><br>
                            <span class="muted">каждый день в <?= htmlspecialchars(substr($t['time_of_day'], 0, 5)) ?></span>
                        <?php elseif ($t['schedule_type'] === 'weekly'): ?>
                            <span class="badge badge-weekly">weekly</span><br>
                            <span class="muted">день <?= (int)$t['weekday'] ?> в <?= htmlspecialchars(substr($t['time_of_day'], 0, 5)) ?></span>
                        <?php else: ?>
                            <span class="badge badge-custom">custom</span><br>
                            <span class="muted">каждые <?= (int)$t['custom_interval_minutes'] ?> мин.</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="muted"><?= htmlspecialchars($t['next_run_at']) ?></span>
                    </td>
                    <td>
                        <?php if (!empty($t['last_completed_at'])): ?>
                            <span class="muted">Выполнена: <?= htmlspecialchars($t['last_completed_at']) ?></span><br>
                        <?php endif; ?>
                        <?php if ($t['is_active']): ?>
                            <span style="color:#4ade80;">АКТИВНА</span>
                        <?php else: ?>
                            <span style="color:#f97373;">ВЫКЛ.</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="tasks.php" style="display:inline">
                            <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="action" value="toggle">
                            <button type="submit"
                                    class="btn-small <?= $t['is_active'] ? 'btn-toggle-active' : 'btn-toggle' ?>">
                                <?= $t['is_active'] ? 'Выключить' : 'Включить' ?>
                            </button>
                        </form>
                        <form method="post" action="tasks.php" style="display:inline"
                              onsubmit="return confirm('Удалить задачу?');">
                            <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-small btn-delete">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>
