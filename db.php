<?php

const DB_FILE = __DIR__ . '/concierge.sqlite';

function get_db(): PDO {
    $is_new = !file_exists(DB_FILE);
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($is_new) {
        $db->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
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
        return $db;
    }

    // Migrate older databases that predate the users table / newer task columns.
    $columns = $db->query('PRAGMA table_info(tasks)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('username', $columns, true)) {
        $db->exec('ALTER TABLE tasks ADD COLUMN username TEXT NOT NULL DEFAULT \'\'');
    }
    if (!in_array('completed', $columns, true)) {
        $db->exec('ALTER TABLE tasks ADD COLUMN completed INTEGER NOT NULL DEFAULT 0');
    }

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('users', $tables, true)) {
        $db->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    return $db;
}

// Case/whitespace-insensitive so "Alex", "alex", and "ALEX " all resolve
// to the same account instead of silently creating separate ones.
function normalize_username(string $username): string {
    return strtolower(trim($username));
}
