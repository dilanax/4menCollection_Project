<?php
/**
 * Local Development Router for PHP Built-in Server
 * Usage: php -S localhost:8000 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 1. If it's a real file (CSS, JS, images, uploads, etc.), serve directly
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// 2. Product Single Page (/product/item-slug)
if (preg_match('#^/product/([^/]+)/?$#', $uri, $matches)) {
    $_GET['page'] = 'product';
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/index.php';
    return true;
}

// 3. Category URL (/category/cat-slug)
if (preg_match('#^/category/([^/]+)/?$#', $uri, $matches)) {
    $_GET['page'] = 'shop';
    $_GET['category'] = $matches[1];
    $_GET['categories'] = [$matches[1]];
    require __DIR__ . '/index.php';
    return true;
}

// 4. Publisher / Brand URL (/publisher/brand-slug)
if (preg_match('#^/publisher/([^/]+)/?$#', $uri, $matches)) {
    $_GET['page'] = 'shop';
    $_GET['brand'] = $matches[1];
    $_GET['brands'] = [$matches[1]];
    require __DIR__ . '/index.php';
    return true;
}

// 5. If it's a directory that has an index.php (e.g. /admin/)
if (is_dir(__DIR__ . $uri) && file_exists(__DIR__ . rtrim($uri, '/') . '/index.php')) {
    require __DIR__ . rtrim($uri, '/') . '/index.php';
    return true;
}

// 6. Default route to index.php
$page = ltrim($uri, '/');
if (!empty($page)) {
    $_GET['page'] = $page;
}
require __DIR__ . '/index.php';
return true;
