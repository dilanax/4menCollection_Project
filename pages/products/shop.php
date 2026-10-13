<?php
// 1. Filter Parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Flexible Category Input Handling (Supports: categories[], category, and slug)
$categories_filter = [];
if (isset($_GET['categories']) && is_array($_GET['categories'])) {
    $categories_filter = array_filter($_GET['categories']);
} elseif (!empty($_GET['category'])) {
    $categories_filter = [trim($_GET['category'])];
} elseif (!empty($_GET['slug'])) {
    $categories_filter = [trim($_GET['slug'])];
}

// Flexible Brands/Publishers Input Handling (Supports: brands[] and brand)
$brands_filter = [];
if (isset($_GET['brands']) && is_array($_GET['brands'])) {
    $brands_filter = array_filter($_GET['brands']);
} elseif (!empty($_GET['brand'])) {
    $brands_filter = [(int)$_GET['brand']];
}

$authors_filter = isset($_GET['authors']) && is_array($_GET['authors']) ? array_filter($_GET['authors']) : [];
$stock_filters = isset($_GET['stock']) && is_array($_GET['stock']) ? array_filter($_GET['stock']) : [];
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : 100000;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// 2. Pagination (20 items per page)
$items_per_page = 20;
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// 3. Base SQL Query
$sql_base = "FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')";
$params = [];

if (!empty($search_query)) {
    $sql_base .= " AND (p.name LIKE ? OR p.short_description LIKE ? OR p.sku LIKE ?)";
    $search_term = "%{$search_query}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($categories_filter)) {
    $placeholders = implode(',', array_fill(0, count($categories_filter), '?'));
    // Filters items matching directly or matching via child sub-categories
    $sql_base .= " AND p.id IN (
        SELECT pc.product_id FROM product_categories pc 
        JOIN categories c ON pc.category_id = c.id 
        WHERE c.slug IN ($placeholders) 
           OR c.parent_id IN (SELECT sub.id FROM categories sub WHERE sub.slug IN ($placeholders))
    )";
    foreach ($categories_filter as $c_slug) {
        $params[] = $c_slug;
    }
    foreach ($categories_filter as $c_slug) {
        $params[] = $c_slug;
    }
}

if (!empty($brands_filter)) {
    $placeholders = implode(',', array_fill(0, count($brands_filter), '?'));
    $sql_base .= " AND b.id IN ($placeholders)";
    foreach ($brands_filter as $b_id) {
        $params[] = (int)$b_id;
    }
}

if (!empty($authors_filter)) {
    $author_clauses = [];
    foreach ($authors_filter as $auth) {
        $author_clauses[] = "pa.terms_json LIKE ?";
        $params[] = "%" . $auth . "%";
    }
    if (!empty($author_clauses)) {
        $sql_base .= " AND p.id IN (
            SELECT pa.product_id FROM product_attributes pa 
            JOIN attributes a ON pa.attribute_id = a.id 
            WHERE (a.slug = 'author' OR LOWER(a.name) = 'author') 
            AND (" . implode(' OR ', $author_clauses) . ")
        )";
    }
}

if (!empty($stock_filters)) {
    $stock_clauses = [];
    if (in_array('instock', $stock_filters)) {
        $stock_clauses[] = "p.stock_status = 'instock'";
    }
    if (in_array('sale', $stock_filters)) {
        $stock_clauses[] = "(p.sale_price IS NOT NULL AND p.sale_price > 0)";
    }
    if (!empty($stock_clauses)) {
        $sql_base .= " AND (" . implode(' OR ', $stock_clauses) . ")";
    }
}

if ($min_price > 0 || $max_price < 100000) {
    $sql_base .= " AND (COALESCE(p.sale_price, p.regular_price) BETWEEN ? AND ?)";
    $params[] = $min_price;
    $params[] = $max_price;
}

$order_by = "ORDER BY p.created_at DESC";
switch ($sort_by) {
    case 'price_low':
        $order_by = "ORDER BY COALESCE(p.sale_price, p.regular_price) ASC";
        break;
    case 'price_high':
        $order_by = "ORDER BY COALESCE(p.sale_price, p.regular_price) DESC";
        break;
    case 'name_asc':
        $order_by = "ORDER BY p.name ASC";
        break;
}

