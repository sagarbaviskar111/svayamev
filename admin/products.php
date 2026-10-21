<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/admin_ui.php';
require_once __DIR__ . '/../inc/upload.php';

admin_boot();

const PRODUCT_IMAGE_EDGE = 1000;

$empty = ['id' => 0, 'name' => '', 'category' => '', 'description' => '', 'in_stock' => 1, 'visible' => 1, 'image' => ''];
$form = $empty;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT image FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        if ($image !== false) {
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            delete_image((string) $image);
            flash('ok', 'Product deleted.');
        }
        header('Location: products.php');
        exit;
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $form = [
            'id' => $id,
            'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80),
            'category' => mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 40),
            'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 500),
            'in_stock' => isset($_POST['in_stock']) ? 1 : 0,
            'visible' => isset($_POST['visible']) ? 1 : 0,
            'image' => '',
        ];

        $existing = null;
        if ($id > 0) {
            $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch() ?: null;
            if ($existing === null) {
                $error = 'That product no longer exists.';
            } else {
                $form['image'] = $existing['image'];
            }
        }

        $hasUpload = ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($error !== '') {
            // keep the message above
        } elseif ($form['name'] === '') {
            $error = 'Please enter the product name.';
        } elseif ($existing === null && !$hasUpload) {
            $error = 'Please add a product photo.';
        } else {
            $newImage = null;
            if ($hasUpload) {
                [$newImage, $uploadError] = store_image($_FILES['image'], PRODUCT_IMAGE_EDGE);
                if ($newImage === null) {
                    $error = $uploadError;
                }
            }

            if ($error === '') {
                if ($existing === null) {
                    // price_paise is a leftover column from an earlier version; prices are shared on WhatsApp now.
                    db()->prepare('INSERT INTO products (name, category, description, price_paise, image, in_stock, visible, created_at)
                        VALUES (?, ?, ?, 0, ?, ?, ?, ?)')
                        ->execute([$form['name'], $form['category'], $form['description'], $newImage, $form['in_stock'], $form['visible'], time()]);
                    flash('ok', 'Product added to the shop.');
                } else {
                    db()->prepare('UPDATE products SET name = ?, category = ?, description = ?, image = ?, in_stock = ?, visible = ? WHERE id = ?')
                        ->execute([$form['name'], $form['category'], $form['description'], $newImage ?? $existing['image'], $form['in_stock'], $form['visible'], $id]);
                    if ($newImage !== null) {
                        delete_image((string) $existing['image']);
                    }
                    flash('ok', 'Product updated.');
                }
                header('Location: products.php');
                exit;
            }
        }
    }
} elseif (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    if ($row = $stmt->fetch()) {
        $form = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'description' => $row['description'],
            'in_stock' => (int) $row['in_stock'],
            'visible' => (int) $row['visible'],
            'image' => $row['image'],
        ];
    }
}

$products = db()->query('SELECT * FROM products ORDER BY created_at DESC, id DESC')->fetchAll();
$categories = db()->query('SELECT DISTINCT category FROM products WHERE category != "" ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
$editing = $form['id'] > 0;
admin_head('Crystal shop', 'products');
?>

        <section class="panel" id="form">
            <h2><?= $editing ? 'Edit product' : 'Add a product' ?></h2>
            <?php if ($error !== ''): ?>
                <p class="alert alert-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="row-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

                <div class="form-grid">
                    <div>
                        <label for="name">Product name</label>
                        <input id="name" name="name" type="text" required maxlength="80" value="<?= e($form['name']) ?>" placeholder="e.g. Rose Quartz Bracelet">
                    </div>
                    <div>
                        <label for="category">Category</label>
                        <input id="category" name="category" type="text" maxlength="40" list="cats" value="<?= e($form['category']) ?>" placeholder="e.g. Bracelet, Pyramid, Tumble">
                        <datalist id="cats">
                            <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <p class="hint">No price is shown on the website. Customers tap the button and ask the price on WhatsApp.</p>

                <label for="description">Short description / benefits (max 500 characters)</label>
                <textarea id="description" name="description" rows="3" maxlength="500" placeholder="What it helps with, size, stone type..."><?= e($form['description']) ?></textarea>

                <label for="image">Product photo <?= $editing ? '(leave empty to keep the current one)' : '' ?></label>
                <?php if ($editing && $form['image'] !== ''): ?>
                    <img class="current-img" src="../uploads/<?= e($form['image']) ?>" alt="Current product photo" width="120" height="120">
                <?php endif; ?>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" <?= $editing ? '' : 'required' ?>>

                <div class="check-row">
                    <label class="check"><input type="checkbox" name="in_stock" value="1" <?= $form['in_stock'] ? 'checked' : '' ?>> In stock</label>
                    <label class="check"><input type="checkbox" name="visible" value="1" <?= $form['visible'] ? 'checked' : '' ?>> Show on website</label>
                </div>

                <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Add to shop' ?></button>
                <?php if ($editing): ?><a class="btn btn-outline" href="products.php">Cancel</a><?php endif; ?>
            </form>
        </section>

        <section class="panel">
            <h2>Products <small>(<?= count($products) ?>)</small></h2>
            <?php if (!$products): ?>
                <p class="muted">No products yet. Add your first crystal above.</p>
            <?php else: ?>
                <ul class="product-list">
                    <?php foreach ($products as $p): ?>
                        <li class="product-row">
                            <img src="../uploads/<?= e($p['image']) ?>" alt="" width="72" height="72" loading="lazy">
                            <div class="product-info">
                                <strong><?= e($p['name']) ?></strong>
                                <?php if ($p['category'] !== ''): ?><span><?= e($p['category']) ?></span><?php endif; ?>
                                <span>
                                    <em class="badge <?= $p['in_stock'] ? 'badge-ok' : 'badge-warn' ?>"><?= $p['in_stock'] ? 'In stock' : 'Sold out' ?></em>
                                    <?php if (!$p['visible']): ?><em class="badge badge-muted">Hidden</em><?php endif; ?>
                                </span>
                            </div>
                            <div class="product-actions">
                                <a class="btn btn-small" href="products.php?edit=<?= (int) $p['id'] ?>#form">Edit</a>
                                <form method="post" data-confirm="Delete this product permanently?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
<?php admin_foot();
