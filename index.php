<?php
// Output Buffering
ob_start();

// Display errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Fallback
if (file_exists('config/db.php')) {
    require_once 'config/db.php';
}

// 1. Get URL Path
$route = isset($_GET['page']) ? trim($_GET['page'], '/') : '';

if (empty($route)) {
    $route = 'home';
}

// 2. Explode URL segments
$segments = explode('/', $route);
$base_page = basename($segments[0]); 

// 3. Dynamic Slugs (SEO Friendly URLs - Handles both .htaccess rewrite and direct path)
if ($base_page === 'product') {
    $base_page = 'single_product';
    if (isset($segments[1])) {
        $_GET['slug'] = htmlspecialchars($segments[1], ENT_QUOTES, 'UTF-8');
    } elseif (isset($_GET['slug'])) {
        $_GET['slug'] = htmlspecialchars($_GET['slug'], ENT_QUOTES, 'UTF-8');
    }
} 
elseif ($base_page === 'category') {
    $base_page = 'shop';
    if (isset($segments[1])) {
        $_GET['category'] = htmlspecialchars($segments[1], ENT_QUOTES, 'UTF-8');
        $_GET['categories'] = [$_GET['category']];
    } elseif (isset($_GET['category'])) {
        $_GET['categories'] = [htmlspecialchars($_GET['category'], ENT_QUOTES, 'UTF-8')];
    }
}
elseif ($base_page === 'blog') {
    if (isset($segments[1]) || isset($_GET['slug'])) {
        $base_page = 'single_blog';
        if (isset($segments[1])) {
            $_GET['slug'] = htmlspecialchars($segments[1], ENT_QUOTES, 'UTF-8');
        }
    } else {
        $base_page = 'blog';
    }
}
elseif ($base_page === 'account' && isset($segments[1]) && $segments[1] === 'invoice_single') {
    $base_page = 'invoice_single';
}

// 4. Public Pages
$publicPages = [
    'login'             => 'public/login.php',
    'register'          => 'public/register.php',
    'logout'            => 'public/logout.php',
    'forgot-password'   => 'public/forgot_password.php',
    'reset-password'    => 'public/reset_password.php'
];

// 5. Custom Routes (Both 'product' and 'single_product' mapped safely + invoice_single)
$customRoutes = [
    'home'               => 'pages/home.php',
    'shop'               => 'pages/products/shop.php',
    'product'            => 'pages/products/single_product.php',
    'single_product'     => 'pages/products/single_product.php',
    'cart'               => 'pages/products/cart.php',
    'checkout'           => 'pages/products/checkout.php', 
    'checkout_action'    => 'pages/checkout/checkout_action.php', 
    'account'            => 'pages/account/account.php',
    'invoice_single'     => 'pages/account/invoice_single.php',
    'single_invoice'     => 'pages/account/invoice_single.php',
    'ajax_cart'          => 'pages/products/ajax_add_to_cart.php',
    'publishers'         => 'pages/publishers.php',
    'authors'            => 'pages/authors.php',
    'blog'               => 'pages/blog/blog.php',
    'single_blog'        => 'pages/blog/single.php',
    'about'              => 'pages/about.php',
    'about-us'           => 'pages/about.php',
    'contact'            => 'pages/contact.php',
    'payhere_redirect'   => 'pages/checkout/payhere_redirect.php',
    'payhere_return'     => 'pages/checkout/payhere_return.php',
    'payhere_cancel'     => 'pages/checkout/payhere_cancel.php',
    'payhere_notify'     => 'pages/checkout/payhere_notify.php'
];

$pagePath = '';

if (array_key_exists($base_page, $publicPages)) {
    $pagePath = $publicPages[$base_page];
} elseif (array_key_exists($base_page, $customRoutes)) {
    $pagePath = $customRoutes[$base_page];
} else {
    // Fallback search in pages/ folder
    $possiblePath = 'pages/' . $base_page . '.php';
    if (file_exists($possiblePath)) {
        $pagePath = $possiblePath;
    } else {
        $pagePath = '';
    }
}

if (!empty($pagePath) && file_exists($pagePath)) {
    if (in_array($base_page, ['logout', 'ajax_cart', 'checkout_action', 'payhere_redirect', 'payhere_notify'])) {
        require_once $pagePath;
        exit;
    }

    require_once 'includes/header.php';
    require_once $pagePath;
    require_once 'includes/footer.php';

} else {
    // 404 Error Handler
    require_once 'includes/header.php';
    if (file_exists('includes/404.php')) {
        require_once 'includes/404.php';
    } else {
        echo "
        <div class='min-h-[60vh] flex flex-col items-center justify-center px-4 py-20 text-center font-sans'>
            <h1 class='text-8xl font-black text-gray-200 mb-4'>404</h1>
            <h2 class='text-3xl font-bold text-gray-900 mb-2'>Page Not Found</h2>
            <p class='text-gray-500 mb-8 max-w-md mx-auto'>The page you are looking for does not exist or has been moved.</p>
            <a href='/' class='bg-sky-600 hover:bg-sky-700 text-white font-semibold px-8 py-3 rounded-xl shadow-md transition duration-300'>
                Return to Home
            </a>
        </div>";
    }
    require_once 'includes/footer.php';
}

ob_end_flush();
?>