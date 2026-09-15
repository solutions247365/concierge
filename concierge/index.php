<?php

const TASKS_FILE = __DIR__ . '/tasks.json';

function load_tasks(): array {
    if (!file_exists(TASKS_FILE)) {
        return [];
    }
    $json = file_get_contents(TASKS_FILE);
    $tasks = json_decode($json, true);
    return is_array($tasks) ? $tasks : [];
}

function save_tasks(array $tasks): void {
    file_put_contents(TASKS_FILE, json_encode($tasks, JSON_PRETTY_PRINT));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim($_POST['task'] ?? '');
    if ($text !== '') {
        $important = isset($_POST['important']);
        $urgent = isset($_POST['urgent']);
        $due_date = trim($_POST['due_date'] ?? '');
        $tasks = load_tasks();
        $tasks[] = [
            'text' => $text,
            'important' => $important,
            'urgent' => $urgent,
            'class' => classify($important, $urgent),
            'due_date' => $due_date,
        ];
        save_tasks($tasks);
    }
    header('Location: index.php');
    exit;
}

$tasks = load_tasks();
$tasks_by_class = [1 => [], 2 => [], 3 => [], 4 => []];
foreach ($tasks as $task) {
    $tasks_by_class[$task['class']][] = $task;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Concierge</title>
</head>
<body>
    <form action="" method="post">
        <label for="task">Task:</label>
        <input type="text" id="task" name="task" required>

        <label for="due_date">Due date:</label>
        <input type="date" id="due_date" name="due_date">

        <label for="important">
            <input type="checkbox" id="important" name="important">
            Important
        </label>

        <label for="urgent">
            <input type="checkbox" id="urgent" name="urgent">
            Urgent
        </label>

        <button type="submit">Add Task</button>
    </form>

    <h2>Tasks</h2>
    <ul>
        <?php foreach ($tasks_by_class as $class_tasks): ?>
            <?php foreach ($class_tasks as $task): ?>
                <li>
                    <?= htmlspecialchars($task['text']) ?>
                    <?php if (!empty($task['due_date'])): ?>
                        (due <?= htmlspecialchars($task['due_date']) ?>)
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</body>
</html>
