<?php
// Include DB connection fallback
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    } elseif (file_exists('config/db.php')) {
        require_once 'config/db.php';
    }
}


// Fetch the top six trending products by order count
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

// Show the latest products when there are no recorded orders yet.
if (empty($trending_products) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt_fb = $pdo->query("SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft') ORDER BY p.id DESC LIMIT 6");
        if ($stmt_fb) {
            $trending_products = $stmt_fb->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {}
}



// 1. Fetch dynamic product categories.
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
                     ) as product_count
                     FROM categories c 
                     WHERE c.slug NOT IN ('vinura-ransika', 'vinura_ransika') 
                     ORDER BY product_count DESC, c.id ASC
                     LIMIT 12";
        $stmt_cats = $pdo->query($sql_cats);
        if ($stmt_cats) {
            $featured_categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    try {
        $sql_cats = "SELECT c.*, COUNT(pc.product_id) as product_count
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

// 2. Fetch the latest products for the featured section.
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

// 3. Fetch products by category, including child categories.
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

// 4. Fetch top brands by product count.
$featured_publishers = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql_pubs = "SELECT b.id, b.name, b.slug, b.image, 
                     COUNT(p.id) as product_count
                     FROM brands b 
                     LEFT JOIN products p ON b.id = p.brand_id 
                          AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                     GROUP BY b.id 
                     ORDER BY product_count DESC, b.name ASC
                     LIMIT 12";
        $stmt_pubs = $pdo->query($sql_pubs);
        if ($stmt_pubs) {
            $featured_publishers = $stmt_pubs->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $featured_publishers = [];
}

// 5. Fetch dynamic editorial posts.
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

<style>
    .home-wrapper, .home-wrapper * {
        font-family: Arial, Helvetica, sans-serif !important;
    }
</style>

<!-- Floating Toast Container -->
<div id="toast-container" class="fixed top-24 right-5 z-[100] transform translate-x-full transition-all duration-300 opacity-0 hidden">
    <div class="bg-white border-l-4 border-neutral-900 shadow-2xl p-4 flex items-center gap-3.5 min-w-[300px] max-w-sm rounded-2xl border border-neutral-200">
        <div class="w-10 h-10 rounded-xl bg-neutral-100 text-neutral-900 flex items-center justify-center shrink-0 border border-neutral-200">
            <svg class="w-5 h-5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Added to Basket!</h4>
            <p id="toast-msg" class="text-xs text-slate-500 font-medium truncate mt-0.5">Item added to your shopping bag.</p>
        </div>
    </div>
</div>

<div class="home-wrapper w-full p-0 m-0 bg-[#f5f4f1] text-neutral-900">

    <!-- ==================== 1. HERO BANNER ==================== -->
    <?php $hero_video_clips = ['/uploads/web/5.mp4', '/uploads/web/2.mp4', '/uploads/web/4.mp4']; ?>
    <style>
        #home-hero,
        #home-hero * {
            font-family: Arial, Helvetica, sans-serif !important;
        }
    </style>
    <section id="home-hero" class="relative isolate flex min-h-[calc(100svh-140px)] w-full items-center overflow-hidden bg-neutral-950 text-white sm:min-h-[680px] lg:min-h-[600px] lg:max-h-[900px]">
        <video
            id="home-hero-video"
            class="absolute inset-0 h-full w-full object-cover"
            src="<?= htmlspecialchars($hero_video_clips[0], ENT_QUOTES, 'UTF-8'); ?>"
            autoplay
            muted
            playsinline
            preload="auto"
            aria-hidden="true"
        ></video>
        <div class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/40 to-black/10"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-black/10"></div>

        <div class="absolute bottom-5 left-6 right-6 z-10 flex flex-col items-start gap-4 sm:bottom-7 sm:left-10 sm:right-10 sm:flex-row sm:items-end sm:justify-between lg:left-12 lg:right-12">
            <p class="w-full text-[clamp(2.25rem,5vw,6rem)] font-black italic uppercase leading-[0.9] tracking-tight text-white drop-shadow-md sm:max-w-[78%]">
                Own your everyday
            </p>
            <button id="hero-video-toggle" type="button" class="rounded-full border border-white/30 bg-black/25 px-3.5 py-2 text-[9px] font-bold uppercase tracking-[0.14em] text-white backdrop-blur-sm transition hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="Pause background video">
                Pause video
            </button>
        </div>
    </section>

    <script>
        (function () {
            const video = document.getElementById('home-hero-video');
            const toggle = document.getElementById('hero-video-toggle');
            const clips = <?= json_encode($hero_video_clips, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            if (!video || !toggle || clips.length < 1) return;

            let currentClip = 0;
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function sizeHeroForViewport() {
                const hero = document.getElementById('home-hero');
                const navbar = document.getElementById('main-navbar');
                if (!hero) return;
                if (navbar) {
                    navbar.style.marginBottom = '-' + navbar.offsetHeight + 'px';
                }
                if (window.matchMedia('(min-width: 1024px)').matches) {
                    const heroTop = hero.getBoundingClientRect().top + window.scrollY;
                    const availableHeight = window.innerHeight - Math.max(0, heroTop);
                    hero.style.height = Math.min(900, Math.max(600, availableHeight)) + 'px';
                } else {
                    hero.style.height = '';
                }
            }

            function updateToggleLabel() {
                const isPlaying = !video.paused;
                toggle.textContent = isPlaying ? 'Pause video' : 'Play video';
                toggle.setAttribute('aria-label', isPlaying ? 'Pause background video' : 'Play background video');
            }

            video.addEventListener('ended', function () {
                currentClip = (currentClip + 1) % clips.length;
                video.src = clips[currentClip];
                video.load();
                video.play().catch(function (error) {
                    console.warn('Hero video could not continue playing:', error);
                    updateToggleLabel();
                });
            });
            video.addEventListener('play', updateToggleLabel);
            video.addEventListener('pause', updateToggleLabel);
            video.addEventListener('error', function () {
                console.error('Unable to load homepage hero video:', video.currentSrc);
                toggle.textContent = 'Video unavailable';
                toggle.disabled = true;
            });

            if (reducedMotion) {
                video.pause();
            }

            toggle.addEventListener('click', function () {
                if (video.paused) {
                    video.play().catch(function (error) {
                        console.error('Unable to play homepage hero video:', error);
                    });
                } else {
                    video.pause();
                }
            });

            sizeHeroForViewport();
            window.addEventListener('resize', sizeHeroForViewport, { passive: true });
            updateToggleLabel();
        })();
    </script>

    <!-- ==================== 2. TRUST RIBBON ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-8">
        <div class="bg-white p-5 sm:p-7 lg:p-8 rounded-2xl border border-neutral-200 shadow-sm">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8">
                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3.5 sm:gap-4 p-2 sm:p-0 group">
                    <div class="w-12 h-12 rounded-full bg-neutral-100 text-neutral-900 flex items-center justify-center shrink-0 border border-neutral-200 group-hover:bg-neutral-900 group-hover:text-white transition-all duration-300">
                        <svg class="w-7 h-7 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 17a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM5 12h14l1 3H4l1-3z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm sm:text-base lg:text-lg font-black text-slate-900 leading-snug tracking-tight">Islandwide Delivery</h4>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Quick delivery to your doorstep</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3.5 sm:gap-4 p-2 sm:p-0 group">
                    <div class="w-12 h-12 rounded-full bg-neutral-100 text-neutral-900 flex items-center justify-center shrink-0 border border-neutral-200 group-hover:bg-neutral-900 group-hover:text-white transition-all duration-300">
                        <svg class="w-7 h-7 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm sm:text-base lg:text-lg font-black text-slate-900 leading-snug tracking-tight">Secure Payment</h4>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Cards, Bank Transfer & COD</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3.5 sm:gap-4 p-2 sm:p-0 group">
                    <div class="w-12 h-12 rounded-full bg-neutral-100 text-neutral-900 flex items-center justify-center shrink-0 border border-neutral-200 group-hover:bg-neutral-900 group-hover:text-white transition-all duration-300">
                        <svg class="w-7 h-7 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-3.138-3.138z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm sm:text-base font-bold text-neutral-900 leading-snug tracking-tight">Quality you can trust</h4>
                        <p class="text-xs sm:text-sm text-neutral-500 font-medium mt-1">Made for lasting everyday wear</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3.5 sm:gap-4 p-2 sm:p-0 group">
                    <div class="w-12 h-12 rounded-full bg-neutral-100 text-neutral-900 flex items-center justify-center shrink-0 border border-neutral-200 group-hover:bg-neutral-900 group-hover:text-white transition-all duration-300">
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
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-8">
        <div class="relative overflow-hidden rounded-2xl bg-neutral-950 p-5 text-white sm:p-7 lg:p-8">
            
            <!-- Background Decorative Glow -->
            <div class="absolute -top-16 -right-16 w-56 h-56 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-16 -left-16 w-56 h-56 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Header Bar -->
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 border-b border-white/15 pb-4">
                <div>
                    <!-- Special Pill Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-white border border-white/15 mb-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                        <span class="text-[10px] font-bold uppercase tracking-[0.16em]">Bestsellers</span>
                    </div>

                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white tracking-tight flex items-center gap-2">
                        Most wanted
                        <span class="text-xs font-semibold text-white/70 bg-white/10 border border-white/15 px-2 py-0.5 rounded-full">Customer favourites</span>
                    </h2>
                    <p class="text-white/65 text-xs sm:text-sm mt-1">The styles customers are choosing this season.</p>
                </div>

                <a href="/shop" class="inline-flex items-center gap-1.5 text-xs font-bold text-neutral-950 bg-white hover:bg-neutral-200 border border-white px-4 py-2 rounded-full transition group self-start sm:self-auto shrink-0">
                    <span>Shop bestsellers</span>
                    <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                </a>
            </div>

            <!-- Products Grid with Rank Badges -->
            <div class="relative z-10 grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3.5 sm:gap-4">
                <?php 
                $rank = 1;
                foreach ($trending_products as $p): 
                    $rankClass = match($rank) {
                        1 => 'bg-white text-neutral-950',
                        default => 'bg-neutral-900 text-white border border-white/20'
                    };
                ?>
                    <div class="relative group/rank h-full flex flex-col">
                        <!-- Floating Trend Rank Tag -->
                        <div class="absolute -top-2.5 -left-2 z-30 flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-md <?= $rankClass; ?>">
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
        <div class="flex justify-between items-end mb-6 border-b border-neutral-200 pb-4">
            <div>
                <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-[0.2em]">Find your fit</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-neutral-950 tracking-tight mt-1">Shop by category</h2>
                <p class="text-neutral-500 text-sm mt-1">Build your wardrobe with everyday essentials.</p>
            </div>
            <a href="/shop" class="text-xs sm:text-sm font-bold text-neutral-900 hover:text-neutral-500 uppercase tracking-wider flex items-center gap-1 group">
                <span>Shop all</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3.5 sm:gap-4">
            <?php if (!empty($featured_categories)): ?>
                <?php foreach (array_slice($featured_categories, 0, 12) as $cat): 
                    $cat_slug = htmlspecialchars($cat['slug'] ?? 'all');
                    $cat_name = htmlspecialchars($cat['name'] ?? 'Category');
                    $product_count = (int)($cat['product_count'] ?? 0);

                    $cat_img = null;
                    if (!empty($cat['image'])) {
                        $cat_img = (str_starts_with($cat['image'], 'http://') || str_starts_with($cat['image'], 'https://'))
                            ? $cat['image']
                            : '/' . ltrim(htmlspecialchars($cat['image']), '/');
                    }
                ?>
                    <a href="/shop?categories[]=<?= $cat_slug ?>" class="group relative aspect-[4/5] overflow-hidden rounded-xl bg-neutral-200 text-left">
                        <div class="absolute inset-0">
                            <?php if ($cat_img): ?>
                                <img src="<?= $cat_img; ?>" alt="<?= $cat_name; ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-neutral-100 text-neutral-500">
                                    <svg class="w-10 h-10 stroke-[1.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 3l5 4-3 4-3-2v12H9V9L6 11 3 7l5-4 2 2h4l2-2z"/></svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/5 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-4 text-white sm:p-5">
                            <h3 class="text-base font-bold leading-tight sm:text-lg">
                                <?= $cat_name; ?>
                            </h3>
                            <div class="mt-2">
                                <span class="inline-flex items-center text-[10px] font-semibold text-white/85">
                                    <?= number_format($product_count); ?> <?= $product_count === 1 ? 'style' : 'styles'; ?>
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
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-10">
        <div class="flex justify-between items-end mb-6 border-b border-neutral-200 pb-4">
            <div>
                <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-[0.2em]">Just landed</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-neutral-950 tracking-tight mt-1">New arrivals</h2>
            </div>
            <a href="/shop" class="text-xs font-bold text-neutral-900 hover:text-neutral-500 uppercase tracking-wider">
                Shop all (<?= count($featured_products); ?>) &rarr;
            </a>
        </div>

        <?php if (!empty($featured_products)): ?>
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4 sm:gap-5">
                <?php foreach ($featured_products as $p): ?>
                    <?php include __DIR__ . '/products/cards/product_card_1.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-16 bg-white rounded-2xl border border-neutral-200 text-neutral-400 text-sm">
                No products found in the catalog.
            </div>
        <?php endif; ?>
    </section>

    <!-- ==================== 5. CATEGORY COLLECTIONS ==================== -->
    <?php if (!empty($category_books)): ?>
        <?php foreach ($category_books as $cData): 
            $cat = $cData['category'];
            $books = $cData['books'];
            $cat_slug = htmlspecialchars($cat['slug']);
            $cat_name = htmlspecialchars($cat['name']);
        ?>
            <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-8">
                <div class="flex justify-between items-end mb-5 border-b border-neutral-200 pb-4">
                    <div>
                        <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-[0.2em]">Explore the edit</span>
                        <h2 class="text-2xl sm:text-3xl font-bold text-neutral-950 tracking-tight mt-1"><?= $cat_name; ?></h2>
                    </div>
                    <a href="/shop?categories[]=<?= $cat_slug; ?>" class="text-xs font-bold text-neutral-900 hover:text-neutral-500 uppercase tracking-wider">
                        View All <?= $cat_name; ?> &rarr;
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4 sm:gap-5">
                    <?php foreach ($books as $p): ?>
                        <?php include __DIR__ . '/products/cards/product_card_1.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

<!-- ==================== 6. FEATURED BRANDS ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-10">
        <div class="flex justify-between items-end mb-6 border-b border-neutral-200 pb-4">
            <div>
                <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-[0.2em]">Labels we love</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-neutral-950 tracking-tight mt-1">Featured brands</h2>
                <p class="text-neutral-500 text-sm mt-1">Discover the labels behind your everyday essentials.</p>
            </div>
            <a href="/shop" class="text-xs font-bold text-neutral-900 hover:text-neutral-500 uppercase tracking-wider flex items-center gap-1 group">
                <span>Shop all brands</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3.5 sm:gap-4">
            <?php if (!empty($featured_publishers)): ?>
                <?php foreach ($featured_publishers as $pub): 
                    $pub_id = (int)$pub['id'];
                    $pub_name = htmlspecialchars($pub['name'] ?? 'Brand');
                    $product_count = (int)($pub['product_count'] ?? 0);
                ?>
                    <a href="/shop?brands[]=<?= $pub_id ?>" class="group bg-white rounded-xl border border-neutral-200 hover:border-neutral-900 hover:shadow-lg transition-all duration-300 flex flex-col justify-center items-center text-center p-5 sm:p-7">

                        <div class="flex flex-col items-center justify-center w-full">
                            <h3 class="text-sm font-bold text-neutral-900 group-hover:text-neutral-500 transition-colors truncate max-w-full leading-snug">
                                <?= $pub_name; ?>
                            </h3>
                            <div class="mt-2">
                                <span class="inline-flex items-center text-[10px] font-semibold text-neutral-500 px-2.5 py-0.5 rounded-full bg-neutral-100">
                                    <?= number_format($product_count); ?> <?= $product_count === 1 ? 'item' : 'items'; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-10 bg-white rounded-2xl border border-slate-200 text-slate-400 text-xs">
                    No brands found in the catalog.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ==================== 7. STYLE JOURNAL ==================== -->
    <section class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 py-10 pb-16">
        <div class="flex justify-between items-end mb-5 border-b border-neutral-200 pb-4">
            <div>
                <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-[0.2em]">The 4MEN edit</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-neutral-950 tracking-tight mt-1">Style journal</h2>
                <p class="text-neutral-500 text-sm mt-1">Ideas, inspiration and notes on dressing well.</p>
            </div>
            <a href="/blog" class="text-xs font-bold text-neutral-900 hover:text-neutral-500 uppercase tracking-wider">
                Read the journal &rarr;
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
