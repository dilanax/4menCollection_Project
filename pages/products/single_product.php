<?php
// 1. Get Slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    echo "<div class='min-h-[50vh] flex flex-col items-center justify-center text-center px-4 py-16' style=\"font-family: 'Poppins', sans-serif;\">
            <h2 class='text-2xl font-black text-slate-800 mb-2'>Book not found.</h2>
            <p class='text-sm text-slate-500 mb-6'>The book you are searching for is missing or invalid.</p>
            <a href='/shop' class='bg-sky-700 hover:bg-sky-800 text-white font-bold text-xs px-6 py-3 rounded-xl transition shadow-sm'>Browse Bookstore</a>
          </div>";
    return;
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// 2. Include AJAX Actions Handler
require_once __DIR__ . '/single_product/ajax_handler.php';

// 3. Retrieve Product Data
$stmt = $pdo->prepare("SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE p.slug = ? AND p.status = 'published'");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='min-h-[50vh] flex flex-col items-center justify-center text-center px-4 py-16' style=\"font-family: 'Poppins', sans-serif;\">
            <h2 class='text-2xl font-black text-slate-800 mb-2'>Book is unavailable.</h2>
            <p class='text-sm text-slate-500 mb-6'>This title has been unpublished or moved.</p>
            <a href='/shop' class='bg-sky-700 hover:bg-sky-800 text-white font-bold text-xs px-6 py-3 rounded-xl transition shadow-sm'>Browse Store</a>
          </div>";
    return;
}

$product_id = (int)$product['id'];

// Check Out of Stock Status
$is_out_of_stock = false;
if ($product['manage_stock'] == 1 && (int)$product['stock_quantity'] <= 0) {
    $is_out_of_stock = true;
} elseif (($product['stock_status'] ?? '') === 'outofstock') {
    $is_out_of_stock = true;
}

// Pricing calculation
$isOnSale = false;
$currentPrice = (float)$product['regular_price'];

if (!empty($product['sale_price']) && (float)$product['sale_price'] > 0) {
    $today = date('Y-m-d');
    $sale_start = $product['sale_price_dates_from'] ?? null;
    $sale_end = $product['sale_price_dates_to'] ?? null;
    
    if ((empty($sale_start) || $sale_start <= $today) && (empty($sale_end) || $sale_end >= $today)) {
        $isOnSale = true;
        $currentPrice = (float)$product['sale_price'];
    }
}

// Discount percentage
$discount_percentage = 0;
if ($isOnSale && (float)$product['regular_price'] > 0) {
    $discount_percentage = round((((float)$product['regular_price'] - $currentPrice) / (float)$product['regular_price']) * 100);
}

// Gallery image array
$gallery_images = !empty($product['gallery']) ? json_decode($product['gallery'], true) : [];
if (!is_array($gallery_images)) $gallery_images = [];
if (!empty($product['main_image']) && !in_array($product['main_image'], $gallery_images)) {
    array_unshift($gallery_images, $product['main_image']); 
}

// Attributes & Variations
$attributes = [];
$variations = [];
if ($product['type'] == 'variable') {
    $attr_stmt = $pdo->prepare("SELECT a.name, pa.terms_json FROM product_attributes pa JOIN attributes a ON pa.attribute_id = a.id WHERE pa.product_id = ?");
    $attr_stmt->execute([$product_id]);
    foreach ($attr_stmt->fetchAll() as $row) {
        $attributes[$row['name']] = json_decode($row['terms_json'], true);
    }
    
    $var_stmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ?");
    $var_stmt->execute([$product_id]);
    $variations = $var_stmt->fetchAll();
}

// Upsell Recommendations
$upsells = [];
if (!empty($product['upsell_ids'])) {
    $upsell_ids = array_filter(array_map('trim', explode(',', $product['upsell_ids'])));
    if (!empty($upsell_ids)) {
        $in = str_repeat('?,', count($upsell_ids) - 1) . '?';
        $ups_stmt = $pdo->prepare("SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE p.id IN ($in) AND p.status='published' LIMIT 6");
        $ups_stmt->execute($upsell_ids);
        $upsells = $ups_stmt->fetchAll();
    }
}

// Categories
$cat_stmt = $pdo->prepare("SELECT c.name, c.slug FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = ?");
$cat_stmt->execute([$product_id]);
$categories = $cat_stmt->fetchAll();

