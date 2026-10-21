<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/admin_ui.php';
require_once __DIR__ . '/../inc/content.php';

admin_boot();

$form = ['id' => 0, 'name' => '', 'body' => '', 'status' => 'approved'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if (in_array($action, ['approve', 'unpublish'], true)) {
        db()->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([$action === 'approve' ? 'approved' : 'pending', $id]);
        flash('ok', $action === 'approve' ? 'Review approved and now visible on the website.' : 'Review hidden from the website.');
        header('Location: reviews.php');
        exit;
    }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        flash('ok', 'Review deleted.');
        header('Location: reviews.php');
        exit;
    }

    if ($action === 'save') {
        $form = [
            'id' => $id,
            'name' => clean_text(str_replace("\n", ' ', (string) ($_POST['name'] ?? '')), 60),
            'body' => clean_text((string) ($_POST['body'] ?? ''), 800),
            'status' => ($_POST['status'] ?? '') === 'pending' ? 'pending' : 'approved',
        ];
        if ($form['name'] === '' || $form['body'] === '') {
            $error = 'Please enter both the name and the review.';
        } elseif ($id > 0) {
            db()->prepare('UPDATE reviews SET name = ?, body = ?, status = ? WHERE id = ?')->execute([$form['name'], $form['body'], $form['status'], $id]);
            flash('ok', 'Review updated.');
            header('Location: reviews.php');
            exit;
        } else {
            db()->prepare('INSERT INTO reviews (name, body, status, created_at) VALUES (?, ?, ?, ?)')->execute([$form['name'], $form['body'], $form['status'], time()]);
            flash('ok', 'Review added.');
            header('Location: reviews.php');
            exit;
        }
    }
} elseif (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT id, name, body, status FROM reviews WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    if ($row = $stmt->fetch()) {
        $form = $row;
    }
}

$pending = db()->query('SELECT * FROM reviews WHERE status = "pending" ORDER BY created_at DESC, id DESC')->fetchAll();
$approved = db()->query('SELECT * FROM reviews WHERE status = "approved" ORDER BY created_at DESC, id DESC')->fetchAll();
$editing = (int) $form['id'] > 0;

function review_row(array $r, bool $isPending): void
{
    ?>
    <li class="review-row">
        <div class="review-text">
            <strong><?= e($r['name']) ?></strong>
            <time datetime="<?= e(date('c', (int) $r['created_at'])) ?>"><?= e(date('d M Y, g:i a', (int) $r['created_at'])) ?></time>
            <p><?= nl2br(e($r['body'])) ?></p>
        </div>
        <div class="product-actions">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $isPending ? 'approve' : 'unpublish' ?>">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="btn btn-small<?= $isPending ? '' : ' btn-outline' ?>"><?= $isPending ? 'Approve' : 'Hide' ?></button>
            </form>
            <a class="btn btn-small btn-outline" href="reviews.php?edit=<?= (int) $r['id'] ?>#form">Edit</a>
            <form method="post" data-confirm="Delete this review permanently?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="btn btn-danger btn-small">Delete</button>
            </form>
        </div>
    </li>
    <?php
}

admin_head('Reviews', 'reviews');
?>

        <section class="panel">
            <h2>Waiting for approval <small>(<?= count($pending) ?>)</small></h2>
            <p class="muted">Reviews sent from the website appear here first. They show on the website only after you press Approve.</p>
            <?php if (!$pending): ?>
                <p class="muted">Nothing waiting. New reviews will show up here.</p>
            <?php else: ?>
                <ul class="review-list"><?php foreach ($pending as $r) { review_row($r, true); } ?></ul>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Published on website <small>(<?= count($approved) ?>)</small></h2>
            <?php if (!$approved): ?>
                <p class="muted">No published reviews yet.</p>
            <?php else: ?>
                <ul class="review-list"><?php foreach ($approved as $r) { review_row($r, false); } ?></ul>
            <?php endif; ?>
        </section>

        <section class="panel" id="form">
            <h2><?= $editing ? 'Edit review' : 'Add a review yourself' ?></h2>
            <?php if ($error !== ''): ?>
                <p class="alert alert-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
            <form method="post" class="row-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

                <label for="name">Name</label>
                <input id="name" name="name" type="text" required maxlength="60" value="<?= e($form['name']) ?>">

                <label for="body">Review</label>
                <textarea id="body" name="body" rows="5" required maxlength="800"><?= e($form['body']) ?></textarea>

                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="approved"<?= $form['status'] === 'approved' ? ' selected' : '' ?>>Published on website</option>
                    <option value="pending"<?= $form['status'] === 'pending' ? ' selected' : '' ?>>Hidden (waiting)</option>
                </select>

                <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Add review' ?></button>
                <?php if ($editing): ?><a class="btn btn-outline" href="reviews.php">Cancel</a><?php endif; ?>
            </form>
        </section>
<?php admin_foot();
