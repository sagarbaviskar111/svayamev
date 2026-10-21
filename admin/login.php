<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';

send_security_headers();
header('Cache-Control: no-store');
start_secure_session();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$hasAdmin = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!$hasAdmin) {
        // First-run setup: create the one admin account.
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
            $error = 'Username: 3-32 letters, numbers, dot, dash or underscore.';
        } elseif (strlen($password) < 10) {
            $error = 'Password must be at least 10 characters.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'Passwords do not match.';
        } else {
            $stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) db()->lastInsertId();
            header('Location: index.php');
            exit;
        }
    } elseif (login_blocked()) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Verify against a dummy hash when the user is unknown so timing is similar.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsalt.invalidhashvalueabcdefghijkl';
        $valid = password_verify($password, $hash) && $user;

        if ($valid) {
            clear_failed_logins();
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $user['id'];
            header('Location: index.php');
            exit;
        }
        record_failed_login();
        $error = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login | Svayamev</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Jost:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="auth-body">
    <main class="auth-card">
        <p class="auth-mark" aria-hidden="true">&#10043;</p>
        <h1>Svayamev</h1>
        <p class="auth-sub"><?= $hasAdmin ? 'Admin sign in' : 'First-time setup: create your admin account' ?></p>

        <?php if ($error !== ''): ?>
            <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required maxlength="32" autocomplete="username">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="<?= $hasAdmin ? 'current-password' : 'new-password' ?>">

            <?php if (!$hasAdmin): ?>
                <label for="confirm">Confirm password</label>
                <input id="confirm" name="confirm" type="password" required autocomplete="new-password">
                <p class="hint">Use at least 10 characters. This screen disappears once the account exists.</p>
            <?php endif; ?>

            <button type="submit" class="btn"><?= $hasAdmin ? 'Sign in' : 'Create admin' ?></button>
        </form>
        <p class="back"><a href="../index.php">&larr; Back to website</a></p>
    </main>
</body>
</html>