// Reviews check
$can_review = false;
$has_reviewed = false;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $chk_bought = $pdo->prepare("SELECT o.id FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = ? AND oi.product_id = ? AND o.order_status = 'completed' LIMIT 1");
    $chk_bought->execute([$uid, $product_id]);
    if ($chk_bought->fetch()) {
        $can_review = true;
    }
    $chk_rev = $pdo->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
    $chk_rev->execute([$product_id, $uid]);
    if ($chk_rev->fetch()) {
        $has_reviewed = true;
        $can_review = false; 
    }
}

$rev_stmt = $pdo->prepare("SELECT r.rating, r.comment, r.created_at, u.username as user_name FROM product_reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
try {
    $rev_stmt->execute([$product_id]);
    $reviews = $rev_stmt->fetchAll();
} catch (Exception $e) {
    $reviews = [];
}

$avg_rating = 0.0;
$total_reviews = count($reviews);
if ($total_reviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($sum / $total_reviews, 1);
}
?>

<script>
    document.title = "<?= !empty($product['meta_title']) ? addslashes($product['meta_title']) : addslashes($product['name']); ?> | ReadersHub";
    document.querySelector('meta[name="description"]')?.setAttribute("content", "<?= addslashes($product['meta_description'] ?? $product['short_description']); ?>");
</script>

<div class="w-full bg-[#f8fafc] py-6 sm:py-10 min-h-screen relative" style="font-family: 'Poppins', sans-serif;">
    
    <!-- Floating Universal Toast Alert -->
    <div id="toast-container" class="fixed top-24 right-5 z-[100] transform translate-x-full transition-all duration-300 opacity-0 hidden">
        <div class="bg-white border-l-4 border-sky-700 shadow-2xl rounded-2xl p-4 flex items-center gap-3.5 min-w-[320px] max-w-sm border border-slate-200">
            <div class="w-10 h-10 bg-sky-50 text-sky-700 rounded-xl flex items-center justify-center shrink-0 border border-sky-100">
                <svg class="w-5 h-5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider" id="toast-title">Added to Basket!</h4>
                <p class="text-xs text-slate-500 font-medium truncate mt-0.5" id="toast-msg">Book added to your shopping cart.</p>
            </div>
            <a href="/cart" class="text-xs font-bold text-sky-700 hover:text-sky-800 bg-sky-50 px-2.5 py-1.5 rounded-lg transition shrink-0">View Cart</a>
        </div>
    </div>

    <!-- Full-Width Fluid Container Aligned with Store & Checkout -->
    <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 2xl:px-16">
        
        <!-- Breadcrumbs -->
        <nav class="flex items-center text-xs font-semibold text-slate-400 mb-6 gap-2 flex-wrap">
            <a href="/" class="hover:text-sky-700 transition">Home</a>
            <span>/</span>
            <a href="/shop" class="hover:text-sky-700 transition">Shop</a>
            <span>/</span>
            <?php if (!empty($categories[0])): ?>
                <a href="/category/<?= htmlspecialchars($categories[0]['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="hover:text-sky-700 transition">
                    <?= htmlspecialchars($categories[0]['name'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <span>/</span>
            <?php endif; ?>
            <span class="text-sky-700 font-bold truncate max-w-xs"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></span>
        </nav>

        <!-- Product Hero Card (Full Width Flex Row) -->
        <div class="w-full bg-white rounded-3xl shadow-sm border border-slate-200/90 overflow-hidden flex flex-col lg:flex-row mb-12 relative">
            <?php require_once __DIR__ . '/single_product/gallery.php'; ?>
            <?php require_once __DIR__ . '/single_product/details.php'; ?>
        </div>

        <!-- Book Tabs (Overview & Reviews) -->
        <?php require_once __DIR__ . '/single_product/reviews.php'; ?>

        <!-- Upsell Recommendations ("You May Also Like" - Full Width 6-Col Grid) -->
        <?php if (!empty($upsells)): ?>
            <div class="mb-14">
                <div class="flex items-center justify-between mb-5 border-b border-slate-200 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-sky-700 uppercase tracking-widest">Recommended</span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight mt-0.5">You May Also Like</h3>
                    </div>
                    <a href="/shop" class="text-xs font-bold text-sky-700 hover:text-sky-800 uppercase tracking-wider flex items-center gap-1 group">
                        <span>Explore Bookstore</span>
                        <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                    </a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3.5 sm:gap-4">
                    <?php foreach ($upsells as $upsell): 
                        $p = $upsell; 
                        include __DIR__ . '/cards/product_card_1.php'; 
                    endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Scripts and Styles -->
<?php require_once __DIR__ . '/single_product/scripts.php'; ?>