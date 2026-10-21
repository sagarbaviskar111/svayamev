<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$base = site_url();
$photos = db()->query('SELECT filename, caption, created_at FROM gallery ORDER BY created_at DESC LIMIT 200')->fetchAll();
$shopItems = db()->query('SELECT image, name FROM products WHERE visible = 1 ORDER BY created_at DESC LIMIT 200')->fetchAll();
$classImages = db()->query('SELECT image, name FROM workshops WHERE visible = 1 AND image != "" ORDER BY sort_order LIMIT 100')->fetchAll();
$lastmod = gmdate('Y-m-d', max(filemtime(__DIR__ . '/index.php'), (int) ($photos[0]['created_at'] ?? 0)));

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url>
    <loc><?= e($base) ?>/</loc>
    <lastmod><?= e($lastmod) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
    <image:image>
      <image:loc><?= e($base) ?>/assets/img/teacher-800.jpg</image:loc>
      <image:title>Svayamev founder and mentor</image:title>
    </image:image>
    <image:image>
      <image:loc><?= e($base) ?>/assets/img/logo-800.webp</image:loc>
      <image:title>Svayamev logo</image:title>
    </image:image>
<?php foreach ($classImages as $c): ?>
    <image:image>
      <image:loc><?= e($base) ?>/uploads/<?= e($c['image']) ?></image:loc>
      <image:title><?= e($c['name']) ?> workshop</image:title>
    </image:image>
<?php endforeach; ?>
<?php foreach ($shopItems as $s): ?>
    <image:image>
      <image:loc><?= e($base) ?>/uploads/<?= e($s['image']) ?></image:loc>
      <image:title><?= e($s['name']) ?></image:title>
    </image:image>
<?php endforeach; ?>
<?php foreach ($photos as $p): ?>
    <image:image>
      <image:loc><?= e($base) ?>/uploads/<?= e($p['filename']) ?></image:loc>
<?php if ($p['caption'] !== ''): ?>
      <image:title><?= e($p['caption']) ?></image:title>
<?php endif; ?>
    </image:image>
<?php endforeach; ?>
  </url>
</urlset>
