<?php
// Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Fallback for Header
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (file_exists('config/db.php')) {
        require_once 'config/db.php';
    } elseif (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    }
}

// Excluded Category Slugs / Names
$excluded_category_names = ['vinura ransika', 'vinura-ransika', 'vinura_ransika'];

// Fetch Categories from Database
$db_categories = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt_nav_cat = $pdo->query("SELECT * FROM categories ORDER BY parent_id ASC, id ASC");
        if ($stmt_nav_cat) {
            $db_categories = $stmt_nav_cat->fetchAll();
        }
    }
} catch (Throwable $e) {
    $db_categories = [];
}

// Build Main Categories & Sub-Categories Tree
$category_tree = [];
$sub_categories = [];

foreach ($db_categories as $cat) {
    $slug = strtolower(trim($cat['slug'] ?? ''));
    $name = strtolower(trim($cat['name'] ?? ''));
    if (in_array($slug, $excluded_category_names) || in_array($name, $excluded_category_names)) {
        continue;
    }

    $pid = (int)($cat['parent_id'] ?? 0);
    if ($pid === 0) {
        $category_tree[$cat['id']] = $cat;
        $category_tree[$cat['id']]['children'] = [];
    } else {
        $sub_categories[$pid][] = $cat;
    }
}

// Assign Children to Parents
foreach ($sub_categories as $pid => $children) {
    if (isset($category_tree[$pid])) {
        $category_tree[$pid]['children'] = $children;
    }
}

// Cart Quantity & Total Calculation
$cart_count = 0;
$cart_total = 0.00;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $qty = (int)($item['quantity'] ?? 1);
        $cart_count += $qty;

        $item_price = 0.00;
        if (isset($item['price']) && (float)$item['price'] > 0) {
            $item_price = (float)$item['price'];
        } elseif (isset($pdo) && $pdo instanceof PDO && !empty($item['product_id'])) {
            // Variation price check
            if (!empty($item['variation_id'])) {
                $vp_stmt = $pdo->prepare("SELECT regular_price, sale_price FROM product_variations WHERE id = ? LIMIT 1");
                $vp_stmt->execute([(int)$item['variation_id']]);
                $v_row = $vp_stmt->fetch(PDO::FETCH_ASSOC);
                if ($v_row) {
                    $item_price = ((float)$v_row['sale_price'] > 0) ? (float)$v_row['sale_price'] : (float)$v_row['regular_price'];
                }
            }
            // Simple product price check
            if ($item_price <= 0) {
                $p_stmt = $pdo->prepare("SELECT regular_price, sale_price, sale_price_dates_from, sale_price_dates_to FROM products WHERE id = ? LIMIT 1");
                $p_stmt->execute([(int)$item['product_id']]);
                $p_row = $p_stmt->fetch(PDO::FETCH_ASSOC);
                if ($p_row) {
                    $today = date('Y-m-d');
                    $on_sale = false;
                    if (!empty($p_row['sale_price']) && (float)$p_row['sale_price'] > 0) {
                        $s_from = $p_row['sale_price_dates_from'] ?? null;
                        $s_to = $p_row['sale_price_dates_to'] ?? null;
                        if ((empty($s_from) || $s_from <= $today) && (empty($s_to) || $s_to >= $today)) {
                            $on_sale = true;
                        }
                    }
                    $item_price = $on_sale ? (float)$p_row['sale_price'] : (float)$p_row['regular_price'];
                }
            }
        }
        $cart_total += ($qty * $item_price);
    }
}

// User Display Name
$display_name = 'User';
if (!empty($_SESSION['user_name'])) {
    $display_name = $_SESSION['user_name'];
} elseif (!empty($_SESSION['username'])) {
    $display_name = $_SESSION['username'];
}

