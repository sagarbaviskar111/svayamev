<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/admin_ui.php';
require_once __DIR__ . '/../inc/upload.php';
require_once __DIR__ . '/../inc/content.php';

admin_boot();

const WORKSHOP_IMAGE_EDGE = 1200;

$next = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM workshops')->fetchColumn();
$empty = ['id' => 0, 'name' => '', 'tag' => '', 'duration' => '', 'mode' => '', 'levels' => '', 'age' => '', 'items' => '', 'note' => '', 'image' => '', 'sort_order' => $next, 'visible' => 1];
$form = $empty;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT image FROM workshops WHERE id = ?');
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        if ($image !== false) {
            db()->prepare('DELETE FROM workshops WHERE id = ?')->execute([$id]);
            if ($image !== '') {
                delete_image((string) $image);
            }
            flash('ok', 'Workshop deleted.');
        }
        header('Location: workshops.php');
        exit;
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $form = [
            'id' => $id,
            'name' => clean_text((string) ($_POST['name'] ?? ''), 90),
            'tag' => clean_text((string) ($_POST['tag'] ?? ''), 30),
            'duration' => clean_text((string) ($_POST['duration'] ?? ''), 40),
            'mode' => clean_text((string) ($_POST['mode'] ?? ''), 40),
            'levels' => clean_text((string) ($_POST['levels'] ?? ''), 40),
            'age' => clean_text((string) ($_POST['age'] ?? ''), 40),
            'items' => implode("\n", array_map(fn ($l) => mb_substr($l, 0, 160), array_slice(lines_to_array((string) ($_POST['items'] ?? '')), 0, 40))),
            'note' => clean_text((string) ($_POST['note'] ?? ''), 160),
            'image' => '',
            'sort_order' => max(0, min(9999, (int) ($_POST['sort_order'] ?? 0))),
            'visible' => isset($_POST['visible']) ? 1 : 0,
        ];
        $removeImage = isset($_POST['remove_image']);

        $existing = null;
        if ($id > 0) {
            $stmt = db()->prepare('SELECT * FROM workshops WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch() ?: null;
            if ($existing === null) {
                $error = 'That workshop no longer exists.';
            } else {
                $form['image'] = $existing['image'];
            }
        }

        $hasUpload = ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($error === '' && $form['name'] === '') {
            $error = 'Please enter the workshop name.';
        }

        $newImage = null;
        if ($error === '' && $hasUpload) {
            [$newImage, $uploadError] = store_image($_FILES['image'], WORKSHOP_IMAGE_EDGE);
            if ($newImage === null) {
                $error = $uploadError;
            }
        }

        if ($error === '') {
            $oldImage = $existing['image'] ?? '';
            $image = $newImage ?? ($removeImage ? '' : $oldImage);

            if ($existing === null) {
                db()->prepare('INSERT INTO workshops (name, tag, duration, mode, levels, age, items, note, image, sort_order, visible, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$form['name'], $form['tag'], $form['duration'], $form['mode'], $form['levels'], $form['age'], $form['items'], $form['note'], $image, $form['sort_order'], $form['visible'], time()]);
                flash('ok', 'Workshop added.');
            } else {
                db()->prepare('UPDATE workshops SET name = ?, tag = ?, duration = ?, mode = ?, levels = ?, age = ?, items = ?, note = ?,
                    image = ?, sort_order = ?, visible = ? WHERE id = ?')
                    ->execute([$form['name'], $form['tag'], $form['duration'], $form['mode'], $form['levels'], $form['age'], $form['items'], $form['note'], $image, $form['sort_order'], $form['visible'], $id]);
                flash('ok', 'Workshop updated.');
            }
            if ($oldImage !== '' && $image !== $oldImage) {
                delete_image((string) $oldImage);
            }
            header('Location: workshops.php');
            exit;
        }
    }
} elseif (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM workshops WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    if ($row = $stmt->fetch()) {
        $form = array_merge($empty, $row);
    }
}

$workshops = db()->query('SELECT * FROM workshops ORDER BY sort_order, id')->fetchAll();
$editing = (int) $form['id'] > 0;
admin_head('Workshops', 'workshops');
?>

        <section class="panel" id="form">
            <h2><?= $editing ? 'Edit workshop' : 'Add a workshop' ?></h2>
            <?php if ($error !== ''): ?>
                <p class="alert alert-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="row-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

                <div class="form-grid">
                    <div>
                        <label for="name">Workshop name</label>
                        <input id="name" name="name" type="text" required maxlength="90" value="<?= e($form['name']) ?>" placeholder="e.g. Reiki Healing">
                    </div>
                    <div>
                        <label for="tag">Level label</label>
                        <input id="tag" name="tag" type="text" maxlength="30" value="<?= e($form['tag']) ?>" placeholder="e.g. Level 1">
                    </div>
                    <div>
                        <label for="duration">Duration</label>
                        <input id="duration" name="duration" type="text" maxlength="40" value="<?= e($form['duration']) ?>" placeholder="e.g. 2 day workshop">
                    </div>
                    <div>
                        <label for="mode">Mode</label>
                        <input id="mode" name="mode" type="text" maxlength="40" value="<?= e($form['mode']) ?>" placeholder="Online, Offline or Online / Offline">
                    </div>
                    <div>
                        <label for="levels">Learning levels (optional)</label>
                        <input id="levels" name="levels" type="text" maxlength="40" value="<?= e($form['levels']) ?>" placeholder="e.g. 3 learning levels">
                    </div>
                    <div>
                        <label for="age">Age criteria (optional)</label>
                        <input id="age" name="age" type="text" maxlength="40" value="<?= e($form['age']) ?>" placeholder="e.g. Age 7 yrs &amp; above">
                    </div>
                </div>

                <label for="items">Course content (one point per line)</label>
                <textarea id="items" name="items" rows="8" placeholder="About 7 Chakras and their healing techniques&#10;Attunement process by the teacher&#10;Guided meditation"><?= e($form['items']) ?></textarea>

                <label for="note">Note under the content (optional)</label>
                <input id="note" name="note" type="text" maxlength="160" value="<?= e($form['note']) ?>" placeholder="e.g. Course content and certificate provided.">

                <label for="image">Workshop image (optional) <?= $editing && $form['image'] !== '' ? '- leave empty to keep the current one' : '' ?></label>
                <?php if ($editing && $form['image'] !== ''): ?>
                    <img class="current-img" src="../uploads/<?= e($form['image']) ?>" alt="Current workshop image" width="160" height="100">
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove this image</label>
                <?php endif; ?>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">

                <div class="form-grid">
                    <div>
                        <label for="sort_order">Display order (smaller shows first)</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="<?= (int) $form['sort_order'] ?>">
                    </div>
                    <div class="check-row">
                        <label class="check"><input type="checkbox" name="visible" value="1" <?= $form['visible'] ? 'checked' : '' ?>> Show on website</label>
                    </div>
                </div>

                <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Add workshop' ?></button>
                <?php if ($editing): ?><a class="btn btn-outline" href="workshops.php">Cancel</a><?php endif; ?>
            </form>
        </section>

        <section class="panel">
            <h2>Certified workshops <small>(<?= count($workshops) ?>)</small></h2>
            <?php if (!$workshops): ?>
                <p class="muted">No workshops yet. Add one above.</p>
            <?php else: ?>
                <ul class="product-list">
                    <?php foreach ($workshops as $w): ?>
                        <li class="product-row">
                            <?php if ($w['image'] !== ''): ?>
                                <img src="../uploads/<?= e($w['image']) ?>" alt="" width="72" height="72" loading="lazy">
                            <?php else: ?>
                                <span class="no-img" aria-hidden="true">No image</span>
                            <?php endif; ?>
                            <div class="product-info">
                                <strong><?= e($w['name']) ?><?= $w['tag'] !== '' ? ' &middot; ' . e($w['tag']) : '' ?></strong>
                                <span><?= e(implode(' · ', array_filter([$w['duration'], $w['mode'], $w['levels'], $w['age']]))) ?></span>
                                <span>
                                    <em class="badge badge-muted">Order <?= (int) $w['sort_order'] ?></em>
                                    <?php if (!$w['visible']): ?><em class="badge badge-warn">Hidden</em><?php endif; ?>
                                </span>
                            </div>
                            <div class="product-actions">
                                <a class="btn btn-small" href="workshops.php?edit=<?= (int) $w['id'] ?>#form">Edit</a>
                                <form method="post" data-confirm="Delete this workshop permanently?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
<?php admin_foot();