$count_stmt = $pdo->prepare("SELECT COUNT(p.id) " . $sql_base);
$count_stmt->execute($params);
$total_items = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$sql = "SELECT p.id, p.name, p.slug, p.main_image, p.regular_price, p.sale_price, p.type, p.stock_status, b.name as brand_name " . $sql_base . " " . $order_by . " LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $val) {
    $stmt->bindValue($key + 1, $val);
}
$stmt->bindValue(count($params) + 1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// 4. AJAX Load More Handling (Books Grid)
if ($is_ajax && isset($_GET['action']) && $_GET['action'] === 'load_more') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    ob_start();
    foreach ($products as $p) {
        include __DIR__ . '/cards/product_card_1.php';
    }
    $html = ob_get_clean();

    echo json_encode([
        'status' => 'success',
        'html' => $html,
        'has_more' => ($current_page < $total_pages),
        'next_page' => $current_page + 1,
        'loaded_count' => min($total_items, $current_page * $items_per_page),
        'total' => $total_items
    ]);
    exit;
}

// 5. Fetch Sidebar Filter Options
$cats = $pdo->query("SELECT name, slug, (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = categories.id) as count FROM categories WHERE slug NOT IN ('vinura-ransika', 'vinura_ransika') ORDER BY name ASC")->fetchAll();
$brands = $pdo->query("SELECT id, name, (SELECT COUNT(*) FROM products p WHERE p.brand_id = brands.id AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')) as count FROM brands ORDER BY count DESC, name ASC")->fetchAll();

