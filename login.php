<?php

session_start();
require __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$db = get_db();
$error = '';
$username_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = trim($_POST['username'] ?? '');
    $username = normalize_username($username_input);
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter both a username and a password.';
    } else {
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['username'] = $username;
                header('Location: index.php');
                exit;
            }
            $error = 'Incorrect password.';
        } else {
            $stmt = $db->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :hash)');
            $stmt->execute([
                'username' => $username,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $_SESSION['username'] = $username;
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Concierge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container landing">
        <h1>Concierge</h1>
        <p class="tagline">A digital and personal concierge for those especially bad at keeping on track</p>

        <form action="" method="post" class="user-form login-form">
            <div>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="Enter your username" value="<?= htmlspecialchars($username_input) ?>" autofocus>
            </div>

            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn-glow-gradient">Continue</button>
        </form>

        <?php if ($error !== ''): ?>
            <p class="form-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <p class="empty-state">New here? Just enter a username and password and we'll set up your account.</p>
    </div>
</body>
</html>
