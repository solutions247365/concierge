<?php

session_start();
require __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$db = get_db();
$error = '';
$success = false;
$username_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = trim($_POST['username'] ?? '');
    $username = normalize_username($username_input);
    $recovery_key = trim($_POST['recovery_key'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if ($username === '' || $recovery_key === '' || $new_password === '') {
        $error = 'Fill in every field.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $valid = $user
            && $user['recovery_key_hash'] !== ''
            && password_verify($recovery_key, $user['recovery_key_hash']);

        if ($valid) {
            $stmt = $db->prepare('
                UPDATE users
                SET password_hash = :hash, failed_attempts = 0, locked_until = 0
                WHERE id = :id
            ');
            $stmt->execute(['hash' => password_hash($new_password, PASSWORD_DEFAULT), 'id' => $user['id']]);
            log_login_attempt($db, $username, true);
            $success = true;
        } else {
            if ($user) {
                log_login_attempt($db, $username, false);
            }
            $error = 'Username and recovery key do not match.';
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
        <p class="tagline">Recover your account</p>

        <?php if ($success): ?>
            <p class="empty-state">Password updated and any lockout cleared. You can log in now.</p>
            <p class="empty-state"><a href="login.php" class="logout-link">Back to login</a></p>
        <?php else: ?>
            <form action="" method="post" class="user-form login-form">
                <div>
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required placeholder="Enter your username" value="<?= htmlspecialchars($username_input) ?>" autofocus>
                </div>

                <div>
                    <label for="recovery_key">Recovery key</label>
                    <input type="text" id="recovery_key" name="recovery_key" required placeholder="The 32-character key you saved at signup">
                </div>

                <div>
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter a new password">
                </div>

                <div>
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Enter it again">
                </div>

                <button type="submit" class="btn-glow-gradient">Reset password</button>
            </form>

            <?php if ($error !== ''): ?>
                <p class="form-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <p class="empty-state"><a href="login.php" class="logout-link">Back to login</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
