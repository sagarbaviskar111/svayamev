<?php
// Local dev only: php -S localhost:8000 router.php
// The built-in server ignores .htaccess, so repeat its rules here.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

if (preg_match('#^/(data|inc|Public)(/|$)#i', $path) || preg_match('#^/uploads/.*\.(php|phtml|phar)$#i', $path) || preg_match('#/\.#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}
if ($path === '/robots.txt') {
    require __DIR__ . '/robots.php';
    return true;
}

return false;
