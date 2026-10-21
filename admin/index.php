<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/admin_ui.php';
require_once __DIR__ . '/../inc/upload.php';

admin_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'upload':
            $files = $_FILES['photos'] ?? null;
            $caption = mb_substr(trim((string) ($_POST['caption'] ?? '')), 0, 140);
            $added = 0;
            $errors = [];
            if ($files && is_array($files['name'])) {
                $count = min(count($files['name']), 20);
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    [$name, $err] = store_image([
                        'name' => $files['name'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ]);
                    if ($name !== null) {
                        db()->prepare('INSERT INTO gallery (filename, caption, created_at) VALUES (?, ?, ?)')
                            ->execute([$name, $caption, time()]);
                        $added++;
                    } else {
                        $errors[] = $err;
                    }
                }
            }
            if ($added > 0) {
                flash('ok', $added . ' photo(s) added to the gallery.');
            } else {
                flash('error', $errors[0] ?? 'Please choose at least one photo.');
            }
            break;

        case 'caption':
            $id = (int) ($_POST['id'] ?? 0);
            $caption = mb_substr(trim((string) ($_POST['caption'] ?? '')), 0, 140);
            db()->prepare('UPDATE gallery SET caption = ? WHERE id = ?')->execute([$caption, $id]);
            flash('ok', 'Caption saved.');
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = db()->prepare('SELECT filename FROM gallery WHERE id = ?');
            $stmt->execute([$id]);
            $filename = $stmt->fetchColumn();
            if ($filename !== false) {
                db()->prepare('DELETE FROM gallery WHERE id = ?')->execute([$id]);
                delete_image((string) $filename);
                flash('ok', 'Photo deleted.');
            }
            break;

        case 'whatsapp':
            $number = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? ''));
            if (strlen($number) < 10 || strlen($number) > 15) {
                flash('error', 'Enter the WhatsApp number with country code, e.g. 919876543210.');
            } else {
                set_setting('whatsapp', $number);
                flash('ok', 'WhatsApp number updated.');
            }
            break;

        case 'password':
            $current = (string) ($_POST['current'] ?? '');
            $new = (string) ($_POST['new'] ?? '');
            $confirm = (string) ($_POST['confirm'] ?? '');
            $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['admin_id']]);
            $hash = (string) $stmt->fetchColumn();
            if (!password_verify($current, $hash)) {
                flash('error', 'Current password is incorrect.');
            } elseif (strlen($new) < 10) {
                flash('error', 'New password must be at least 10 characters.');
            } elseif (!hash_equals($new, $confirm)) {
                flash('error', 'New passwords do not match.');
            } else {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
                session_regenerate_id(true);
                flash('ok', 'Password changed.');
            }
            break;
    }

    header('Location: index.php');
    exit;
}

$photos = db()->query('SELECT id, filename, caption FROM gallery ORDER BY created_at DESC, id DESC')->fetchAll();
$whatsapp = setting('whatsapp', '');
admin_head('Gallery & settings', 'index');
?>

        <section class="panel">
            <h2>Add photos to the gallery</h2>
            <form method="post" enctype="multipart/form-data" class="row-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <label for="photos">Photos (JPG, PNG, WebP, up to 8 MB each)</label>
                <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required>
                <label for="caption">Caption (optional, applies to all selected)</label>
                <input id="caption" name="caption" type="text" maxlength="140" placeholder="e.g. Reiki workshop, Pune">
                <button type="submit" class="btn">Upload</button>
            </form>
        </section>

        <section class="panel">
            <h2>Gallery <small>(<?= count($photos) ?>)</small></h2>
            <?php if (!$photos): ?>
                <p class="muted">No photos yet. Upload your first one above.</p>
            <?php else: ?>
                <ul class="photo-grid">
                    <?php foreach ($photos as $photo): ?>
                        <li class="photo-card">
                            <img src="../uploads/<?= e($photo['filename']) ?>" alt="<?= e($photo['caption'] ?: 'Gallery photo') ?>" loading="lazy">
                            <form method="post" class="caption-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="caption">
                                <input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
                                <input type="text" name="caption" value="<?= e($photo['caption']) ?>" maxlength="140" placeholder="Caption" aria-label="Caption">
                                <button type="submit" class="btn btn-small">Save</button>
                            </form>
                            <form method="post" class="delete-form" data-confirm="Delete this photo permanently?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="panel two-col">
            <div>
                <h2>WhatsApp number</h2>
                <form method="post" class="row-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="whatsapp">
                    <label for="whatsapp">With country code, digits only</label>
                    <input id="whatsapp" name="whatsapp" type="text" inputmode="numeric" value="<?= e($whatsapp) ?>" placeholder="919876543210" required>
                    <button type="submit" class="btn">Save number</button>
                </form>
            </div>
            <div>
                <h2>Change password</h2>
                <form method="post" class="row-form" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <label for="current">Current password</label>
                    <input id="current" name="current" type="password" required autocomplete="current-password">
                    <label for="new">New password (10+ characters)</label>
                    <input id="new" name="new" type="password" required minlength="10" autocomplete="new-password">
                    <label for="confirm">Confirm new password</label>
                    <input id="confirm" name="confirm" type="password" required minlength="10" autocomplete="new-password">
                    <button type="submit" class="btn">Change password</button>
                </form>
            </div>
        </section>
<?php admin_foot();
