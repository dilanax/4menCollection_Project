<?php
// Include DB connection fallback
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    } elseif (file_exists('config/db.php')) {
        require_once 'config/db.php';
    }
}


// Fetch Top 6 Trending / Best-selling Books by Order Count
$trending_products = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql_trending = "SELECT p.*, b.name as brand_name, 
                                COALESCE(SUM(oi.quantity), 0) as total_sold,
                                (SELECT c.name FROM categories c 
                                 JOIN product_categories pc ON c.id = pc.category_id 
                                 WHERE pc.product_id = p.id LIMIT 1) as category_name,
                                (SELECT c.slug FROM categories c 
                                 JOIN product_categories pc ON c.id = pc.category_id 
                                 WHERE pc.product_id = p.id LIMIT 1) as category_slug
                         FROM products p
                         JOIN order_items oi ON p.id = oi.product_id
                         LEFT JOIN brands b ON p.brand_id = b.id
                         WHERE (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                         GROUP BY p.id
                         ORDER BY total_sold DESC, p.id DESC
                         LIMIT 6";
        $stmt_trending = $pdo->query($sql_trending);
        if ($stmt_trending) {
            $trending_products = $stmt_trending->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $trending_products = [];
}

// Fallback: Orders කිසිවක් නැති විට සාමාන්‍යයෙන් පොත් 6ක් පෙන්වීම සඳහා
if (empty($trending_products) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt_fb = $pdo->query("SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft') ORDER BY p.id DESC LIMIT 6");
        if ($stmt_fb) {
            $trending_products = $stmt_fb->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {}
}



// 1. Fetch Dynamic Categories with Book Counts (Only using categories & product_categories)
$featured_categories = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql_cats = "SELECT c.*, 
                     (
                         SELECT COUNT(DISTINCT pc.product_id) 
                         FROM product_categories pc 
                         JOIN products p ON pc.product_id = p.id
                         WHERE (pc.category_id = c.id OR pc.category_id IN (SELECT sub.id FROM categories sub WHERE sub.parent_id = c.id))
                         AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                     ) as book_count 
                     FROM categories c 
                     WHERE c.slug NOT IN ('vinura-ransika', 'vinura_ransika') 
                     ORDER BY book_count DESC, c.id ASC 
                     LIMIT 12";
        $stmt_cats = $pdo->query($sql_cats);
        if ($stmt_cats) {
            $featured_categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    try {
        $sql_cats = "SELECT c.*, COUNT(pc.product_id) as book_count 
                     FROM categories c 
                     LEFT JOIN product_categories pc ON c.id = pc.category_id 
                     WHERE c.slug NOT IN ('vinura-ransika', 'vinura_ransika') 
                     GROUP BY c.id 
                     ORDER BY c.id ASC 
                     LIMIT 12";
        $stmt_cats = $pdo->query($sql_cats);
        if ($stmt_cats) {
            $featured_categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e2) {
        $featured_categories = [];
    }
}

// 2. Fetch Latest 12 Books for Featured Section (With Exact Category Name & Slug)
$featured_products = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql_prods = "SELECT p.*, b.name as brand_name,
                             (SELECT c.name FROM categories c 
                              JOIN product_categories pc ON c.id = pc.category_id 
                              WHERE pc.product_id = p.id LIMIT 1) as category_name,
                             (SELECT c.slug FROM categories c 
                              JOIN product_categories pc ON c.id = pc.category_id 
                              WHERE pc.product_id = p.id LIMIT 1) as category_slug
                      FROM products p 
                      LEFT JOIN brands b ON p.brand_id = b.id 
                      WHERE (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                      ORDER BY p.id DESC 
                      LIMIT 12";
        $stmt_prods = $pdo->query($sql_prods);
        if ($stmt_prods) {
            $featured_products = $stmt_prods->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $featured_products = [];
}

// 3. Fetch Category-wise Books (Matches Category & its Sub-categories without non-existent columns)
$category_books = [];
if (!empty($featured_categories) && isset($pdo) && $pdo instanceof PDO) {
    foreach ($featured_categories as $cat) {
        $cat_id = (int)$cat['id'];
        try {
            $stmt_cb = $pdo->prepare("SELECT DISTINCT p.*, b.name as brand_name 
                                      FROM products p 
                                      JOIN product_categories pc ON p.id = pc.product_id 
                                      LEFT JOIN brands b ON p.brand_id = b.id 
                                      WHERE (pc.category_id = ? OR pc.category_id IN (SELECT sub.id FROM categories sub WHERE sub.parent_id = ?))
                                      AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                                      ORDER BY p.id DESC 
                                      LIMIT 12");
            $stmt_cb->execute([$cat_id, $cat_id]);
            $books = $stmt_cb->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($books)) {
                // Attach Category info to each book so cards display correctly
                foreach ($books as &$bk) {
                    $bk['category_name'] = $cat['name'];
                    $bk['category_slug'] = $cat['slug'];
                }
                unset($bk);

                $category_books[$cat_id] = [
                    'category' => $cat,
                    'books'    => $books
                ];
            }
        } catch (Throwable $e) {
            // Fallback to simple direct category match
            try {
                $stmt_cb = $pdo->prepare("SELECT p.*, b.name as brand_name 
                                          FROM products p 
                                          JOIN product_categories pc ON p.id = pc.product_id 
                                          LEFT JOIN brands b ON p.brand_id = b.id 
                                          WHERE pc.category_id = ? 
                                          AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1') OR p.status != 'draft')
                                          ORDER BY p.id DESC 
                                          LIMIT 12");
                $stmt_cb->execute([$cat_id]);
                $books = $stmt_cb->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($books)) {
                    foreach ($books as &$bk) {
                        $bk['category_name'] = $cat['name'];
                        $bk['category_slug'] = $cat['slug'];
                    }
                    unset($bk);

                    $category_books[$cat_id] = [
                        'category' => $cat,
                        'books'    => $books
                    ];
                }
            } catch (Throwable $e2) {}
        }
    }
}

// 4. Fetch Top 12 Publishers (Brands) with Book Counts
$featured_publishers = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql_pubs = "SELECT b.id, b.name, b.slug, b.image, 
                     COUNT(p.id) as book_count 
                     FROM brands b 
                     LEFT JOIN products p ON b.id = p.brand_id 
                          AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                     GROUP BY b.id 
                     ORDER BY book_count DESC, b.name ASC 
                     LIMIT 12";
        $stmt_pubs = $pdo->query($sql_pubs);
        if ($stmt_pubs) {
            $featured_publishers = $stmt_pubs->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $featured_publishers = [];
}

// 5. Fetch Top 12 Authors from 'attributes' and 'attribute_terms'
$featured_authors = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql_authors = "SELECT t.id, t.name, t.slug,
                        (
                            SELECT COUNT(*) FROM product_attributes pa 
                            JOIN products p ON pa.product_id = p.id
                            WHERE pa.attribute_id = t.attribute_id 
                            AND (pa.terms_json LIKE CONCAT('%\"', t.name, '\"%') OR pa.terms_json LIKE CONCAT('%', t.name, '%') OR pa.terms_json LIKE CONCAT('%', t.slug, '%'))
                            AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                        ) as book_count
                        FROM attribute_terms t
                        JOIN attributes a ON t.attribute_id = a.id
                        WHERE a.slug = 'author' OR LOWER(a.name) = 'author'
                        ORDER BY book_count DESC, t.name ASC
                        LIMIT 12";
        $stmt_authors = $pdo->query($sql_authors);
        if ($stmt_authors) {
            $featured_authors = $stmt_authors->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt_authors = $pdo->query("SELECT t.id, t.name, t.slug, 0 as book_count FROM attribute_terms t JOIN attributes a ON t.attribute_id = a.id WHERE a.slug = 'author' OR LOWER(a.name) = 'author' ORDER BY t.name ASC LIMIT 12");
            if ($stmt_authors) {
                $featured_authors = $stmt_authors->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $e2) {
        $featured_authors = [];
    }
}

// 6. Fetch Dynamic Blog Posts
$latest_posts = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql_blog = "SELECT p.*, u.username as author_name 
                     FROM blog_posts p 
                     LEFT JOIN users u ON p.author_id = u.id 
                     WHERE (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1') OR p.status != 'draft')
                     ORDER BY p.id DESC 
                     LIMIT 3";
        $stmt_blog = $pdo->query($sql_blog);
        if ($stmt_blog) {
            $latest_posts = $stmt_blog->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $latest_posts = [];
}
?>

<!-- Google Fonts & Swiper.js Bundle -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    .home-wrapper, .home-wrapper * {
        font-family: 'Poppins', sans-serif !important;
    }

    .hero-swiper {
        width: 100%;
        position: relative;
        overflow: hidden;
    }
    .hero-swiper .swiper-wrapper {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        width: 100%;
        height: 100%;
    }
    .hero-swiper .swiper-slide {
        flex-shrink: 0 !important;
        width: 100% !important;
        height: 100%;
    }

    .hero-swiper .swiper-pagination-bullet {
        background: #ffffff;
        width: 9px;
        height: 9px;
        opacity: 0.6;
        border-radius: 9999px !important;
        transition: all 0.3s ease;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
    }
    .hero-swiper .swiper-pagination-bullet-active {
        background: #0369a1 !important;
        width: 28px !important;
        opacity: 1 !important;
    }
</style>

<!-- Floating Toast Container -->
<div id="toast-container" class="fixed top-24 right-5 z-[100] transform translate-x-full transition-all duration-300 opacity-0 hidden">
    <div class="bg-white border-l-4 border-sky-700 shadow-2xl p-4 flex items-center gap-3.5 min-w-[300px] max-w-sm rounded-2xl border border-slate-200">
        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center shrink-0 border border-sky-200">
            <svg class="w-5 h-5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Added to Basket!</h4>
            <p id="toast-msg" class="text-xs text-slate-500 font-medium truncate mt-0.5">Book added to your shopping cart.</p>
        </div>
    </div>
</div>

<div class="home-wrapper w-full p-0 m-0 bg-[#f8fafc] text-slate-800">

    <!-- ==================== 1. HERO BANNER ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 pt-4 pb-2">
        <div class="w-full overflow-hidden rounded-2xl sm:rounded-3xl border border-slate-200/80 shadow-sm bg-slate-100">
            <div class="swiper hero-swiper w-full aspect-[2300/960]">
                <div class="swiper-wrapper">
                    <div class="swiper-slide relative w-full h-full">
                        <a href="/shop" class="block w-full h-full">
                            <img src="uploads/web/slide2.webp" alt="ReadersHub Banner 1" class="w-full h-full object-cover object-center" loading="eager">
                        </a>
                    </div>
                    <div class="swiper-slide relative w-full h-full">
                        <a href="/shop?categories[]=academic" class="block w-full h-full">
                            <img src="uploads/web/slide1.webp" alt="ReadersHub Banner 2" class="w-full h-full object-cover object-center" loading="lazy">
                        </a>
                    </div>
                </div>
                <div class="swiper-pagination !bottom-3 sm:!bottom-5 z-20"></div>
                <div class="swiper-button-prev !hidden sm:!flex !w-10 !h-10 !rounded-full !bg-white/90 !text-slate-800 hover:!bg-sky-700 hover:!text-white border border-slate-200 shadow-md after:!text-xs transition-colors !left-4 sm:!left-6 z-20"></div>
                <div class="swiper-button-next !hidden sm:!flex !w-10 !h-10 !rounded-full !bg-white/90 !text-slate-800 hover:!bg-sky-700 hover:!text-white border border-slate-200 shadow-md after:!text-xs transition-colors !right-4 sm:!right-6 z-20"></div>
            </div>
        </div>
    </section>

    <!-- ==================== 2. TRUST RIBBON ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-6">
        <div class="bg-white p-5 sm:p-7 lg:p-8 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-sm">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8">
                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3.5 sm:gap-4 p-2 sm:p-0 group">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-sky-50 text-sky-700 flex items-center justify-center shrink-0 border border-sky-100 group-hover:bg-sky-700 group-hover:text-white transition-all duration-300 shadow-xs">
                        <svg class="w-7 h-7 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 17a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM5 12h14l1 3H4l1-3z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm sm:text-base lg:text-lg font-black text-slate-900 leading-snug tracking-tight">Islandwide Delivery</h4>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Quick delivery to your doorstep</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3.5 sm:gap-4 p-2 sm:p-0 group">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center shrink-0 border border-indigo-100 group-hover:bg-indigo-700 group-hover:text-white transition-all duration-300 shadow-xs">
                        <svg class="w-7 h-7 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm sm:text-base lg:text-lg font-black text-slate-900 leading-snug tracking-tight">Secure Payment</h4>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Cards, Bank Transfer & COD</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3.5 sm:gap-4 p-2 sm:p-0 group">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-100 group-hover:bg-amber-600 group-hover:text-white transition-all duration-300 shadow-xs">
                        <svg class="w-7 h-7 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-3.138-3.138z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm sm:text-base lg:text-lg font-black text-slate-900 leading-snug tracking-tight">100% Genuine Books</h4>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Direct publishers guarantee</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3.5 sm:gap-4 p-2 sm:p-0 group">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-100 group-hover:bg-emerald-700 group-hover:text-white transition-all duration-300 shadow-xs">
                        <svg class="w-7 h-7 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm sm:text-base lg:text-lg font-black text-slate-900 leading-snug tracking-tight">Cash on Delivery</h4>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Pay upon parcel arrival</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    
    
    
<!-- ==================== SPECIAL TRENDING / BESTSELLERS SECTION ==================== -->
    <?php if (!empty($trending_products)): ?>
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-8 my-4">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-500/10 via-sky-500/5 to-white border-2 border-amber-400/40 p-5 sm:p-7 lg:p-8 shadow-sm">
            
            <!-- Background Decorative Glow -->
            <div class="absolute -top-16 -right-16 w-56 h-56 bg-amber-400/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-16 -left-16 w-56 h-56 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Header Bar -->
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 border-b border-amber-200/60 pb-4">
                <div>
                    <!-- Special Pill Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-sm shadow-amber-500/30 mb-2">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                        </span>
                        <svg class="w-3.5 h-3.5 fill-current animate-pulse" viewBox="0 0 24 24">
                            <path d="M17.556 7.847c-.503-.984-1.196-1.854-2.043-2.569-.379-.319-.895-.429-1.373-.292-.477.137-.847.493-.984.97-.246.858-.694 1.637-1.306 2.274-.755.786-1.748 1.304-2.825 1.474-.473.075-.867.388-1.047.831s-.11.954.186 1.332c.983 1.257 1.558 2.809 1.633 4.417.067 1.442-.423 2.859-1.378 3.985-.308.363-.377.871-.177 1.3.199.429.626.706 1.104.717 3.327.08 6.444-1.464 8.358-4.131 1.913-2.668 2.247-6.13.892-9.108zm-5.556 12.153c-.366 0-.726-.046-1.077-.134.629-.974.912-2.13.805-3.308-.077-1.173-.497-2.3-1.213-3.256.467-.282.969-.504 1.498-.662.593.893 1.393 1.625 2.33 2.131-.476 2.92-2.124 4.965-2.343 5.229z"/>
                        </svg>
                        <span class="text-[11px] font-black uppercase tracking-wider">Top 6 Bestsellers</span>
                    </div>

                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        Trending Books
                        <span class="text-xs font-bold text-amber-600 bg-amber-100 border border-amber-200 px-2 py-0.5 rounded-lg">Verified Orders</span>
                    </h2>
                    <p class="text-slate-500 text-xs sm:text-sm mt-0.5 font-medium">Sri Lanka's most demanded books calculated directly from customer orders</p>
                </div>

                <a href="/shop?sort=price_high" class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-800 bg-white hover:bg-sky-50 border border-slate-200 px-4 py-2 rounded-xl transition shadow-xs group self-start sm:self-auto shrink-0">
                    <span>Explore All Titles</span>
                    <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                </a>
            </div>

            <!-- Products Grid with Rank Badges -->
            <div class="relative z-10 grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 xl:grid-cols-6 gap-3.5 sm:gap-4">
                <?php 
                $rank = 1;
                foreach ($trending_products as $p): 
                    // Rank Badge Colors: #1 Gold, #2 Silver, #3 Bronze, others Dark Slate
                    $rankClass = match($rank) {
                        1 => 'bg-gradient-to-r from-amber-500 to-yellow-400 text-slate-950 shadow-amber-500/40',
                        2 => 'bg-gradient-to-r from-slate-300 to-slate-400 text-slate-900 shadow-slate-400/40',
                        3 => 'bg-gradient-to-r from-amber-700 to-amber-600 text-white shadow-amber-700/40',
                        default => 'bg-slate-900 text-white shadow-slate-900/30'
                    };
                ?>
                    <div class="relative group/rank h-full flex flex-col">
                        <!-- Floating Trend Rank Tag -->
                        <div class="absolute -top-2.5 -left-2 z-30 flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider shadow-md <?= $rankClass; ?>">
                            <span>#<?= $rank; ?></span>
                            <?php if ($rank === 1): ?>
                                <span>★</span>
                            <?php endif; ?>
                        </div>

                        <?php include __DIR__ . '/products/cards/product_card_1.php'; ?>
                    </div>
                <?php 
                    $rank++;
                endforeach; 
                ?>
            </div>

        </div>
    </section>
    <?php endif; ?>
    
    
    

    <!-- ==================== 3. EXPLORE CATEGORIES ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-8">
        <div class="flex justify-between items-end mb-6 border-b border-slate-200 pb-3.5">
            <div>
                <span class="text-[10px] font-bold text-sky-700 uppercase tracking-widest">Discover Genres</span>
                <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tight mt-0.5">Explore Categories</h2>
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5 font-medium">Browse our wide range of reading genres</p>
            </div>
            <a href="/shop" class="text-xs sm:text-sm font-bold text-sky-700 hover:text-sky-800 uppercase tracking-wider flex items-center gap-1 group">
                <span>View All Categories</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3.5 sm:gap-4">
            <?php if (!empty($featured_categories)): ?>
                <?php foreach (array_slice($featured_categories, 0, 12) as $cat): 
                    $cat_slug = htmlspecialchars($cat['slug'] ?? 'all');
                    $cat_name = htmlspecialchars($cat['name'] ?? 'Category');
                    $book_count = (int)($cat['book_count'] ?? 0);

                    $cat_img = null;
                    if (!empty($cat['image'])) {
                        $cat_img = (str_starts_with($cat['image'], 'http://') || str_starts_with($cat['image'], 'https://'))
                            ? $cat['image']
                            : '/' . ltrim(htmlspecialchars($cat['image']), '/');
                    }
                ?>
                    <a href="/shop?categories[]=<?= $cat_slug ?>" class="group bg-white rounded-2xl border border-slate-200/90 hover:border-sky-700 hover:shadow-xl hover:shadow-sky-700/10 transition-all duration-300 flex flex-col overflow-hidden text-center">
                        <div class="relative w-full aspect-square bg-slate-100 overflow-hidden border-b border-slate-100 rounded-t-2xl">
                            <?php if ($cat_img): ?>
                                <img src="<?= $cat_img; ?>" alt="<?= $cat_name; ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out" loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-slate-50 text-sky-700/80">
                                    <svg class="w-10 h-10 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-3.5 sm:p-4 flex flex-col items-center flex-1 justify-between bg-white rounded-b-2xl">
                            <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 group-hover:text-sky-700 transition-colors truncate max-w-full leading-snug">
                                <?= $cat_name; ?>
                            </h3>
                            <div class="mt-2">
                                <span class="inline-flex items-center text-[10px] font-bold bg-sky-50 text-sky-800 px-2.5 py-0.5 rounded-md border border-sky-100/80">
                                    <?= number_format($book_count); ?> <?= $book_count === 1 ? 'Book' : 'Books'; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-12 bg-white rounded-2xl border border-slate-200 text-slate-400 text-xs">
                    No categories found in the catalog.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ==================== 4. MAIN STORE SHOWCASE ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-8">
        <div class="flex justify-between items-end mb-5 border-b border-slate-200 pb-3">
            <div>
                <span class="text-[10px] font-bold text-sky-700 uppercase tracking-widest">Store Showcase</span>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 uppercase tracking-wider mt-0.5">Latest Books & Bestsellers</h2>
            </div>
            <a href="/shop" class="text-xs font-bold text-sky-700 hover:text-sky-800 uppercase tracking-wider">
                Explore All Books (<?= count($featured_products); ?>) &rarr;
            </a>
        </div>

        <?php if (!empty($featured_products)): ?>
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3 sm:gap-4">
                <?php foreach ($featured_products as $p): ?>
                    <?php include __DIR__ . '/products/cards/product_card_1.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 text-slate-400 text-xs">
                No products found in the catalog.
            </div>
        <?php endif; ?>
    </section>

    <!-- ==================== 5. CATEGORY-WISE BOOK SECTIONS ==================== -->
    <?php if (!empty($category_books)): ?>
        <?php foreach ($category_books as $cData): 
            $cat = $cData['category'];
            $books = $cData['books'];
            $cat_slug = htmlspecialchars($cat['slug']);
            $cat_name = htmlspecialchars($cat['name']);
        ?>
            <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-6">
                <div class="flex justify-between items-end mb-4 border-b border-slate-200 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-sky-700 uppercase tracking-widest">Genre Highlights</span>
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 uppercase tracking-wider mt-0.5"><?= $cat_name; ?></h2>
                    </div>
                    <a href="/shop?categories[]=<?= $cat_slug; ?>" class="text-xs font-bold text-sky-700 hover:text-sky-800 uppercase tracking-wider">
                        View All <?= $cat_name; ?> &rarr;
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3 sm:gap-4">
                    <?php foreach ($books as $p): ?>
                        <?php include __DIR__ . '/products/cards/product_card_1.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

<!-- ==================== 6. TOP PUBLISHERS SECTION ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-8">
        <div class="flex justify-between items-end mb-5 border-b border-slate-200 pb-3.5">
            <div>
                <span class="text-[10px] font-bold text-sky-700 uppercase tracking-widest">Authorized Imprints</span>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight mt-0.5">Top Publishers</h2>
                <p class="text-slate-500 text-xs mt-0.5 font-medium">Browse authentic books from Sri Lanka's leading publication houses</p>
            </div>
            <a href="/publishers" class="text-xs font-bold text-sky-700 hover:text-sky-800 uppercase tracking-wider flex items-center gap-1 group">
                <span>View All Publishers</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3.5 sm:gap-4">
            <?php if (!empty($featured_publishers)): ?>
                <?php foreach ($featured_publishers as $pub): 
                    $pub_id = (int)$pub['id'];
                    $pub_name = htmlspecialchars($pub['name'] ?? 'Publisher');
                    $book_count = (int)($pub['book_count'] ?? 0);
                    
                    $pub_img = null;
                    if (!empty($pub['image'])) {
                        $pub_img = (str_starts_with($pub['image'], 'http://') || str_starts_with($pub['image'], 'https://'))
                            ? $pub['image']
                            : '/' . ltrim(htmlspecialchars($pub['image']), '/');
                    }
                ?>
                    <a href="/shop?brands[]=<?= $pub_id ?>" class="group bg-white rounded-2xl border border-slate-200/90 hover:border-sky-700 hover:shadow-xl hover:shadow-sky-700/10 transition-all duration-300 flex flex-col justify-center items-center text-center p-4 sm:p-5">
                        
                        <?php /* ==================== IMAGE CONTAINER (COMMENTED OUT) ====================
                        <div class="relative w-full aspect-square bg-slate-100 overflow-hidden border-b border-slate-100 rounded-t-2xl mb-3">
                            if ($pub_img) {
                                echo '<img src="' . $pub_img . '" alt="' . $pub_name . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out" loading="lazy">';
                            } else {
                                echo '<div class="w-full h-full flex items-center justify-center bg-slate-50 text-sky-700/80">
                                    <svg class="w-10 h-10 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>';
                            }
                        </div>
                        ============================================================================= */ ?>

                        <div class="flex flex-col items-center justify-center w-full">
                            <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 group-hover:text-sky-700 transition-colors truncate max-w-full leading-snug">
                                <?= $pub_name; ?>
                            </h3>
                            <div class="mt-2">
                                <span class="inline-flex items-center text-[10px] font-bold bg-sky-50 text-sky-800 px-2.5 py-0.5 rounded-md border border-sky-100/80">
                                    <?= number_format($book_count); ?> <?= $book_count === 1 ? 'Title' : 'Titles'; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-10 bg-white rounded-2xl border border-slate-200 text-slate-400 text-xs">
                    No publishers found in the catalog.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ==================== 7. FEATURED AUTHORS SECTION ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-8">
        <div class="flex justify-between items-end mb-5 border-b border-slate-200 pb-3.5">
            <div>
                <span class="text-[10px] font-bold text-sky-700 uppercase tracking-widest">Literary Creators</span>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight mt-0.5">Featured Authors</h2>
                <p class="text-slate-500 text-xs mt-0.5 font-medium">Explore literature and celebrated works by your favorite authors</p>
            </div>
            <a href="/authors" class="text-xs font-bold text-sky-700 hover:text-sky-800 uppercase tracking-wider flex items-center gap-1 group">
                <span>View All Authors</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3.5 sm:gap-4">
            <?php if (!empty($featured_authors)): ?>
                <?php foreach ($featured_authors as $author): 
                    $author_name = htmlspecialchars($author['name'] ?? 'Author');
                    $author_slug = htmlspecialchars($author['slug'] ?? '');
                    $book_count = (int)($author['book_count'] ?? 0);
                    $first_letter = strtoupper(mb_substr(trim($author['name']), 0, 1));
                ?>
                    <a href="/shop?authors[]=<?= urlencode($author['name']); ?>" class="group bg-white p-5 rounded-2xl border border-slate-200/90 hover:border-sky-700 hover:shadow-xl hover:shadow-sky-700/10 transition-all duration-300 flex flex-col items-center text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-gradient-to-tr from-sky-100 via-sky-50 to-white text-sky-700 flex items-center justify-center border-2 border-sky-200 group-hover:border-sky-700 group-hover:scale-105 transition-all duration-300 shadow-xs mb-3.5 relative overflow-hidden">
                            <span class="text-lg sm:text-xl font-black uppercase text-sky-800 select-none">
                                <?= $first_letter ?: 'A'; ?>
                            </span>
                            <div class="absolute bottom-0 right-0 w-5 h-5 bg-sky-700 text-white rounded-full flex items-center justify-center shadow-xs">
                                <svg class="w-3 h-3 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                            </div>
                        </div>

                        <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 group-hover:text-sky-700 transition-colors truncate max-w-full leading-snug">
                            <?= $author_name; ?>
                        </h3>

                        <div class="mt-1.5">
                            <span class="inline-flex items-center text-[10px] font-bold bg-sky-50 text-sky-800 px-2.5 py-0.5 rounded-md border border-sky-100/80">
                                <?= number_format($book_count); ?> <?= $book_count === 1 ? 'Book' : 'Books'; ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-10 bg-white rounded-2xl border border-slate-200 text-slate-400 text-xs">
                    No authors found in the catalog.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ==================== 8. LATEST BLOG POSTS ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-10 pb-16">
        <div class="flex justify-between items-end mb-4 border-b border-slate-200 pb-3">
            <div>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 uppercase tracking-wider">Literary Articles</h2>
                <p class="text-slate-500 text-xs mt-0.5">Reviews, reading guides, and literary discussions</p>
            </div>
            <a href="/blog" class="text-xs font-bold text-sky-700 hover:text-sky-800 uppercase tracking-wider">
                All Articles &rarr;
            </a>
        </div>

        <?php if (!empty($latest_posts)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                <?php foreach ($latest_posts as $post): ?>
                    <?php require 'pages/blog/card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<!-- Swiper Initialization -->
<script>
    (function initSwiperSlider() {
        let swiperInstance = null;
        let attempts = 0;

        function runSwiper() {
            if (swiperInstance) return;
            if (typeof Swiper !== 'undefined') {
                swiperInstance = new Swiper('.hero-swiper', {
                    slidesPerView: 1,
                    spaceBetween: 0,
                    loop: true,
                    speed: 700,
                    effect: 'fade',
                    fadeEffect: { crossFade: true },
                    autoplay: {
                        delay: 4500,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true
                    },
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                });
            } else if (attempts < 25) {
                attempts++;
                setTimeout(runSwiper, 150);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener("DOMContentLoaded", runSwiper);
        } else {
            runSwiper();
        }
        window.addEventListener('load', runSwiper);
    })();
</script>