// Fetch Authors from 'attributes' and 'attribute_terms'
$authors = [];
try {
    $auth_stmt = $pdo->query("SELECT t.id, t.name, t.slug,
        (
            SELECT COUNT(*) FROM product_attributes pa 
            JOIN products p ON pa.product_id = p.id
            WHERE pa.attribute_id = t.attribute_id 
            AND (pa.terms_json LIKE CONCAT('%\"', t.name, '\"%') OR pa.terms_json LIKE CONCAT('%', t.name, '%'))
            AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
        ) as count
        FROM attribute_terms t 
        JOIN attributes a ON t.attribute_id = a.id 
        WHERE a.slug = 'author' OR LOWER(a.name) = 'author' 
        ORDER BY count DESC, t.name ASC");
    if ($auth_stmt) {
        $authors = $auth_stmt->fetchAll();
    }
} catch (Throwable $e) {
    $authors = [];
}
?>

<!-- Poppins Font Setup and Scroll Customization -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
    
    .shop-container, .shop-container * {
        font-family: 'Poppins', sans-serif;
    }

    .filter-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .filter-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    .filter-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .filter-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<div class="shop-container bg-[#f8fafc] py-6 sm:py-8 min-h-screen text-slate-800">
    <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12">
        
        <!-- Header & Counter -->
        <div class="mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <nav class="flex items-center text-xs font-semibold text-slate-400 mb-2 gap-2">
                    <a href="/" class="hover:text-sky-700 transition-colors">Home</a>
                    <span>/</span>
                    <span class="text-sky-700 font-bold">Book Catalog</span>
                </nav>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                    Explore Bookstore
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Showing <span id="showing-count" class="font-bold text-slate-900"><?= min($total_items, count($products)); ?></span> of <span id="total-count" class="font-bold text-slate-900"><?= $total_items; ?></span> titles
                </p>
            </div>
            
            <button onclick="toggleMobileFilter()" class="lg:hidden w-full md:w-auto bg-white border border-slate-300 text-slate-800 px-4 py-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 shadow-xs hover:border-sky-700 transition-colors">
                <svg class="w-4 h-4 text-sky-700 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                Filter Books (<?= count($categories_filter) + count($brands_filter) + count($authors_filter) + count($stock_filters); ?> Active)
            </button>
        </div>

        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            <!-- Mobile Backdrop -->
            <div id="filter-overlay" onclick="toggleMobileFilter()" class="fixed inset-0 bg-slate-950/60 z-40 hidden lg:hidden backdrop-blur-xs transition-opacity duration-300"></div>

            <!-- ==================== 1. FIXED / STICKY FILTER SIDEBAR ==================== -->
            <aside id="filter-sidebar" class="fixed inset-y-0 left-0 w-80 bg-white z-50 transform -translate-x-full transition-transform duration-300 lg:translate-x-0 lg:static lg:w-64 xl:w-72 lg:bg-transparent lg:z-10 shrink-0 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] overflow-y-auto filter-scrollbar pr-1">
                
                <div class="p-6 lg:p-0 flex flex-col space-y-4">
                    
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 lg:hidden">
                        <span class="text-sm font-black text-slate-900 tracking-wider uppercase">Filter Books</span>
                        <button onclick="toggleMobileFilter()" class="text-slate-500 hover:text-slate-800 p-1 text-lg">✕</button>
                    </div>

                    <form action="/shop" method="GET" id="filter-form" class="space-y-4">
                        
                        <!-- Search Box -->
                        <div class="bg-white border border-slate-200/90 p-4 rounded-2xl shadow-xs transition-all hover:border-slate-300">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Search Catalog</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    name="search" 
                                    value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>" 
                                    placeholder="Title, ISBN, author..." 
                                    class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 text-xs text-slate-800 rounded-xl focus:bg-white focus:border-sky-700 outline-none transition-colors"
                                >
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3 pointer-events-none stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                        </div>

                        <!-- Categories Filter Checkboxes -->
                        <div class="bg-white border border-slate-200/90 p-4 rounded-2xl shadow-xs transition-all hover:border-slate-300">
                            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Categories</span>
                                <?php if (!empty($categories_filter)): ?>
                                    <span class="text-[10px] font-bold text-sky-700 bg-sky-50 border border-sky-100 px-2 py-0.5 rounded-md"><?= count($categories_filter); ?> active</span>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-2.5 max-h-48 overflow-y-auto filter-scrollbar pr-1">
                                <?php foreach ($cats as $c): if ($c['count'] > 0): ?>
                                    <label class="flex items-center justify-between cursor-pointer group select-none py-0.5">
                                        <div class="flex items-center gap-2.5">
                                            <div class="relative flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    name="categories[]" 
                                                    value="<?= htmlspecialchars($c['slug']); ?>" 
                                                    <?= in_array($c['slug'], $categories_filter) ? 'checked' : ''; ?> 
                                                    class="peer sr-only"
                                                >
                                                <div class="w-4 h-4 border border-slate-300 bg-white peer-checked:bg-sky-700 peer-checked:border-sky-700 flex items-center justify-center transition-colors rounded-md">
                                                    <svg class="w-2.5 h-2.5 text-white stroke-[3] hidden peer-checked:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                </div>
                                            </div>
                                            <span class="text-xs text-slate-700 group-hover:text-sky-700 transition-colors <?= in_array($c['slug'], $categories_filter) ? 'font-bold text-sky-700' : ''; ?>">
                                                <?= htmlspecialchars($c['name']); ?>
                                            </span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-md"><?= $c['count']; ?></span>
                                    </label>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>

                        <!-- Authors Filter Checkboxes -->
                        <?php if (!empty($authors)): ?>
                            <div class="bg-white border border-slate-200/90 p-4 rounded-2xl shadow-xs transition-all hover:border-slate-300">
                                <div class="flex items-center justify-between mb-2 border-b border-slate-100 pb-2">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Authors</span>
                                    <?php if (!empty($authors_filter)): ?>
                                        <span class="text-[10px] font-bold text-sky-700 bg-sky-50 border border-sky-100 px-2 py-0.5 rounded-md"><?= count($authors_filter); ?> active</span>
                                    <?php endif; ?>
                                </div>

                                <div class="relative mb-2.5">
                                    <input 
                                        type="text" 
                                        id="author-filter-search" 
                                        placeholder="Search authors..." 
                                        class="w-full pl-7 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 text-[11px] text-slate-800 rounded-lg outline-none focus:bg-white focus:border-sky-700 transition-colors"
                                    >
                                    <svg class="w-3 h-3 text-slate-400 absolute left-2 top-2.5 pointer-events-none stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>

                                <div id="author-checkbox-list" class="space-y-2 max-h-48 overflow-y-auto filter-scrollbar pr-1">
                                    <?php 
                                    $a_index = 0;
                                    foreach ($authors as $auth): 
                                        $a_index++;
                                        $isChecked = in_array($auth['name'], $authors_filter);
                                        $isHidden = ($a_index > 8 && !$isChecked);
                                    ?>
                                        <label class="author-filter-item flex items-center justify-between cursor-pointer group select-none py-0.5 <?= $isHidden ? 'hidden' : ''; ?>" data-name="<?= strtolower(htmlspecialchars($auth['name'])); ?>">
                                            <div class="flex items-center gap-2.5">
                                                <div class="relative flex items-center">
                                                    <input 
                                                        type="checkbox" 
                                                        name="authors[]" 
                                                        value="<?= htmlspecialchars($auth['name']); ?>" 
                                                        <?= $isChecked ? 'checked' : ''; ?> 
                                                        class="peer sr-only"
                                                    >
                                                    <div class="w-4 h-4 border border-slate-300 bg-white peer-checked:bg-sky-700 peer-checked:border-sky-700 flex items-center justify-center transition-colors rounded-md">
                                                        <svg class="w-2.5 h-2.5 text-white stroke-[3] hidden peer-checked:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    </div>
                                                </div>
                                                <span class="text-xs text-slate-700 group-hover:text-sky-700 transition-colors truncate max-w-[140px] <?= $isChecked ? 'font-bold text-sky-700' : ''; ?>">
                                                    <?= htmlspecialchars($auth['name']); ?>
                                                </span>
                                            </div>
                                            <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-md"><?= (int)$auth['count']; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($a_index > 8): ?>
                                    <div class="pt-2 mt-1 border-t border-slate-100 text-center">
                                        <button 
                                            type="button" 
                                            id="load-more-authors-btn" 
                                            class="text-[11px] font-bold text-sky-700 hover:text-sky-800 hover:underline inline-flex items-center gap-1"
                                        >
                                            <span>Show More (+<?= max(0, $a_index - 8); ?>)</span>
                                            <svg class="w-3 h-3 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Publishers Checkboxes -->
                        <div class="bg-white border border-slate-200/90 p-4 rounded-2xl shadow-xs transition-all hover:border-slate-300">
                            <div class="flex items-center justify-between mb-2 border-b border-slate-100 pb-2">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Publishers</span>
                                <?php if (!empty($brands_filter)): ?>
                                    <span class="text-[10px] font-bold text-sky-700 bg-sky-50 border border-sky-100 px-2 py-0.5 rounded-md"><?= count($brands_filter); ?> active</span>
                                <?php endif; ?>
                            </div>

                            <div class="relative mb-2.5">
                                <input 
                                    type="text" 
                                    id="brand-filter-search" 
                                    placeholder="Search publishers..." 
                                    class="w-full pl-7 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 text-[11px] text-slate-800 rounded-lg outline-none focus:bg-white focus:border-sky-700 transition-colors"
                                >
                                <svg class="w-3 h-3 text-slate-400 absolute left-2 top-2.5 pointer-events-none stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>

                            <div id="publisher-checkbox-list" class="space-y-2 max-h-48 overflow-y-auto filter-scrollbar pr-1">
                                <?php 
                                $b_index = 0;
                                foreach ($brands as $b): 
                                    if ($b['count'] > 0): 
                                        $b_index++;
                                        $isChecked = in_array($b['id'], $brands_filter);
                                        $isHidden = ($b_index > 8 && !$isChecked);
                                ?>
                                    <label class="publisher-filter-item flex items-center justify-between cursor-pointer group select-none py-0.5 <?= $isHidden ? 'hidden' : ''; ?>" data-name="<?= strtolower(htmlspecialchars($b['name'])); ?>">
                                        <div class="flex items-center gap-2.5">
                                            <div class="relative flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    name="brands[]" 
                                                    value="<?= $b['id']; ?>" 
                                                    <?= $isChecked ? 'checked' : ''; ?> 
                                                    class="peer sr-only"
                                                >
                                                <div class="w-4 h-4 border border-slate-300 bg-white peer-checked:bg-sky-700 peer-checked:border-sky-700 flex items-center justify-center transition-colors rounded-md">
                                                    <svg class="w-2.5 h-2.5 text-white stroke-[3] hidden peer-checked:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                </div>
                                            </div>
                                            <span class="text-xs text-slate-700 group-hover:text-sky-700 transition-colors truncate max-w-[140px] <?= $isChecked ? 'font-bold text-sky-700' : ''; ?>">
                                                <?= htmlspecialchars($b['name']); ?>
                                            </span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-md"><?= $b['count']; ?></span>
                                    </label>
                                <?php endif; endforeach; ?>
                            </div>

                            <?php if ($b_index > 8): ?>
                                <div class="pt-2 mt-1 border-t border-slate-100 text-center">
                                    <button 
                                        type="button" 
                                        id="load-more-publishers-btn" 
                                        class="text-[11px] font-bold text-sky-700 hover:text-sky-800 hover:underline inline-flex items-center gap-1"
                                    >
                                        <span>Show More (+<?= max(0, $b_index - 8); ?>)</span>
                                        <svg class="w-3 h-3 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Availability Checkboxes -->
                        <div class="bg-white border border-slate-200/90 p-4 rounded-2xl shadow-xs transition-all hover:border-slate-300">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3 border-b border-slate-100 pb-2">Status & Offers</span>
                            <div class="space-y-2.5">
                                <label class="flex items-center gap-2.5 cursor-pointer select-none py-0.5">
                                    <div class="relative flex items-center">
                                        <input 
                                            type="checkbox" 
                                            name="stock[]" 
                                            value="instock" 
                                            <?= in_array('instock', $stock_filters) ? 'checked' : ''; ?> 
                                            class="peer sr-only"
                                        >
                                        <div class="w-4 h-4 border border-slate-300 bg-white peer-checked:bg-sky-700 peer-checked:border-sky-700 flex items-center justify-center transition-colors rounded-md">
                                            <svg class="w-2.5 h-2.5 text-white stroke-[3] hidden peer-checked:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                    </div>
                                    <span class="text-xs text-slate-700">In Stock Titles</span>
                                </label>

                                <label class="flex items-center gap-2.5 cursor-pointer select-none py-0.5">
                                    <div class="relative flex items-center">
                                        <input 
                                            type="checkbox" 
                                            name="stock[]" 
                                            value="sale" 
                                            <?= in_array('sale', $stock_filters) ? 'checked' : ''; ?> 
                                            class="peer sr-only"
                                        >
                                        <div class="w-4 h-4 border border-slate-300 bg-white peer-checked:bg-rose-600 peer-checked:border-rose-600 flex items-center justify-center transition-colors rounded-md">
                                            <svg class="w-2.5 h-2.5 text-white stroke-[3] hidden peer-checked:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                    </div>
                                    <span class="text-xs text-rose-600 font-bold">Discounted Offers</span>
                                </label>
                            </div>
                        </div>

                        <!-- Price Range Box -->
                        <div class="bg-white border border-slate-200/90 p-4 rounded-2xl shadow-xs transition-all hover:border-slate-300">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3 border-b border-slate-100 pb-2">Price Range (Rs.)</span>
                            <div class="flex items-center gap-2 mb-3">
                                <input type="number" name="min_price" value="<?= $min_price > 0 ? $min_price : ''; ?>" placeholder="Min" class="w-full bg-slate-50 border border-slate-200 text-xs px-2.5 py-2 text-center rounded-xl outline-none focus:bg-white focus:border-sky-700 transition-colors">
                                <span class="text-slate-400 text-xs">-</span>
                                <input type="number" name="max_price" value="<?= $max_price < 100000 ? $max_price : ''; ?>" placeholder="Max" class="w-full bg-slate-50 border border-slate-200 text-xs px-2.5 py-2 text-center rounded-xl outline-none focus:bg-white focus:border-sky-700 transition-colors">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-sky-700 hover:bg-sky-800 text-white font-bold text-xs py-3 uppercase tracking-wider rounded-xl transition-all shadow-md shadow-sky-700/20 active:scale-98">
                            Apply Filters
                        </button>

                        <?php if (!empty($search_query) || !empty($categories_filter) || !empty($brands_filter) || !empty($authors_filter) || !empty($stock_filters) || $min_price > 0 || $max_price < 100000): ?>
                            <a href="/shop" class="block text-center text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 border border-rose-200 py-2.5 rounded-xl transition-colors">
                                Reset All Filters
                            </a>
                        <?php endif; ?>

                    </form>
                </div>
            </aside>

            <!-- ==================== 2. PRODUCT GRID SECTION ==================== -->
            <div class="flex-1 w-full space-y-4">
                
                <!-- Styled Sort Bar -->
                <div class="bg-white border border-slate-200/90 p-3 rounded-2xl flex justify-between items-center shadow-xs">
                    <span class="text-xs font-black text-slate-800 tracking-wider uppercase">Books Available</span>
                    
                    <div class="flex items-center gap-2">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider hidden sm:block">Sort By:</label>
                        <div class="relative inline-block">
                            <select form="filter-form" name="sort" onchange="document.getElementById('filter-form').submit();" class="appearance-none text-xs bg-slate-50 border border-slate-200 pl-3 pr-8 py-2 font-bold text-slate-700 outline-none rounded-xl cursor-pointer focus:border-sky-700 transition-colors">
                                <option value="newest" <?= $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?= $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?= $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="name_asc" <?= $sort_by == 'name_asc' ? 'selected' : ''; ?>>Title: A to Z</option>
                            </select>
                            <span class="absolute right-2.5 top-3 pointer-events-none text-slate-500">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Books Responsive Grid -->
                <?php if (!empty($products)): ?>
                    <div id="books-grid" class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 2xl:grid-cols-5 gap-3 sm:gap-4">
                        <?php foreach ($products as $p): ?>
                            <?php include __DIR__ . '/cards/product_card_1.php'; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- 20-by-20 AJAX Load More Button Container -->
                    <div id="load-more-container" class="pt-8 pb-4 text-center <?= ($current_page >= $total_pages) ? 'hidden' : ''; ?>">
                        <button 
                            type="button" 
                            id="load-more-btn"
                            data-page="<?= $current_page + 1; ?>"
                            data-total-pages="<?= $total_pages; ?>"
                            class="inline-flex items-center justify-center gap-2 bg-white hover:bg-sky-700 hover:text-white text-slate-800 border-2 border-slate-200 hover:border-sky-700 px-8 py-3.5 text-xs font-black uppercase tracking-widest transition-all duration-300 rounded-2xl shadow-xs active:scale-95 group"
                        >
                            <span class="btn-text">Load More Books (+20)</span>
                            <span class="btn-spinner hidden">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </span>
                        </button>
                    </div>

                <?php else: ?>
                    <div class="bg-white border border-slate-200/90 p-12 text-center rounded-3xl shadow-xs">
                        <p class="text-sm font-bold text-slate-800 mb-1">No matching titles found</p>
                        <p class="text-xs text-slate-400 mb-6">Try clearing or unchecking some of your active filters.</p>
                        <a href="/shop" class="inline-block bg-sky-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl uppercase tracking-wider hover:bg-sky-800 transition-colors shadow-xs">
                            Clear Filters
                        </a>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</div>

<script>
    // Mobile Drawer Toggle
    function toggleMobileFilter() {
        const sidebar = document.getElementById('filter-sidebar');
        const overlay = document.getElementById('filter-overlay');
        
        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    // Client-side Filter Search for Authors in Sidebar
    document.getElementById('author-filter-search')?.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase().trim();
        const items = document.querySelectorAll('.author-filter-item');
        const loadMoreAuthorsBtn = document.getElementById('load-more-authors-btn');
        
        if (query.length > 0) {
            if (loadMoreAuthorsBtn) loadMoreAuthorsBtn.parentElement.classList.add('hidden');
            items.forEach(item => {
                const name = item.getAttribute('data-name') || '';
                if (name.includes(query)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        } else {
            if (loadMoreAuthorsBtn) loadMoreAuthorsBtn.parentElement.classList.remove('hidden');
            let count = 0;
            items.forEach(item => {
                const isChecked = item.querySelector('input[type="checkbox"]')?.checked;
                count++;
                if (count <= 8 || isChecked) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }
    });

    // Load More Authors in Sidebar Toggle
    document.getElementById('load-more-authors-btn')?.addEventListener('click', function() {
        const hiddenItems = document.querySelectorAll('.author-filter-item.hidden');
        hiddenItems.forEach(el => el.classList.remove('hidden'));
        this.parentElement.classList.add('hidden');
    });

    // Client-side Filter Search for Publishers in Sidebar
    document.getElementById('brand-filter-search')?.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase().trim();
        const items = document.querySelectorAll('.publisher-filter-item');
        const loadMorePubsBtn = document.getElementById('load-more-publishers-btn');
        
        if (query.length > 0) {
            if (loadMorePubsBtn) loadMorePubsBtn.parentElement.classList.add('hidden');
            items.forEach(item => {
                const name = item.getAttribute('data-name') || '';
                if (name.includes(query)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        } else {
            if (loadMorePubsBtn) loadMorePubsBtn.parentElement.classList.remove('hidden');
            let count = 0;
            items.forEach(item => {
                const isChecked = item.querySelector('input[type="checkbox"]')?.checked;
                count++;
                if (count <= 8 || isChecked) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }
    });

    // Load More Publishers in Sidebar Toggle
    document.getElementById('load-more-publishers-btn')?.addEventListener('click', function() {
        const hiddenItems = document.querySelectorAll('.publisher-filter-item.hidden');
        hiddenItems.forEach(el => el.classList.remove('hidden'));
        this.parentElement.classList.add('hidden');
    });

    // AJAX 20-by-20 Load More Logic for Books
    document.addEventListener("DOMContentLoaded", function() {
        const loadMoreBtn = document.getElementById('load-more-btn');
        const loadMoreContainer = document.getElementById('load-more-container');
        const booksGrid = document.getElementById('books-grid');
        const showingCountSpan = document.getElementById('showing-count');

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                const btn = this;
                const nextPage = parseInt(btn.getAttribute('data-page')) || 2;
                const totalPages = parseInt(btn.getAttribute('data-total-pages')) || 1;

                const textSpan = btn.querySelector('.btn-text');
                const spinnerSpan = btn.querySelector('.btn-spinner');

                btn.disabled = true;
                textSpan.classList.add('hidden');
                spinnerSpan.classList.remove('hidden');

                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('p', nextPage);
                currentUrl.searchParams.set('action', 'load_more');

                fetch(currentUrl.toString(), {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        booksGrid.insertAdjacentHTML('beforeend', data.html);

                        if (showingCountSpan) {
                            showingCountSpan.innerText = data.loaded_count;
                        }

                        if (data.has_more) {
                            btn.setAttribute('data-page', data.next_page);
                            btn.disabled = false;
                            textSpan.classList.remove('hidden');
                            spinnerSpan.classList.add('hidden');
                        } else {
                            loadMoreContainer.classList.add('hidden');
                        }
                    } else {
                        alert('Unable to load more books.');
                        btn.disabled = false;
                        textSpan.classList.remove('hidden');
                        spinnerSpan.classList.add('hidden');
                    }
                })
                .catch(err => {
                    console.error('Load more error:', err);
                    btn.disabled = false;
                    textSpan.classList.remove('hidden');
                    spinnerSpan.classList.add('hidden');
                });
            });
        }
    });
</script>