<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/admin_ui.php';
require_once __DIR__ . '/../inc/content.php';

admin_boot();

$text = setting('healing_services');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $items = array_slice(lines_to_array((string) ($_POST['services'] ?? '')), 0, 40);
    $items = array_map(fn ($l) => mb_substr(clean_text($l, 80), 0, 80), $items);
    $items = array_values(array_filter($items, fn ($l) => $l !== ''));
    set_setting('healing_services', implode("\n", $items));
    flash('ok', $items ? 'Healing & cleansing list saved (' . count($items) . ' items).' : 'List cleared. The list is hidden on the website until you add items.');
    header('Location: healing.php');
    exit;
}

admin_head('Healing list', 'healing');
?>

        <section class="panel">
            <h2>Consultation &amp; healing services</h2>
            <p class="muted">This is the list shown in the "Healing &amp; cleansing" section of the website. Write one service per line. Add a line to add a service, change a line to edit it, delete a line to remove it. The order here is the order on the website.</p>
            <form method="post" class="row-form">
                <?= csrf_field() ?>
                <label for="services">Services (one per line, up to 40)</label>
                <textarea id="services" name="services" rows="12" maxlength="4000"><?= e($text) ?></textarea>
                <button type="submit" class="btn">Save list</button>
            </form>
        </section>
<?php admin_foot();
