<?php

const DB_FILE = __DIR__ . '/concierge.sqlite';

function get_db(): PDO {
    $is_new = !file_exists(DB_FILE);
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($is_new) {
        $db->exec('
            CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                text TEXT NOT NULL,
                important INTEGER NOT NULL DEFAULT 0,
                urgent INTEGER NOT NULL DEFAULT 0,
                class INTEGER NOT NULL,
                due_date TEXT NOT NULL DEFAULT \'\'
            )
        ');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim($_POST['task'] ?? '');
    if ($text !== '') {
        $important = isset($_POST['important']);
        $due_date = trim($_POST['due_date'] ?? '');
        // Urgency is derived from the due date whenever one is set; the
        // checkbox only applies as a fallback when there's no due date.
        $urgent = $due_date !== '' ? is_due_soon($due_date) : isset($_POST['urgent']);
        $stmt = $db->prepare('
            INSERT INTO tasks (text, important, urgent, class, due_date)
            VALUES (:text, :important, :urgent, :class, :due_date)
        ');
        $stmt->execute([
            'text' => $text,
            'important' => (int) $important,
            'urgent' => (int) $urgent,
            'class' => classify($important, $urgent),
            'due_date' => $due_date,
        ]);
    }
    header('Location: index.php');
    exit;
}

$tasks = $db->query('
    SELECT * FROM tasks
    ORDER BY class ASC, (due_date = \'\') ASC, due_date ASC, id ASC
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Concierge</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Concierge</h1>

        <form action="" method="post">
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

                <div class="checkbox-field">
                    <input type="checkbox" id="urgent" name="urgent">
                    <label for="urgent">Urgent (used if no due date)</label>
                </div>

                <button type="submit" class="btn-glow-gradient add-task-btn">Add Task</button>
            </div>
        </form>

        <div class="columns">
            <div class="column">
                <h2>Tasks</h2>
                <?php if (empty($tasks)): ?>
                    <p class="empty-state">No tasks yet.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($tasks as $task): ?>
                            <li class="class-<?= $task['class'] ?>">
                                <span><?= htmlspecialchars($task['text']) ?></span>
                                <?php if (!empty($task['due_date'])): ?>
                                    <span class="due-date">Due <?= htmlspecialchars($task['due_date']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="column"></div>
        </div>
    </div>
</body>
</html>
