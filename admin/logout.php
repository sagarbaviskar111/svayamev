<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';

start_secure_session();

// Logout is a POST with a CSRF token so other sites can't sign the admin out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

header('Location: login.php');
exit;