$current_uri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>4MEN COLLECTION | Luxury Men's Fashion & Apparel</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    letterSpacing: {
                        'widest-luxury': '0.22em',
                    }
                }
            }
        };
    </script>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body, * {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* Clean storefront navigation */
        .carnage-navbar {
            background-color: rgba(255, 255, 255, 0.76);
            backdrop-filter: saturate(180%) blur(18px);
            -webkit-backdrop-filter: saturate(180%) blur(18px);
            border-bottom: 1px solid rgba(17, 24, 39, 0.06);
            transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease, opacity 0.25s ease, transform 0.25s ease;
        }
        .carnage-navbar.home-hero-navbar {
            margin-bottom: -80px;
            background-color: transparent;
            border-bottom-color: transparent;
            box-shadow: none;
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }
        .carnage-navbar.scrolled {
            background-color: rgba(255, 255, 255, 0.94);
            border-bottom-color: rgba(17, 24, 39, 0.1);
            box-shadow: 0 8px 24px -18px rgba(0, 0, 0, 0.34);
        }
        .carnage-navbar.home-hero-navbar:not(.scrolled) .universal-drawer-trigger,
        .carnage-navbar.home-hero-navbar:not(.scrolled) #search-toggle-btn,
        .carnage-navbar.home-hero-navbar:not(.scrolled) .relative.group > a[aria-label="Account"],
        .carnage-navbar.home-hero-navbar:not(.scrolled) > div > div > div > a[aria-label="Cart"],
        .carnage-navbar.home-hero-navbar:not(.scrolled) nav a,
        .carnage-navbar.home-hero-navbar:not(.scrolled) nav button {
            color: #fff !important;
        }
        .carnage-navbar.home-hero-navbar:not(.scrolled) nav a > span:last-child {
            background-color: #fff;
        }
        .carnage-navbar.home-hero-navbar:not(.scrolled) .universal-drawer-trigger:hover,
        .carnage-navbar.home-hero-navbar:not(.scrolled) #search-toggle-btn:hover,
        .carnage-navbar.home-hero-navbar:not(.scrolled) .relative.group > a[aria-label="Account"]:hover,
        .carnage-navbar.home-hero-navbar:not(.scrolled) > div > div > div > a[aria-label="Cart"]:hover {
            background-color: rgba(255, 255, 255, 0.12);
        }
        .carnage-navbar.home-hero-navbar:not(.scrolled) .w-9.h-9,
        .carnage-navbar.home-hero-navbar:not(.scrolled) .sm\:w-10.sm\:h-10 {
            background-color: #fff;
            color: #111;
        }
        .carnage-navbar.home-hero-navbar:not(.scrolled) a[href="/"] .text-neutral-900,
        .carnage-navbar.home-hero-navbar:not(.scrolled) a[href="/"] .text-neutral-500 {
            color: #fff !important;
        }
        .carnage-navbar.home-hero-navbar.scrolled {
            background-color: rgba(0, 0, 0, 0.96);
            border-bottom-color: rgba(255, 255, 255, 0.12);
            box-shadow: 0 8px 24px -18px rgba(0, 0, 0, 0.7);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }
        .carnage-navbar.home-hero-navbar.scrolled .universal-drawer-trigger,
        .carnage-navbar.home-hero-navbar.scrolled #search-toggle-btn,
        .carnage-navbar.home-hero-navbar.scrolled .relative.group > a[aria-label="Account"],
        .carnage-navbar.home-hero-navbar.scrolled > div > div > div > a[aria-label="Cart"],
        .carnage-navbar.home-hero-navbar.scrolled nav a,
        .carnage-navbar.home-hero-navbar.scrolled nav button,
        .carnage-navbar.home-hero-navbar.scrolled a[href="/"] .text-neutral-900,
        .carnage-navbar.home-hero-navbar.scrolled a[href="/"] .text-neutral-500 {
            color: #fff !important;
        }
        .carnage-navbar.home-hero-navbar.scrolled nav a > span:last-child {
            background-color: #fff;
        }
        .carnage-navbar.home-hero-navbar.scrolled .universal-drawer-trigger:hover,
        .carnage-navbar.home-hero-navbar.scrolled #search-toggle-btn:hover,
        .carnage-navbar.home-hero-navbar.scrolled .relative.group > a[aria-label="Account"]:hover,
        .carnage-navbar.home-hero-navbar.scrolled > div > div > div > a[aria-label="Cart"]:hover {
            background-color: rgba(255, 255, 255, 0.12);
        }
        .carnage-navbar.home-hero-navbar.scrolled .w-9.h-9,
        .carnage-navbar.home-hero-navbar.scrolled .sm\:w-10.sm\:h-10 {
            background-color: #fff;
            color: #111;
        }
        @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
            .carnage-navbar { background-color: rgba(255, 255, 255, 0.96); }
        }
        @media (prefers-reduced-motion: reduce) {
            .carnage-navbar { transition: none; }
        }
        @media (max-width: 639px) {
            .carnage-navbar.home-hero-navbar { margin-bottom: -72px; }
        }

        /* Custom Sleek Scrollbar */
        .drawer-scrollbar::-webkit-scrollbar { width: 4px; }
        .drawer-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .drawer-scrollbar::-webkit-scrollbar-thumb { background: #333333; border-radius: 9999px; }
        .drawer-scrollbar::-webkit-scrollbar-thumb:hover { background: #555555; }
    </style>

    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-white dark:bg-[#09090b] text-neutral-900 dark:text-neutral-100 flex flex-col min-h-screen antialiased selection:bg-black selection:text-white">

    <!-- ========================================================================= -->
    <!-- 1. MODERN STOREFRONT NAVIGATION: BRAND, SHOP LINKS, AND QUICK ACTIONS -->
    <!-- ========================================================================= -->
    <header id="main-navbar" class="carnage-navbar <?= ($current_uri === '/' || parse_url($current_uri, PHP_URL_PATH) === '/home') ? 'home-hero-navbar' : ''; ?> sticky top-0 z-40 w-full text-neutral-900">
        <div class="w-full px-4 sm:px-6 lg:px-10 xl:px-14">
            <div class="flex items-center justify-between h-18 sm:h-20 gap-4">
                
                <!-- LEFT SIDE: Mobile Menu Trigger + 4MEN COLLECTION Brand Logo -->
                <div class="flex items-center gap-3.5 shrink-0">
                    <!-- Mobile Drawer Toggle Trigger -->
                    <button type="button" class="universal-drawer-trigger lg:hidden rounded-xl p-2 text-neutral-700 transition hover:bg-neutral-100 hover:text-black focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400" aria-label="Open Navigation" aria-controls="main-drawer" aria-expanded="false">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                    </button>

                    <!-- 4MEN COLLECTION Admin-Style Luxury Brand Logo -->
                    <a href="/" class="flex items-center gap-2.5 sm:gap-3 group transition-transform duration-200 hover:scale-[1.02] shrink-0">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-black text-white flex items-center justify-center font-black text-xs sm:text-sm shadow-sm shrink-0 tracking-tight">
                            4M
                        </div>
                        <div class="flex flex-col text-left leading-tight shrink-0">
                            <span class="text-base sm:text-lg font-black tracking-widest text-neutral-900 uppercase whitespace-nowrap">4MEN</span>
                            <span class="text-[9px] font-bold tracking-[0.28em] text-neutral-500 uppercase mt-0.5 whitespace-nowrap">COLLECTION</span>
                        </div>
                    </a>
                </div>

                <!-- CENTER: Navigation Function Links (Bold, Uppercase, Carnage Style, Strictly 1-Line) -->
                <nav class="hidden lg:flex items-center justify-center flex-1 flex-nowrap gap-3 lg:gap-4 xl:gap-7 2xl:gap-8 text-[11px] lg:text-xs xl:text-[13px] font-bold uppercase tracking-[0.08em] xl:tracking-[0.12em] whitespace-nowrap px-1 xl:px-4" aria-label="Main navigation">
                    <a href="/" class="relative py-2 text-neutral-700 hover:text-black transition group shrink-0 whitespace-nowrap <?= ($current_uri === '/' || $current_uri === '/home') ? 'text-black' : '' ?>">
                        <span class="whitespace-nowrap inline-block">HOME</span>
                        <span class="absolute bottom-0 left-0 h-[2px] bg-black transition-all duration-200 <?= ($current_uri === '/' || $current_uri === '/home') ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                    </a>

                    <a href="/shop" class="relative py-2 text-neutral-700 hover:text-black transition group shrink-0 whitespace-nowrap <?= (strpos($current_uri, '/shop') !== false && !isset($_GET['filter'])) ? 'text-black' : '' ?>">
                        <span class="whitespace-nowrap inline-block">SHOP</span>
                        <span class="absolute bottom-0 left-0 h-[2px] bg-black transition-all duration-200 <?= (strpos($current_uri, '/shop') !== false && !isset($_GET['filter'])) ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                    </a>

                    <!-- Collections Dropdown Menu -->
                    <div class="relative group py-2 shrink-0 whitespace-nowrap">
                        <button type="button" aria-haspopup="true" class="flex items-center gap-1.5 text-neutral-700 hover:text-black transition focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 focus-visible:ring-offset-4 uppercase tracking-[0.08em] xl:tracking-[0.12em] font-bold whitespace-nowrap shrink-0">
                            <span class="whitespace-nowrap inline-block">COLLECTIONS</span>
                            <svg class="w-3.5 h-3.5 stroke-[2.5] text-neutral-400 group-hover:text-black group-hover:rotate-180 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div class="absolute top-full left-1/2 -translate-x-1/2 w-64 bg-white/95 backdrop-blur-xl border border-neutral-200 rounded-2xl shadow-xl p-2.5 opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-all duration-200 transform translate-y-2 group-hover:translate-y-0 group-focus-within:translate-y-0 z-50">
                            <div class="space-y-1">
                                <a href="/shop" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold text-neutral-900 hover:bg-neutral-100 transition">
                                    <span>View All Apparel</span>
                                    <span class="text-[9px] bg-white text-black px-1.5 py-0.5 rounded font-black tracking-wider">ALL</span>
                                </a>
                                <div class="h-px bg-neutral-100 my-1"></div>
                                <?php if (!empty($category_tree)): ?>
                                    <?php foreach (array_slice($category_tree, 0, 7) as $cat): ?>
                                        <a href="/category/<?= htmlspecialchars($cat['slug']) ?>" class="flex items-center justify-between px-3.5 py-2 rounded-xl text-xs font-semibold text-neutral-600 hover:text-black hover:bg-neutral-100 transition">
                                            <span><?= htmlspecialchars($cat['name']) ?></span>
                                            <svg class="w-3 h-3 stroke-[2] text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <a href="/shop" class="block px-3.5 py-2 text-xs text-neutral-600 hover:text-black">Suits & Blazers</a>
                                    <a href="/shop" class="block px-3.5 py-2 text-xs text-neutral-600 hover:text-black">Oxford Shirts</a>
                                    <a href="/shop" class="block px-3.5 py-2 text-xs text-neutral-600 hover:text-black">Trousers & Chinos</a>
                                    <a href="/shop" class="block px-3.5 py-2 text-xs text-neutral-600 hover:text-black">Luxury Polos</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <a href="/shop?filter=new" class="relative py-2 text-neutral-700 hover:text-black transition group shrink-0 whitespace-nowrap <?= (isset($_GET['filter']) && $_GET['filter'] === 'new') ? 'text-black' : '' ?>">
                        <span class="whitespace-nowrap inline-block">NEW&nbsp;ARRIVALS</span>
                        <span class="absolute bottom-0 left-0 h-[2px] bg-black transition-all duration-200 <?= (isset($_GET['filter']) && $_GET['filter'] === 'new') ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                    </a>

                    <a href="/about" class="relative py-2 text-neutral-700 hover:text-black transition group shrink-0 whitespace-nowrap <?= (strpos($current_uri, '/about') !== false) ? 'text-black' : '' ?>">
                        <span class="whitespace-nowrap inline-block">ABOUT&nbsp;US</span>
                        <span class="absolute bottom-0 left-0 h-[2px] bg-black transition-all duration-200 <?= (strpos($current_uri, '/about') !== false) ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                    </a>

                    <a href="/contact" class="relative py-2 text-neutral-700 hover:text-black transition group shrink-0 whitespace-nowrap <?= (strpos($current_uri, '/contact') !== false) ? 'text-black' : '' ?>">
                        <span class="whitespace-nowrap inline-block">CONTACT</span>
                        <span class="absolute bottom-0 left-0 h-[2px] bg-black transition-all duration-200 <?= (strpos($current_uri, '/contact') !== false) ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                    </a>
                </nav>

                <!-- RIGHT SIDE: Actions (Search, Account, Shopping Bag) -->
                <div class="flex items-center justify-end gap-3 sm:gap-4 shrink-0">
                    
                    <!-- Search Icon Button -->
                    <button type="button" id="search-toggle-btn" onclick="toggleSearchOverlay()" class="rounded-full p-2.5 text-neutral-700 transition hover:bg-neutral-100 hover:text-black focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400" title="Search Apparel" aria-label="Search" aria-controls="search-overlay" aria-expanded="false">
                        <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </button>

                    <!-- User Account / Profile -->
                    <div class="relative group">
                        <a href="<?= isset($_SESSION['user_id']) ? '/account' : '/login'; ?>" class="flex items-center rounded-full p-2.5 text-neutral-700 transition hover:bg-neutral-100 hover:text-black" title="My Account" aria-label="Account">
                            <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </a>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <!-- Logged In User Dropdown -->
                            <div class="absolute right-0 top-full mt-2 w-52 rounded-2xl border border-neutral-200 bg-white/95 p-2.5 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-all duration-200 z-50">
                                <div class="px-3 py-2 border-b border-neutral-100">
                                    <p class="text-[10px] uppercase font-bold tracking-wider text-neutral-500">Signed in as</p>
                                    <p class="text-xs font-bold text-neutral-900 truncate"><?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="space-y-1 mt-1.5">
                                    <a href="/account" class="block px-3 py-2 text-xs font-medium text-neutral-700 hover:text-black hover:bg-neutral-100 rounded-xl transition">My Orders & Profile</a>
                                    <?php if (in_array($_SESSION['user_role'] ?? '', ['admin', 'shop_admin'])): ?>
                                        <a href="/admin" class="block px-3 py-2 text-xs font-bold text-neutral-900 hover:bg-neutral-100 rounded-xl transition">Admin Dashboard</a>
                                    <?php endif; ?>
                                    <a href="/logout" class="block px-3 py-2 text-xs font-bold text-rose-400 hover:bg-rose-950/40 rounded-xl transition">Sign Out</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Shopping Bag Icon (Carnage Style Minimalist Bag) -->
                    <a href="/cart" class="relative flex items-center rounded-full p-2.5 text-neutral-700 transition hover:bg-neutral-100 hover:text-black group" title="Shopping Bag" aria-label="Cart">
                        <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                        </svg>
                        <!-- Badge Counter -->
                        <span id="cart-badge-desktop" class="<?= $cart_count > 0 ? 'flex' : 'hidden'; ?> absolute top-0 right-0 bg-black text-white text-[9px] font-black min-w-[1rem] h-[1rem] px-1 rounded-full items-center justify-center shadow-sm">
                            <?= $cart_count; ?>
                        </span>
                        <span id="cart-total-desktop" class="hidden"><?= number_format($cart_total, 2); ?></span>
                    </a>

                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- SLIDE-DOWN SEARCH BAR OVERLAY (DARK GLASS) -->
        <!-- ========================================================================= -->
        <div id="search-overlay" class="hidden border-t border-neutral-100 bg-white/95 px-4 py-4 shadow-lg backdrop-blur-xl sm:px-8 transition-all">
            <div class="max-w-3xl mx-auto">
                <form action="/shop" method="GET" class="relative flex items-center">
                    <input 
                        type="text" 
                        name="search" 
                        id="search-input"
                        placeholder="Search suits, tailored blazers, oxford shirts, chinos, polos..." 
                        class="w-full pl-12 pr-12 py-3.5 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs sm:text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/5 font-medium transition"
                    >
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-neutral-500">
                        <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    </span>
                    <button type="button" onclick="toggleSearchOverlay()" class="absolute inset-y-0 right-0 flex items-center pr-4 text-neutral-500 hover:text-black transition">
                        <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </form>

                <div class="flex items-center gap-2 mt-3 text-[11px] text-neutral-500 overflow-x-auto pb-1">
                    <span class="font-bold uppercase tracking-wider text-neutral-500 shrink-0">Popular:</span>
                    <a href="/shop?search=Shirt" class="px-2.5 py-1 rounded-lg bg-white border border-neutral-200 hover:bg-neutral-900 hover:text-white transition shrink-0 font-medium">Oxford Shirts</a>
                    <a href="/shop?search=Chino" class="px-2.5 py-1 rounded-lg bg-white border border-neutral-200 hover:bg-neutral-900 hover:text-white transition shrink-0 font-medium">Stretch Chinos</a>
                    <a href="/shop?search=Blazer" class="px-2.5 py-1 rounded-lg bg-white border border-neutral-200 hover:bg-neutral-900 hover:text-white transition shrink-0 font-medium">Tailored Blazers</a>
                    <a href="/shop?search=Polo" class="px-2.5 py-1 rounded-lg bg-white border border-neutral-200 hover:bg-neutral-900 hover:text-white transition shrink-0 font-medium">Pique Polos</a>
                </div>
            </div>
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- 2. MOBILE SLIDE-OUT DRAWER (4MEN LUXURY APPAREL) -->
    <!-- ========================================================================= -->
    <div id="drawer-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[998] opacity-0 pointer-events-none transition-opacity duration-300 ease-in-out"></div>

    <aside id="main-drawer" class="fixed top-0 left-0 h-full w-[320px] sm:w-[380px] bg-white dark:bg-neutral-900 z-[999] shadow-2xl transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col overflow-hidden border-r border-neutral-200 dark:border-neutral-800">
        
        <!-- Drawer Header -->
        <div class="p-5 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-black text-white dark:bg-white dark:text-black flex items-center justify-center font-black text-xs shadow-md tracking-tight">
                    4M
                </div>
                <div class="flex flex-col leading-tight">
                    <span class="text-base font-black tracking-wider text-neutral-900 dark:text-white uppercase">4MEN</span>
                    <span class="text-[9px] font-bold tracking-[0.25em] text-neutral-500 uppercase">COLLECTION</span>
                </div>
            </a>
            
            <button type="button" id="drawer-close-btn" class="w-8 h-8 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-500 hover:text-black dark:hover:text-white flex items-center justify-center transition focus:outline-none">
                <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Drawer Content -->
        <div class="flex-1 overflow-y-auto drawer-scrollbar p-5 space-y-6">
            
            <!-- Quick Mobile Search -->
            <form action="/shop" method="GET">
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search 4MEN Collection..." 
                        class="w-full pl-10 pr-4 py-2.5 bg-neutral-100 dark:bg-neutral-800 rounded-xl text-xs text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-black/10 font-medium"
                    >
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-neutral-400">
                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    </span>
                </div>
            </form>

            <!-- Primary Nav Links -->
            <div>
                <p class="text-[10px] font-bold tracking-[0.22em] text-neutral-400 uppercase mb-2.5">Menu</p>
                <div class="space-y-1">
                    <a href="/" class="flex items-center justify-between p-2.5 rounded-xl text-xs font-bold text-neutral-900 dark:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                        <span>Home</span>
                    </a>
                    <a href="/shop" class="flex items-center justify-between p-2.5 rounded-xl text-xs font-bold text-neutral-900 dark:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                        <span>Shop All</span>
                        <span class="text-[9px] bg-black text-white dark:bg-white dark:text-black px-1.5 py-0.5 rounded font-black">ALL</span>
                    </a>
                    <a href="/shop?filter=new" class="flex items-center justify-between p-2.5 rounded-xl text-xs font-bold text-neutral-900 dark:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                        <span class="whitespace-nowrap">New Arrivals</span>
                        <span class="text-[9px] bg-emerald-600 text-white px-1.5 py-0.5 rounded font-bold shrink-0">HOT</span>
                    </a>
                    <a href="/about" class="flex items-center justify-between p-2.5 rounded-xl text-xs font-bold text-neutral-900 dark:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                        <span class="whitespace-nowrap">About Us</span>
                    </a>
                    <a href="/contact" class="flex items-center justify-between p-2.5 rounded-xl text-xs font-bold text-neutral-900 dark:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                        <span>Contact & Inquiries</span>
                    </a>
                </div>
            </div>

            <!-- Categories Accordion Section -->
            <?php if (!empty($category_tree)): ?>
            <div class="border-t border-neutral-100 dark:border-neutral-800 pt-5">
                <p class="text-[10px] font-bold tracking-[0.22em] text-neutral-400 uppercase mb-2.5">Categories</p>
                <div class="space-y-1.5">
                    <?php foreach ($category_tree as $cat_id => $parent_cat): 
                        $has_children = !empty($parent_cat['children']);
                    ?>
                        <div class="border border-neutral-100 dark:border-neutral-800 rounded-xl overflow-hidden bg-neutral-50/50 dark:bg-neutral-800/40">
                            <div class="flex items-center justify-between p-2.5">
                                <a href="/category/<?= htmlspecialchars($parent_cat['slug']) ?>" class="text-xs font-bold text-neutral-800 dark:text-neutral-200 hover:text-black dark:hover:text-white flex-1 truncate">
                                    <?= htmlspecialchars($parent_cat['name']) ?>
                                </a>

                                <?php if ($has_children): ?>
                                    <button type="button" onclick="toggleSubCategory('sub-cat-<?= $cat_id ?>', this)" class="p-1 text-neutral-400 hover:text-black dark:hover:text-white transition" title="Toggle Sub-categories">
                                        <svg class="w-3.5 h-3.5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if ($has_children): ?>
                                <div id="sub-cat-<?= $cat_id ?>" class="hidden bg-white dark:bg-neutral-900 border-t border-neutral-100 dark:border-neutral-800 px-3 py-2 space-y-1">
                                    <?php foreach ($parent_cat['children'] as $child): ?>
                                        <a href="/category/<?= htmlspecialchars($child['slug']) ?>" class="flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-medium text-neutral-600 dark:text-neutral-400 hover:text-black dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                                            <span><?= htmlspecialchars($child['name']) ?></span>
                                            <svg class="w-3 h-3 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Contact Box -->
            <div class="bg-neutral-50 dark:bg-neutral-800/60 p-4 rounded-2xl border border-neutral-100 dark:border-neutral-800 space-y-1 text-xs">
                <p class="font-bold text-neutral-900 dark:text-white">Customer Support</p>
                <p class="text-neutral-500 font-medium">+94 (77) 123-4567</p>
                <p class="text-neutral-500 font-medium">support@4mencollection.lk</p>
            </div>
        </div>

        <!-- Drawer Footer Account Bar -->
        <div class="p-4 border-t border-neutral-100 dark:border-neutral-800 bg-white dark:bg-neutral-900">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-black text-white dark:bg-white dark:text-black font-extrabold flex items-center justify-center text-xs">
                            <?= strtoupper(substr($display_name, 0, 1)); ?>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[9px] text-neutral-400 font-bold uppercase tracking-wider">Signed in</span>
                            <span class="text-xs font-bold text-neutral-900 dark:text-white leading-tight truncate max-w-[140px]"><?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <a href="/account" class="text-xs font-bold text-neutral-900 dark:text-white hover:underline">My Account</a>
                </div>
                <a href="/logout" class="block text-center w-full py-2.5 text-xs font-bold text-rose-600 bg-rose-50 dark:bg-rose-950/40 rounded-xl transition">Sign Out</a>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-2">
                    <a href="/login" class="text-center py-2.5 text-xs font-bold text-neutral-900 dark:text-white border border-neutral-200 dark:border-neutral-700 rounded-xl hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">Sign In</a>
                    <a href="/register" class="text-center py-2.5 text-xs font-bold text-white bg-black dark:bg-white dark:text-black hover:bg-neutral-800 rounded-xl transition">Register</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ========================================================================= -->
    <!-- 3. NAVIGATION JAVASCRIPT & CART SYNC -->
    <!-- ========================================================================= -->
    <script>
        // Keep the glass header readable as the page moves beneath it.
        function updateNavbarOnScroll() {
            const navbar = document.getElementById('main-navbar');
            if (!navbar) return;
            if (window.scrollY > 20) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
        window.addEventListener('scroll', updateNavbarOnScroll, { passive: true });
        updateNavbarOnScroll();

        // Search Overlay Toggle
        function toggleSearchOverlay() {
            const overlay = document.getElementById('search-overlay');
            const input = document.getElementById('search-input');
            const toggle = document.getElementById('search-toggle-btn');
            if (!overlay) return;

            const isOpening = overlay.classList.contains('hidden');
            overlay.classList.toggle('hidden', !isOpening);
            if (toggle) toggle.setAttribute('aria-expanded', String(isOpening));

            if (isOpening) {
                if (input) setTimeout(() => input.focus(), 50);
            }
        }

        // Mobile Drawer Toggle
        document.addEventListener("DOMContentLoaded", function () {
            const closeBtn = document.getElementById('drawer-close-btn');
            const drawer = document.getElementById('main-drawer');
            const overlay = document.getElementById('drawer-overlay');
            const drawerTriggers = document.querySelectorAll('.universal-drawer-trigger');

            function openDrawer() {
                if (!drawer || !overlay) return;
                drawer.classList.remove('-translate-x-full');
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                overlay.classList.add('opacity-100');
                document.body.classList.add('overflow-hidden');
                drawerTriggers.forEach(btn => btn.setAttribute('aria-expanded', 'true'));
            }

            function closeDrawer() {
                if (!drawer || !overlay) return;
                drawer.classList.add('-translate-x-full');
                overlay.classList.remove('opacity-100');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                document.body.classList.remove('overflow-hidden');
                drawerTriggers.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
            }

            drawerTriggers.forEach(btn => btn.addEventListener('click', openDrawer));
            if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
            if (overlay) overlay.addEventListener('click', closeDrawer);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    if (drawer && !drawer.classList.contains('-translate-x-full')) {
                        closeDrawer();
                    }
                    const searchOverlay = document.getElementById('search-overlay');
                    if (searchOverlay && !searchOverlay.classList.contains('hidden')) {
                        searchOverlay.classList.add('hidden');
                        document.getElementById('search-toggle-btn')?.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });

        // Sub-category Accordion
        function toggleSubCategory(id, btn) {
            const container = document.getElementById(id);
            if (!container) return;
            const svg = btn.querySelector('svg');
            
            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                if (svg) svg.classList.add('rotate-180');
            } else {
                container.classList.add('hidden');
                if (svg) svg.classList.remove('rotate-180');
            }
        }

        // Global Cart Counter & Subtotal Handler
        function updateCartBadgeGlobal(quantity, total = null, priceDelta = null) {
            const deskBadge = document.getElementById('cart-badge-desktop');
            const deskTotal = document.getElementById('cart-total-desktop');

            if (deskBadge) {
                let currentCount = parseInt(deskBadge.innerText) || 0;
                let newCount = currentCount + parseInt(quantity);
                deskBadge.innerText = newCount;
                if (newCount > 0) {
                    deskBadge.classList.remove('hidden');
                    deskBadge.classList.add('flex');
                }
            }

            if (deskTotal) {
                let newTotalVal = null;
                if (total !== null && !isNaN(parseFloat(total))) {
                    newTotalVal = parseFloat(total);
                } else if (priceDelta !== null && !isNaN(parseFloat(priceDelta))) {
                    let currentVal = parseFloat(deskTotal.innerText.replace(/,/g, '')) || 0.00;
                    newTotalVal = currentVal + parseFloat(priceDelta);
                }

                if (newTotalVal !== null) {
                    deskTotal.innerText = newTotalVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            }
        }

        document.addEventListener('cartUpdated', function(e) {
            if (e.detail && e.detail.quantity) {
                updateCartBadgeGlobal(
                    e.detail.quantity, 
                    e.detail.total !== undefined ? e.detail.total : null,
                    e.detail.priceDelta !== undefined ? e.detail.priceDelta : null
                );
            }
        });
    </script>

    <!-- Main Content Container Open -->
    <main class="flex-1 w-full">