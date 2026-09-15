<?php

const DB_FILE = __DIR__ . '/concierge.sqlite';
const USER_COOKIE = 'concierge_user';

function get_db(): PDO {
    $is_new = !file_exists(DB_FILE);
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($is_new) {
        $db->exec('
            CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL DEFAULT \'\',
                text TEXT NOT NULL,
                important INTEGER NOT NULL DEFAULT 0,
                urgent INTEGER NOT NULL DEFAULT 0,
                class INTEGER NOT NULL,
                due_date TEXT NOT NULL DEFAULT \'\',
                completed INTEGER NOT NULL DEFAULT 0
            )
        ');
    } else {
        // Migrate older databases that predate the username/completed columns.
        $columns = $db->query('PRAGMA table_info(tasks)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('username', $columns, true)) {
            $db->exec('ALTER TABLE tasks ADD COLUMN username TEXT NOT NULL DEFAULT \'\'');
        }
        if (!in_array('completed', $columns, true)) {
            $db->exec('ALTER TABLE tasks ADD COLUMN completed INTEGER NOT NULL DEFAULT 0');
        }
    }
    return $db;
}

function classify(bool $important, bool $urgent): int {
    if ($important && $urgent) {
        return 1;
    }
    if ($important) {
        return 2;
    }
    if ($urgent) {
        return 3;
    }
    return 4;
}

function is_due_soon(string $due_date, int $days = 7): bool {
    if ($due_date === '') {
        return false;
    }
    $due = strtotime($due_date);
    if ($due === false) {
        return false;
    }
    $threshold = strtotime("+{$days} days", strtotime(date('Y-m-d')));
    return $due <= $threshold;
}

$db = get_db();
$current_user = trim($_COOKIE[USER_COOKIE] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_username'])) {
        $username = trim($_POST['username'] ?? '');
        if ($username !== '') {
            setcookie(USER_COOKIE, $username, time() + 60 * 60 * 24 * 365, '/');
        }
        header('Location: index.php');
        exit;
    }

    if ($current_user !== '') {
        if (isset($_POST['delete_id'])) {
            $stmt = $db->prepare('DELETE FROM tasks WHERE id = :id AND username = :username');
            $stmt->execute(['id' => (int) $_POST['delete_id'], 'username' => $current_user]);
        } elseif (isset($_POST['toggle_id'])) {
            $stmt = $db->prepare('UPDATE tasks SET completed = 1 - completed WHERE id = :id AND username = :username');
            $stmt->execute(['id' => (int) $_POST['toggle_id'], 'username' => $current_user]);
        } elseif (isset($_POST['reset'])) {
            $stmt = $db->prepare('DELETE FROM tasks WHERE username = :username');
            $stmt->execute(['username' => $current_user]);
        } elseif (isset($_POST['task'])) {
            $text = trim($_POST['task']);
            if ($text !== '') {
                $important = isset($_POST['important']);
                $due_date = trim($_POST['due_date'] ?? '');
                $urgent = is_due_soon($due_date);
                $stmt = $db->prepare('
                    INSERT INTO tasks (username, text, important, urgent, class, due_date)
                    VALUES (:username, :text, :important, :urgent, :class, :due_date)
                ');
                $stmt->execute([
                    'username' => $current_user,
                    'text' => $text,
                    'important' => (int) $important,
                    'urgent' => (int) $urgent,
                    'class' => classify($important, $urgent),
                    'due_date' => $due_date,
                ]);
            }
        }
    }
    header('Location: index.php');
    exit;
}

