<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Opens an admin page: <head>, top bar with the menu, flash message and <main>. */
function admin_head(string $title, string $active): void
{
    $pending = (int) db()->query('SELECT COUNT(*) FROM reviews WHERE status = "pending"')->fetchColumn();
    $flash = take_flash();
    $links = [
        'index' => ['index.php', 'Gallery &amp; settings'],
        'workshops' => ['workshops.php', 'Workshops'],
        'healing' => ['healing.php', 'Healing list'],
        'products' => ['products.php', 'Crystal shop'],
        'reviews' => ['reviews.php', 'Reviews'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> | Svayamev Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Jost:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="admin-bar">
        <strong>Svayamev <span>Admin</span></strong>
        <nav aria-label="Admin">
            <?php foreach ($links as $key => [$href, $label]): ?>
                <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= $label ?><?php if ($key === 'reviews' && $pending > 0): ?> <em class="count"><?= $pending ?></em><?php endif; ?></a>
            <?php endforeach; ?>
            <a href="../index.php" target="_blank" rel="noopener">View website</a>
            <form method="post" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="link-btn">Sign out</button>
            </form>
        </nav>
    </header>

    <main class="admin-wrap">
        <?php if ($flash): ?>
            <p class="alert alert-<?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>
<?php
}

function admin_foot(): void
{
    ?>
    </main>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
<?php
}

/** Starts the usual admin request: headers, secure session, login check. */
function admin_boot(): void
{
    send_security_headers();
    header('Cache-Control: no-store');
    start_secure_session();
    require_admin();
}
