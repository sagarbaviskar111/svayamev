<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /inc/\n";
echo "Disallow: /data/\n";
echo "\n";
echo 'Sitemap: ' . site_url() . "/sitemap.xml\n";