$tasks = [];
if ($current_user !== '') {
    $stmt = $db->prepare('
        SELECT * FROM tasks
        WHERE username = :username
        ORDER BY class ASC, (due_date = \'\') ASC, due_date ASC, id ASC
    ');
    $stmt->execute(['username' => $current_user]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$total_count = count($tasks);
$completed_count = count(array_filter($tasks, fn($t) => (int) $t['completed'] === 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Concierge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Concierge</h1>

        <?php if ($current_user !== ''): ?>
            <div class="tally"><?= $completed_count ?>/<?= $total_count ?></div>
        <?php endif; ?>

        <form action="" method="post" class="user-form">
            <div>
                <label for="username">User</label>
                <input type="text" id="username" name="username" required placeholder="Enter your name" value="<?= htmlspecialchars($current_user) ?>">
            </div>
            <input type="hidden" name="set_username" value="1">
            <button type="submit" class="btn-glow-gradient">Continue</button>
        </form>

        <?php if ($current_user === ''): ?>
            <p class="empty-state">Enter your name above to start or resume your task list.</p>
        <?php else: ?>
            <form action="" method="post" class="task-form">
                <div>
                    <label for="task">Task</label>
                    <input type="text" id="task" name="task" required>
                </div>

                <div>
                    <label for="due_date">Due date</label>
                    <input type="date" id="due_date" name="due_date">
                </div>

                <div class="checkbox-group">
                    <div class="checkbox-field">
                        <input type="checkbox" id="important" name="important">
                        <label for="important">Important</label>
                    </div>
                </div>

                <div class="action-group">
                    <button type="submit" class="btn-glow-gradient add-task-btn">Add Task</button>

                    <input type="search" id="task-search" class="search-input" placeholder="Search tasks or due date&hellip;">
                    <button type="button" id="search-btn" class="search-btn" aria-label="Search">&#128269;</button>
                </div>
            </form>

            <div class="columns">
                <div class="column">
                    <div class="tasks-toolbar">
                        <h2>Tasks</h2>
                        <form action="" method="post" id="reset-form">
                            <input type="hidden" name="reset" value="1">
                            <button type="submit" class="reset-btn">Reset</button>
                        </form>
                    </div>
                    <?php if (empty($tasks)): ?>
                        <p class="empty-state">No tasks yet.</p>
                    <?php else: ?>
                        <ul id="task-list">
                            <?php foreach ($tasks as $task): ?>
                                <li class="class-<?= $task['class'] ?><?= $task['completed'] ? ' completed' : '' ?>">
                                    <span class="task-text"><?= htmlspecialchars($task['text']) ?></span>
                                    <?php if (!empty($task['due_date'])): ?>
                                        <span class="due-date">Due <?= htmlspecialchars($task['due_date']) ?></span>
                                    <?php endif; ?>
                                    <div class="task-actions">
                                        <form action="" method="post" class="inline-form">
                                            <input type="hidden" name="toggle_id" value="<?= (int) $task['id'] ?>">
                                            <button type="submit" class="done-btn" title="<?= $task['completed'] ? 'Mark as not done' : 'Mark as done' ?>">&#10003;</button>
                                        </form>
                                        <form action="" method="post" class="inline-form" data-confirm="Delete this task?">
                                            <input type="hidden" name="delete_id" value="<?= (int) $task['id'] ?>">
                                            <button type="submit" class="delete-btn" title="Delete task">&times;</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="empty-state" id="search-empty" hidden>No tasks match your search.</p>
                    <?php endif; ?>
                </div>

                <div class="column">
                    <div class="calendar">
                        <button type="button" class="cal-arrow" id="cal-prev" aria-label="Previous day">&#8249;</button>
                        <div class="cal-days">
                            <div class="cal-day cal-side" id="cal-day-prev">
                                <div class="cal-weekday"></div>
                                <div class="cal-date"></div>
                                <ul class="cal-tasks"></ul>
                            </div>
                            <div class="cal-day cal-current" id="cal-day-current">
                                <div class="cal-weekday"></div>
                                <div class="cal-date"></div>
                                <ul class="cal-tasks"></ul>
                            </div>
                            <div class="cal-day cal-side" id="cal-day-next">
                                <div class="cal-weekday"></div>
                                <div class="cal-date"></div>
                                <ul class="cal-tasks"></ul>
                            </div>
                        </div>
                        <button type="button" class="cal-arrow" id="cal-next" aria-label="Next day">&#8250;</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        window.CAL_TASKS = <?= json_encode(array_map(fn($t) => [
            'text' => $t['text'],
            'due_date' => $t['due_date'],
            'class' => (int) $t['class'],
            'completed' => (int) $t['completed'],
        ], $tasks), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="app.js"></script>
</body>
</html>
