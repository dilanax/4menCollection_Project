<?php
// Session ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection fallback for header
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (file_exists('config/db.php')) {
        require_once 'config/db.php';
    } elseif (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    }
}

// Excluded category slugs/names
$excluded_category_names = ['vinura ransika', 'vinura-ransika', 'vinura_ransika'];

// Fetch categories from Database
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

// Cart Quantity සහ සත්‍ය Subtotal ගණනය කිරීම (Database Price Lookup Fallback)
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

// Username එක ලබා ගැනීම
$display_name = 'User';
if (!empty($_SESSION['user_name'])) {
    $display_name = $_SESSION['user_name'];
} elseif (!empty($_SESSION['username'])) {
    $display_name = $_SESSION['username'];
}

$current_uri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="si" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReadersHub | Modern Online Bookshop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body, * { font-family: 'Poppins', sans-serif; }
        .drawer-scrollbar::-webkit-scrollbar { width: 4px; }
        .drawer-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .drawer-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .drawer-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
    
    <style>
        /* Dark Theme Global Overrides */
        html.dark {
            color-scheme: dark;
        }
        html.dark body {
            background-color: #0b0f19 !important;
            color: #cbd5e1 !important;
        }
        html.dark .bg-white,
        html.dark .bg-\[\#f8fafc\] {
            background-color: #111827 !important;
        }
        html.dark .bg-slate-50,
        html.dark .bg-slate-50\/50,
        html.dark .bg-slate-50\/60,
        html.dark .bg-slate-50\/70,
        html.dark .bg-slate-50\/80,
        html.dark .bg-slate-50\/90 {
            background-color: #1e293b !important;
        }
        html.dark .text-slate-900,
        html.dark .text-slate-800 {
            color: #f8fafc !important;
        }
        html.dark .text-slate-700,
        html.dark .text-slate-600 {
            color: #cbd5e1 !important;
        }
        html.dark .text-slate-500,
        html.dark .text-slate-400 {
            color: #94a3b8 !important;
        }
        html.dark .border-slate-200,
        html.dark .border-slate-200\/90,
        html.dark .border-slate-100 {
            border-color: #334155 !important;
        }
        html.dark input,
        html.dark textarea,
        html.dark select {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-color: #475569 !important;
        }

        /* Header Dark Mode Specific Styling */
        html.dark header,
        html.dark header.bg-white\/95 {
            background-color: rgba(15, 23, 42, 0.95) !important;
            border-color: #1e293b !important;
        }
        html.dark header .text-slate-900,
        html.dark header .text-slate-800,
        html.dark header .text-slate-700 {
            color: #f8fafc !important;
        }
        html.dark header form > div,
        html.dark header .border-slate-200,
        html.dark header .border-slate-300 {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        html.dark header input {
            color: #f8fafc !important;
        }
        html.dark header .bg-sky-50 {
            background-color: #1e293b !important;
            color: #38bdf8 !important;
            border-color: #0284c7 !important;
        }
        html.dark header .hover\:bg-slate-50:hover,
        html.dark header .hover\:bg-sky-50:hover {
            background-color: #1e293b !important;
        }

        /* Drawer Dark Mode */
        html.dark #main-drawer {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }
        html.dark #main-drawer .bg-white,
        html.dark #main-drawer .from-sky-50\/80,
        html.dark #main-drawer .to-white {
            background: #1e293b !important;
            background-color: #1e293b !important;
        }
        html.dark #main-drawer .text-slate-900,
        html.dark #main-drawer .text-slate-800 {
            color: #f8fafc !important;
        }
        html.dark #main-drawer .text-slate-600,
        html.dark #main-drawer .text-slate-700 {
            color: #cbd5e1 !important;
        }
        html.dark #main-drawer .border-slate-100,
        html.dark #main-drawer .border-slate-200 {
            border-color: #334155 !important;
        }
        html.dark #main-drawer .bg-slate-50,
        html.dark #main-drawer .bg-slate-50\/80 {
            background-color: #0f172a !important;
        }
        html.dark #main-drawer .hover\:bg-sky-50:hover,
        html.dark #main-drawer .hover\:bg-slate-50:hover {
            background-color: #334155 !important;
        }
    </style>

    <script>
        // Default to Light (White) theme unless 'dark' is explicitly saved
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-[#f8fafc] text-slate-800 flex flex-col min-h-screen antialiased">

    <!-- ==================== 1. UNIVERSAL CATEGORIES & SUB-CATEGORIES SIDEBAR ==================== -->
    <div id="drawer-overlay" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-[998] opacity-0 pointer-events-none transition-opacity duration-300 ease-in-out"></div>

    <aside id="main-drawer" class="fixed top-0 left-0 h-full w-[320px] sm:w-[380px] bg-white z-[999] shadow-2xl transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col overflow-hidden">
        
        <!-- Drawer Header -->
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-sky-50/80 to-white">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-sky-700 text-white flex items-center justify-center font-black shadow-sm">
                    <svg class="w-5 h-5 stroke-[2.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-slate-900 leading-tight">All Categories</h3>
                    <p class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">Books & Collections</p>
                </div>
            </div>
            
            <button type="button" id="drawer-close-btn" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center transition focus:outline-none">
                <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Drawer Content -->
        <div class="flex-1 overflow-y-auto drawer-scrollbar p-4 sm:p-5 space-y-6">
            
            <div>
                <div class="space-y-1.5">
                    
                    <!-- All Books Link -->
                    <a href="/shop" class="flex items-center justify-between p-3 rounded-xl hover:bg-sky-50 text-slate-800 hover:text-sky-700 font-bold text-xs sm:text-sm transition group border border-slate-100">
                        <span class="flex items-center gap-3">
                            <span class="w-2 h-2 rounded-full bg-sky-700"></span>
                            <span>Browse All Books (Shop)</span>
                        </span>
                        <span class="text-[10px] bg-sky-100 text-sky-800 font-black px-2 py-0.5 rounded-md">ALL</span>
                    </a>

                    <!-- Category Items with Sub-Category Dropdown / Expand -->
                    <?php if (!empty($category_tree)): ?>
                        <?php foreach ($category_tree as $cat_id => $parent_cat): 
                            $has_children = !empty($parent_cat['children']);
                            $cat_img = !empty($parent_cat['image']) 
                                ? ((str_starts_with($parent_cat['image'], 'http://') || str_starts_with($parent_cat['image'], 'https://')) ? $parent_cat['image'] : '/' . ltrim(htmlspecialchars($parent_cat['image']), '/')) 
                                : null;
                        ?>
                            <div class="border border-slate-100 rounded-xl overflow-hidden bg-white hover:border-slate-300 transition-colors">
                                <div class="flex items-center justify-between p-2.5 sm:p-3">
                                    <a href="/category/<?= htmlspecialchars($parent_cat['slug']) ?>" class="flex items-center gap-3 text-slate-800 hover:text-sky-700 font-bold text-xs sm:text-sm flex-1 min-w-0">
                                        <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shrink-0 overflow-hidden border border-slate-200">
                                            <?php if ($cat_img): ?>
                                                <img src="<?= $cat_img; ?>" alt="<?= htmlspecialchars($parent_cat['name']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <svg class="w-4 h-4 text-sky-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                            <?php endif; ?>
                                        </div>
                                        <span class="truncate"><?= htmlspecialchars($parent_cat['name']) ?></span>
                                    </a>

                                    <?php if ($has_children): ?>
                                        <button type="button" onclick="toggleSubCategory('sub-cat-<?= $cat_id ?>', this)" class="p-1.5 text-slate-400 hover:text-sky-700 hover:bg-slate-100 rounded-lg transition" title="Toggle Sub-categories">
                                            <svg class="w-4 h-4 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    <?php else: ?>
                                        <a href="/category/<?= htmlspecialchars($parent_cat['slug']) ?>" class="text-slate-300 hover:text-sky-700 p-1">
                                            <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Sub-Categories Accordion Body -->
                                <?php if ($has_children): ?>
                                    <div id="sub-cat-<?= $cat_id ?>" class="hidden bg-slate-50/80 border-t border-slate-100 px-3 py-2 space-y-1">
                                        <?php foreach ($parent_cat['children'] as $child): ?>
                                            <a href="/category/<?= htmlspecialchars($child['slug']) ?>" class="flex items-center justify-between py-1.5 px-3 rounded-lg text-xs font-semibold text-slate-600 hover:text-sky-700 hover:bg-white transition">
                                                <span class="flex items-center gap-2">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                                    <?= htmlspecialchars($child['name']) ?>
                                                </span>
                                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Quick Navigation Menu -->
            <div class="border-t border-slate-100 pt-5">
                <p class="text-[11px] font-bold tracking-widest text-slate-400 uppercase mb-3">Quick Links</p>
                <div class="space-y-1">
                    <a href="/" class="flex items-center gap-3 p-2.5 rounded-xl text-xs sm:text-sm font-semibold transition <?= ($current_uri === '/' || $current_uri === '/home') ? 'bg-sky-50 text-sky-700' : 'text-slate-700 hover:bg-slate-50 hover:text-sky-700' ?>">
                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                        <span>Home</span>
                    </a>
                    <a href="/authors" class="flex items-center gap-3 p-2.5 rounded-xl text-xs sm:text-sm font-semibold transition <?= (strpos($current_uri, '/authors') !== false) ? 'bg-sky-50 text-sky-700' : 'text-slate-700 hover:bg-slate-50 hover:text-sky-700' ?>">
                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        <span>Authors & Writers</span>
                    </a>
                    <a href="/publishers" class="flex items-center gap-3 p-2.5 rounded-xl text-xs sm:text-sm font-semibold transition <?= (strpos($current_uri, '/publishers') !== false) ? 'bg-sky-50 text-sky-700' : 'text-slate-700 hover:bg-slate-50 hover:text-sky-700' ?>">
                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span>Publishers</span>
                    </a>
                    <a href="/shop?stock=sale" class="flex items-center gap-3 p-2.5 rounded-xl text-xs sm:text-sm font-semibold text-rose-600 hover:bg-rose-50 transition">
                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                        <span>Special Offers & Discounts</span>
                    </a>
                    <a href="/blog" class="flex items-center gap-3 p-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-sky-700 transition <?= (strpos($current_uri, '/blog') !== false) ? 'bg-sky-50 text-sky-700' : '' ?>">
                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        <span>Literary Blog & Reviews</span>
                    </a>
                    <a href="/contact" class="flex items-center gap-3 p-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-sky-700 transition <?= (strpos($current_uri, '/contact') !== false) ? 'bg-sky-50 text-sky-700' : '' ?>">
                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                        <span>Contact ReadersHub</span>
                    </a>
                </div>
            </div>

            <!-- Need Help Box -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1.5 text-xs text-slate-600">
                <div class="font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-4 h-4 text-sky-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Need Help? Hotline
                </div>
                <div class="font-semibold text-slate-800">+94 (77) 123-4567</div>
                <div class="text-slate-500">support@readershub.lk</div>
            </div>
        </div>

        <!-- Drawer Footer Account Bar -->
        <div class="p-4 border-t border-slate-100 bg-white">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-sky-100 text-sky-800 font-bold flex items-center justify-center text-xs">
                            <?= strtoupper(substr($display_name, 0, 1)); ?>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-slate-400 font-medium">Signed in</span>
                            <span class="text-xs font-bold text-slate-900 leading-tight truncate max-w-[140px]"><?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <a href="/account" class="text-xs font-bold text-sky-700 hover:underline">Account</a>
                </div>
                <a href="/logout" class="block text-center w-full py-2 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition">Log Out</a>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-2">
                    <a href="/login" class="text-center py-2 text-xs font-bold text-slate-700 border border-slate-200 rounded-xl hover:bg-slate-50 transition">Sign In</a>
                    <a href="/register" class="text-center py-2 text-xs font-bold text-white bg-sky-700 hover:bg-sky-800 rounded-xl transition shadow-xs">Register</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ==================== 2. TOP HEADER UTILITY BAR ==================== -->
    <div class="border-b border-slate-800 bg-[#0f172a] text-xs text-slate-300 hidden md:block w-full">
        <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 flex justify-between items-center h-9">
            <div class="flex items-center space-x-5 text-[11px]">
                <a href="tel:+94771234567" class="hover:text-sky-400 transition flex items-center gap-1.5 font-medium">
                    <svg class="w-3.5 h-3.5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>+94 (77) 123-4567</span>
                </a>
                <span class="text-slate-700">|</span>
                <a href="mailto:info@readershub.lk" class="hover:text-sky-400 transition flex items-center gap-1.5 font-medium">
                    <svg class="w-3.5 h-3.5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>info@readershub.lk</span>
                </a>
                <span class="text-slate-700 hidden lg:inline">|</span>
                <span class="hidden lg:inline text-slate-400">⚡ Islandwide Delivery Available</span>
            </div>

            <div class="flex items-center space-x-6 text-[11px] text-slate-300">
                <a href="/authors" class="hover:text-sky-400 transition flex items-center gap-1 font-medium">
                    <span>Authors</span>
                </a>
                <span class="text-slate-700">|</span>
                <a href="/publishers" class="hover:text-sky-400 transition flex items-center gap-1 font-medium">
                    <span>Publishers</span>
                </a>
                <span class="text-slate-700">|</span>
                <a href="/shop" class="hover:text-sky-400 transition flex items-center gap-1 font-medium">
                    <span>Shop Catalog</span>
                </a>
                <span class="text-slate-700">|</span>
                <a href="/cart" class="hover:text-sky-400 transition flex items-center gap-1 font-medium">
                    <span>Shopping Cart</span>
                </a>
                <span class="text-slate-700">|</span>
                <a href="<?= isset($_SESSION['user_id']) ? '/account' : '/login'; ?>" class="hover:text-sky-400 transition flex items-center gap-1.5 font-medium text-slate-200">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <?= isset($_SESSION['user_id']) ? htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') : 'My Account'; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- ==================== 3. MAIN STICKY NAVIGATION ==================== -->
    <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 shadow-xs sticky top-0 z-50 w-full transition-all">
        <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12">
            <div class="flex items-center justify-between h-20 gap-4 lg:gap-8">
                
                <!-- Left: Logo & Categories Trigger Button -->
                <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                    
                    <!-- Mobile Hamburger Toggle Button -->
                    <button type="button" class="universal-drawer-trigger lg:hidden p-2 text-slate-700 hover:text-sky-700 hover:bg-sky-50 rounded-xl transition focus:outline-none" aria-label="Open Mobile Menu">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    </button>

                    <!-- Brand Logo -->
                    <a href="/" class="flex items-center gap-2 group">
                        <span class="text-2xl lg:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center">
                            Readers<span class="text-sky-700 font-black">Hub</span><span class="w-2 h-2 rounded-full bg-sky-600 ml-0.5 inline-block"></span>
                        </span>
                    </a>

                    <!-- Desktop & Laptop "Categories" Trigger Button -->
                    <button type="button" class="universal-drawer-trigger hidden lg:flex items-center gap-2.5 bg-sky-50 hover:bg-sky-700 text-sky-800 hover:text-white border border-sky-200 hover:border-sky-700 px-4 py-2.5 rounded-xl transition-all duration-200 font-bold text-xs tracking-wider uppercase ml-2 shadow-xs group">
                        <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                        <span>Categories</span>
                    </button>
                </div>

                <!-- Middle: Extended Search Bar -->
                <div class="hidden md:flex flex-1 max-w-xl mx-4">
                    <form action="/shop" method="GET" class="w-full">
                        <div class="flex items-center rounded-full border-2 border-slate-200 hover:border-sky-700 focus-within:border-sky-700 bg-white transition duration-200 shadow-xs overflow-hidden p-0.5">
                            <input 
                                type="text" 
                                name="search" 
                                placeholder="Search by title, author, or ISBN..." 
                                class="w-full bg-transparent text-xs sm:text-sm text-slate-800 pl-5 pr-3 py-2 outline-none"
                            >
                            <button type="submit" class="bg-sky-700 hover:bg-sky-800 text-white w-10 h-10 rounded-full flex items-center justify-center shrink-0 transition shadow-sm ml-1" aria-label="Search">
                                <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Center/Right Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center space-x-7 text-sm font-semibold shrink-0">
                    <a href="/" class="text-slate-800 hover:text-sky-700 transition <?= ($current_uri === '/' || $current_uri === '/home') ? 'text-sky-700 font-bold' : '' ?>">Home</a>
                    <a href="/shop" class="text-slate-800 hover:text-sky-700 transition <?= (strpos($current_uri, '/shop') !== false) ? 'text-sky-700 font-bold' : '' ?>">Shop</a>
                    <a href="/authors" class="text-slate-800 hover:text-sky-700 transition <?= (strpos($current_uri, '/authors') !== false) ? 'text-sky-700 font-bold' : '' ?>">Authors</a>
                    <a href="/publishers" class="text-slate-800 hover:text-sky-700 transition <?= (strpos($current_uri, '/publishers') !== false) ? 'text-sky-700 font-bold' : '' ?>">Publishers</a>
                    <a href="/contact" class="text-slate-800 hover:text-sky-700 transition <?= (strpos($current_uri, '/contact') !== false) ? 'text-sky-700 font-bold' : '' ?>">Contact Us</a>
                </nav>

                <!-- Right Action Icons (Wishlist, Account, Cart) -->
                <div class="flex items-center space-x-3 sm:space-x-4 shrink-0">
                    
                    <a href="/account" class="relative hidden sm:inline-flex text-slate-700 hover:text-sky-700 transition p-2 rounded-xl hover:bg-slate-50" title="Wishlist">
                        <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    </a>

                    <a href="<?= isset($_SESSION['user_id']) ? '/account' : '/login'; ?>" class="text-slate-700 hover:text-sky-700 transition p-2 rounded-xl hover:bg-slate-50" title="Account">
                        <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </a>

                    <!-- Cart Indicator with Synchronized Live Amount -->
                    <a href="/cart" class="flex items-center gap-2.5 text-slate-800 hover:text-sky-700 transition p-2 rounded-xl hover:bg-slate-50" title="Shopping Cart">
                        <div class="relative">
                            <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <span id="cart-badge-desktop" class="<?= $cart_count > 0 ? 'flex' : 'hidden'; ?> absolute -top-1.5 -right-2 bg-rose-600 text-white text-[9px] font-black min-w-[1rem] h-[1rem] px-1 rounded-full items-center justify-center shadow">
                                <?= $cart_count; ?>
                            </span>
                        </div>
                        <span class="hidden sm:inline-block font-bold text-xs tracking-tight text-slate-900">
                            Rs. <span id="cart-total-desktop"><?= number_format($cart_total, 2); ?></span>
                        </span>
                    </a>
                </div>

            </div>

            <!-- Mobile Search Bar Below Header -->
            <div class="md:hidden pb-3">
                <form action="/shop" method="GET" class="w-full">
                    <div class="flex items-center rounded-full border border-slate-300 bg-white shadow-xs overflow-hidden p-0.5">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Search books, authors..." 
                            class="w-full bg-transparent text-xs text-slate-800 pl-4 pr-2 py-2 outline-none"
                        >
                        <button type="submit" class="bg-sky-700 text-white w-8 h-8 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <!-- ==================== 4. SIDEBAR & ACCORDION JAVASCRIPT ==================== -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const triggers = document.querySelectorAll('.universal-drawer-trigger');
            const closeBtn = document.getElementById('drawer-close-btn');
            const drawer = document.getElementById('main-drawer');
            const overlay = document.getElementById('drawer-overlay');

            function openDrawer() {
                if (!drawer || !overlay) return;
                drawer.classList.remove('-translate-x-full');
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                overlay.classList.add('opacity-100');
                document.body.classList.add('overflow-hidden');
            }

            function closeDrawer() {
                if (!drawer || !overlay) return;
                drawer.classList.add('-translate-x-full');
                overlay.classList.remove('opacity-100');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                document.body.classList.remove('overflow-hidden');
            }

            triggers.forEach(btn => btn.addEventListener('click', openDrawer));
            if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
            if (overlay) overlay.addEventListener('click', closeDrawer);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && drawer && !drawer.classList.contains('-translate-x-full')) {
                    closeDrawer();
                }
            });
        });

        // Sub-category accordion expand/collapse
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

        // Global Cart Counter & Subtotal Handler (Parses Numbers and Formats with Commas)
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

    <main class="flex-1 w-full">