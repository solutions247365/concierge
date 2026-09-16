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
                recovery_key_hash TEXT NOT NULL DEFAULT \'\',
                failed_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until INTEGER NOT NULL DEFAULT 0,
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
        $db->exec('
            CREATE TABLE login_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                city TEXT,
                region TEXT,
                country TEXT,
                success INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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
                recovery_key_hash TEXT NOT NULL DEFAULT \'\',
                failed_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
    } else {
        $user_columns = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('failed_attempts', $user_columns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN failed_attempts INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('locked_until', $user_columns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN locked_until INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('recovery_key_hash', $user_columns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN recovery_key_hash TEXT NOT NULL DEFAULT \'\'');
        }
    }

    if (!in_array('login_log', $tables, true)) {
        $db->exec('
            CREATE TABLE login_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                city TEXT,
                region TEXT,
                country TEXT,
                success INTEGER NOT NULL,
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

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 15 * 60;

// A 32-character hex recovery key, shown to the user exactly once at
// signup. Only its hash is ever stored - losing it means losing the
// ability to reset the password or clear a lockout for that account.
function generate_recovery_key(): string {
    return bin2hex(random_bytes(16));
}

// Best-effort IP geolocation via a free public API. Never let a slow or
// unreachable lookup block the login flow - on any failure this just
// leaves city/region/country empty.
function geolocate_ip(string $ip): array {
    $empty = ['city' => null, 'region' => null, 'country' => null];

    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
        return $empty;
    }

    $context = stream_context_create(['http' => ['timeout' => 2]]);
    $response = @file_get_contents(
        "http://ip-api.com/json/{$ip}?fields=status,city,regionName,country",
        false,
        $context
    );

    if ($response === false) {
        return $empty;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return $empty;
    }

    return [
        'city' => $data['city'] ?? null,
        'region' => $data['regionName'] ?? null,
        'country' => $data['country'] ?? null,
    ];
}

function log_login_attempt(PDO $db, string $username, bool $success): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $geo = geolocate_ip($ip);

    $stmt = $db->prepare('
        INSERT INTO login_log (username, ip_address, city, region, country, success)
        VALUES (:username, :ip, :city, :region, :country, :success)
    ');
    $stmt->execute([
        'username' => $username,
        'ip' => $ip,
        'city' => $geo['city'],
        'region' => $geo['region'],
        'country' => $geo['country'],
        'success' => (int) $success,
    ]);
}
