<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    header("Location: /login");
    exit;
}

require_once '../../config/db.php';

// SweetAlert Session Messages
$message = '';
$messageType = '';
if (isset($_SESSION['swal_msg'])) {
    $message = $_SESSION['swal_msg'];
    $messageType = $_SESSION['swal_type'];
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
}

// --- CSV Export Logic ---
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=readershub_products_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['ID', 'Name', 'SKU', 'Type', 'Regular Price', 'Sale Price', 'Status', 'Stock Status', 'Stock Qty', 'Categories', 'Brand']);
    
    $export_sql = "SELECT p.*, b.name as brand_name, 
        (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_names 
        FROM products p LEFT JOIN brands b ON p.brand_id = b.id ORDER BY p.id DESC";
    $export_stmt = $pdo->query($export_sql);
    
    while ($row = $export_stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'], $row['name'], $row['sku'], $row['type'], $row['regular_price'], $row['sale_price'], 
            $row['status'], $row['stock_status'], $row['stock_quantity'], $row['category_names'], $row['brand_name']
        ]);
    }
    fclose($output);
    exit;
}

// --- Filtering Parameters ---
$where_clauses = ["1=1"];
$params = [];

// 1. Search (Name or SKU)
$search = trim(htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'));
if (!empty($search)) {
    $where_clauses[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 2. Status Filter
$filter_status = htmlspecialchars($_GET['status'] ?? '', ENT_QUOTES, 'UTF-8');
if (!empty($filter_status) && in_array($filter_status, ['published', 'draft'])) {
    $where_clauses[] = "p.status = ?";
    $params[] = $filter_status;
}

// 3. Stock Status Filter
$filter_stock = htmlspecialchars($_GET['stock'] ?? '', ENT_QUOTES, 'UTF-8');
if (!empty($filter_stock) && in_array($filter_stock, ['instock', 'outofstock', 'onbackorder'])) {
    $where_clauses[] = "p.stock_status = ?";
    $params[] = $filter_stock;
}

// 4. Brand / Publisher Filter
$filter_brand = (int)($_GET['brand'] ?? 0);
if ($filter_brand > 0) {
    $where_clauses[] = "p.brand_id = ?";
    $params[] = $filter_brand;
}

// 5. Category Filter
$filter_category = (int)($_GET['category'] ?? 0);
if ($filter_category > 0) {
    $where_clauses[] = "p.id IN (SELECT product_id FROM product_categories WHERE category_id = ?)";
    $params[] = $filter_category;
}

// 6. Publish Date Filter
$filter_date = htmlspecialchars($_GET['date'] ?? '', ENT_QUOTES, 'UTF-8');
if ($filter_date == 'today') {
    $where_clauses[] = "DATE(p.publish_date) = CURDATE()";
} elseif ($filter_date == 'this_month') {
    $where_clauses[] = "MONTH(p.publish_date) = MONTH(CURDATE()) AND YEAR(p.publish_date) = YEAR(CURDATE())";
}

$where_sql = implode(' AND ', $where_clauses);

// --- Pagination Logic (25 per page) ---
$per_page = 25;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;

// Count Total
$count_stmt = $pdo->prepare("SELECT COUNT(p.id) FROM products p WHERE $where_sql");
$count_stmt->execute($params);
$total_products = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

// Fetch Paginated Products
$sql = "SELECT p.*, b.name as brand_name, 
        (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_names 
        FROM products p 
        LEFT JOIN brands b ON p.brand_id = b.id 
        WHERE $where_sql 
        ORDER BY p.id DESC 
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch Options for Filters
$categories_list = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$brands_list = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll();

// URL Query Builder for Pagination
$query_params = $_GET;
unset($query_params['page']);
$base_query_string = http_build_query($query_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Books & Products | ReadersHub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, input, textarea, select, button { font-family: 'Poppins', sans-serif !important; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-slate-800 antialiased">

    <?php 
    if (file_exists('../../sidebar.php')) { require_once '../../sidebar.php'; } 
    elseif (file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">
        
        <!-- Top Navigation Header -->
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3.5 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-open-header" type="button" class="md:hidden p-2 text-slate-600 hover:text-sky-700 focus:outline-none rounded-xl hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Catalog Overview</span>
                    <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 tracking-tight">All Books & Inventory</h1>
                </div>
            </div>
            
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap justify-end">
                <!-- CSV Import Form -->
                <form action="all_product_action.php" method="POST" enctype="multipart/form-data" class="flex items-center border border-slate-200 rounded-xl overflow-hidden bg-white shadow-xs">
                    <input type="hidden" name="action" value="import_csv">
                    <input type="file" name="import_csv" accept=".csv" required class="text-[10px] text-slate-500 file:hidden cursor-pointer w-24 sm:w-auto px-2 py-1">
                    <button type="submit" class="bg-slate-50 hover:bg-slate-100 text-slate-700 px-3 py-1.5 text-xs font-bold transition border-l border-slate-200">Import</button>
                </form>

                <!-- CSV Export Button -->
                <a href="?export_csv=1" class="text-xs font-bold text-slate-700 hover:text-sky-700 px-3.5 py-2 border border-slate-200 rounded-xl bg-white shadow-xs transition hidden sm:inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span>Export</span>
                </a>

                <!-- Add Book Button -->
                <a href="add_product.php" class="bg-sky-700 hover:bg-sky-800 text-white px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-md shadow-sky-700/20 flex items-center gap-1.5">
                    <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    <span>Add Book</span>
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">

            <!-- Filter Panel -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-4 sm:p-6 mb-6">
                <form action="" method="GET" class="flex flex-col xl:flex-row gap-3">
                    
                    <!-- Search Input -->
                    <div class="flex-1">
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title, author keyword, or ISBN..." class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl pl-10 pr-4 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none transition-colors">
                        </div>
                    </div>

                    <!-- Dropdowns Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5 xl:w-2/3">
                        <select name="category" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-3 py-2 text-xs focus:bg-white focus:border-sky-700 outline-none font-semibold">
                            <option value="">All Categories</option>
                            <?php foreach ($categories_list as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $filter_category == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="brand" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-3 py-2 text-xs focus:bg-white focus:border-sky-700 outline-none font-semibold">
                            <option value="">All Publishers</option>
                            <?php foreach ($brands_list as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo $filter_brand == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-3 py-2 text-xs focus:bg-white focus:border-sky-700 outline-none font-semibold">
                            <option value="">All Statuses</option>
                            <option value="published" <?php echo $filter_status == 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo $filter_status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>

                        <select name="stock" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-3 py-2 text-xs focus:bg-white focus:border-sky-700 outline-none font-semibold">
                            <option value="">All Stock</option>
                            <option value="instock" <?php echo $filter_stock == 'instock' ? 'selected' : ''; ?>>In Stock</option>
                            <option value="outofstock" <?php echo $filter_stock == 'outofstock' ? 'selected' : ''; ?>>Out of Stock</option>
                            <option value="onbackorder" <?php echo $filter_stock == 'onbackorder' ? 'selected' : ''; ?>>On Backorder</option>
                        </select>

                        <select name="date" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-3 py-2 text-xs focus:bg-white focus:border-sky-700 outline-none font-semibold col-span-2 sm:col-span-1">
                            <option value="">Any Date</option>
                            <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Added Today</option>
                            <option value="this_month" <?php echo $filter_date == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>

                    <!-- Filter Action Buttons -->
                    <div class="flex gap-2 shrink-0">
                        <button type="submit" class="bg-sky-700 hover:bg-sky-800 text-white px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-sm flex-1 xl:flex-initial">Filter</button>
                        <?php if (!empty($_GET) && (isset($_GET['search']) || isset($_GET['category']) || isset($_GET['brand']) || isset($_GET['status']) || isset($_GET['stock']) || isset($_GET['date']))): ?>
                            <a href="all_product.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold transition text-center flex-1 xl:flex-initial border border-slate-200">Clear</a>
                        <?php endif; ?>
                    </div>

                </form>
                
                <div class="mt-4 pt-3.5 border-t border-slate-100 text-xs font-medium text-slate-400 flex justify-between items-center flex-wrap gap-2">
                    <span>
                        Showing <strong class="text-slate-800"><?php echo min($total_products, $offset + 1); ?></strong> - <strong class="text-slate-800"><?php echo min($total_products, $offset + count($products)); ?></strong> of <strong class="text-slate-800"><?php echo $total_products; ?></strong> items
                    </span>
                    <a href="sample_import.csv" download="sample_import.csv" class="text-sky-700 hover:underline transition flex items-center gap-1 font-bold text-[11px]">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>Download Sample CSV</span>
                    </a>
                </div>
            </div>

            <!-- Desktop Data Table -->
            <div class="hidden lg:block bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden w-full mb-6">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-400 text-[10px] uppercase tracking-widest font-extrabold">
                            <th class="px-5 py-3.5 w-16">Cover</th>
                            <th class="px-5 py-3.5">Title & SKU</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Stock</th>
                            <th class="px-5 py-3.5">Price</th>
                            <th class="px-5 py-3.5">Categories & Publisher</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-slate-50/60 transition group">
                                <td class="px-5 py-3.5">
                                    <?php if ($p['main_image']): ?>
                                        <img src="/<?php echo htmlspecialchars($p['main_image']); ?>" class="w-10 aspect-[2/3] rounded-md object-cover border border-slate-200 shadow-xs">
                                    <?php else: ?>
                                        <div class="w-10 aspect-[2/3] rounded-md bg-slate-100 flex items-center justify-center border border-slate-200 text-slate-400">
                                            <svg class="w-5 h-5 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3.5">
                                    <p class="font-extrabold text-slate-900 text-sm line-clamp-1 max-w-sm" title="<?php echo htmlspecialchars($p['name']); ?>">
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </p>
                                    <div class="text-[11px] text-slate-400 mt-0.5 font-mono flex items-center gap-1.5">
                                        <span>ISBN: <?php echo !empty($p['sku']) ? htmlspecialchars($p['sku']) : '-'; ?></span>
                                        <span class="px-1.5 py-0.2 rounded bg-slate-100 border border-slate-200 text-slate-600 font-bold uppercase text-[9px]"><?php echo ucfirst($p['type']); ?></span>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5">
                                    <?php if ($p['status'] == 'published'): ?>
                                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-md text-[10px] font-black uppercase tracking-wider">Published</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-600 border border-slate-200 rounded-md text-[10px] font-black uppercase tracking-wider">Draft</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3.5">
                                    <?php if ($p['stock_status'] == 'instock'): ?>
                                        <span class="text-emerald-600 font-bold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> In Stock
                                        </span>
                                    <?php elseif ($p['stock_status'] == 'outofstock'): ?>
                                        <span class="text-rose-500 font-bold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Out of Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="text-amber-500 font-bold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> On Backorder
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($p['manage_stock'] == 1 && $p['stock_quantity'] !== null): ?>
                                        <p class="text-[10px] text-slate-400 font-bold mt-0.5 ml-3">(<?php echo $p['stock_quantity']; ?> left)</p>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3.5">
                                    <?php if (!empty($p['sale_price'])): ?>
                                        <span class="line-through text-slate-400 text-[11px] block">Rs. <?php echo number_format($p['regular_price'], 2); ?></span>
                                        <span class="text-rose-600 font-black text-sm">Rs. <?php echo number_format($p['sale_price'], 2); ?></span>
                                    <?php elseif (!empty($p['regular_price'])): ?>
                                        <span class="text-slate-900 font-bold text-sm">Rs. <?php echo number_format($p['regular_price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3.5">
                                    <p class="text-xs font-semibold text-sky-700 line-clamp-1">
                                        <?php echo !empty($p['category_names']) ? htmlspecialchars($p['category_names']) : '<span class="text-slate-400 font-normal italic">Uncategorized</span>'; ?>
                                    </p>
                                    <?php if ($p['brand_name']): ?>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase mt-0.5"><?php echo htmlspecialchars($p['brand_name']); ?></p>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="/product/<?php echo $p['slug']; ?>" target="_blank" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition" title="Preview on Bookstore">
                                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a href="edit_product.php?id=<?php echo $p['id']; ?>" class="p-1.5 text-sky-700 hover:text-sky-900 rounded-lg hover:bg-sky-50 transition" title="Edit Book">
                                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <button type="button" onclick="confirmDelete(<?php echo $p['id']; ?>)" class="p-1.5 text-rose-500 hover:text-rose-700 rounded-lg hover:bg-rose-50 transition" title="Delete">
                                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-14 text-center">
                                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 mb-3">
                                        <svg class="w-6 h-6 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    </div>
                                    <p class="text-slate-800 font-bold text-sm mb-1">No products found</p>
                                    <p class="text-slate-400 text-xs mb-4">Try clearing active filters or create a new title.</p>
                                    <a href="add_product.php" class="inline-block bg-sky-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm hover:bg-sky-800 transition">Add New Product</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="lg:hidden space-y-3 mb-6">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $p): ?>
                        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs relative flex flex-col gap-3">
                            <div class="flex gap-3.5 items-start pr-16">
                                <?php if ($p['main_image']): ?>
                                    <img src="/<?php echo htmlspecialchars($p['main_image']); ?>" class="w-14 aspect-[2/3] rounded-lg object-cover border border-slate-200 shadow-xs shrink-0">
                                <?php else: ?>
                                    <div class="w-14 aspect-[2/3] rounded-lg bg-slate-100 flex items-center justify-center border border-slate-200 shrink-0 text-slate-400">
                                        <svg class="w-6 h-6 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    </div>
                                <?php endif; ?>

                                <div class="min-w-0">
                                    <h3 class="font-extrabold text-slate-900 text-sm line-clamp-2 leading-tight mb-1"><?php echo htmlspecialchars($p['name']); ?></h3>
                                    <p class="text-[11px] font-mono text-slate-400 mb-1.5">ISBN: <?php echo !empty($p['sku']) ? htmlspecialchars($p['sku']) : '-'; ?></p>
                                    
                                    <div class="flex flex-wrap gap-1">
                                        <?php if ($p['status'] == 'published'): ?>
                                            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[9px] font-black uppercase">Published</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 rounded text-[9px] font-black uppercase">Draft</span>
                                        <?php endif; ?>
                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 rounded text-[9px] font-black uppercase"><?php echo ucfirst($p['type']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile Actions in corner -->
                            <div class="absolute top-3.5 right-3.5 flex gap-1">
                                <a href="edit_product.php?id=<?php echo $p['id']; ?>" class="p-2 text-sky-700 bg-sky-50 rounded-lg hover:bg-sky-100 transition">
                                    <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button type="button" onclick="confirmDelete(<?php echo $p['id']; ?>)" class="p-2 text-rose-600 bg-rose-50 rounded-lg hover:bg-rose-100 transition">
                                    <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                            <!-- Price & Stock Info Box -->
                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Price</span>
                                    <?php if (!empty($p['sale_price'])): ?>
                                        <span class="text-rose-600 font-black">Rs. <?php echo number_format($p['sale_price'], 2); ?></span>
                                    <?php elseif (!empty($p['regular_price'])): ?>
                                        <span class="text-slate-900 font-bold">Rs. <?php echo number_format($p['regular_price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Stock</span>
                                    <?php if ($p['stock_status'] == 'instock'): ?>
                                        <span class="text-emerald-600 font-bold text-[11px]">In Stock <?php echo ($p['manage_stock'] == 1) ? '('.$p['stock_quantity'].')' : ''; ?></span>
                                    <?php else: ?>
                                        <span class="text-rose-500 font-bold text-[11px]">Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination Bar -->
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-between bg-white px-4 py-3 sm:px-6 rounded-2xl border border-slate-200/90 shadow-xs mb-8 flex-wrap gap-3">
                    <div class="text-xs text-slate-500">
                        Page <strong class="text-slate-900"><?php echo $current_page; ?></strong> of <strong class="text-slate-900"><?php echo $total_pages; ?></strong>
                    </div>

                    <div class="inline-flex items-center gap-1.5">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo ($current_page - 1); ?><?php echo !empty($base_query_string) ? '&' . $base_query_string : ''; ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                                &larr; Prev
                            </a>
                        <?php endif; ?>

                        <?php 
                        $start_p = max(1, $current_page - 2);
                        $end_p = min($total_pages, $current_page + 2);
                        for ($i = $start_p; $i <= $end_p; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($base_query_string) ? '&' . $base_query_string : ''; ?>" class="px-3 py-1.5 border rounded-lg text-xs font-bold transition <?php echo ($i === $current_page) ? 'bg-sky-700 text-white border-sky-700 shadow-xs' : 'border-slate-200 text-slate-700 hover:bg-slate-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo ($current_page + 1); ?><?php echo !empty($base_query_string) ? '&' . $base_query_string : ''; ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                                Next &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Hidden Form for Safe Deletion -->
    <form id="deleteForm" action="all_product_action.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_product">
        <input type="hidden" name="product_id" id="delete_id">
    </form>

    <script>
        // Sample CSV Generation
        const sampleCSVContent = "Name,SKU,Type,Regular Price,Sale Price,Status,Stock Status,Stock Qty\nMadol Doova,9789551234567,simple,1200,,published,instock,25\nGamperaliya,9789557654321,simple,1500,1350,published,instock,10\nViragaya Special,9789559876543,variable,2000,,draft,instock,0";
        const blob = new Blob([sampleCSVContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const downloadLink = document.querySelector('a[download="sample_import.csv"]');
        if (downloadLink) { downloadLink.href = url; }

        // SweetAlert Notifications
        <?php if ($message): ?>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?php echo $messageType; ?>',
                title: '<?php echo addslashes($message); ?>',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        <?php endif; ?>

        // Safe Delete Confirmation Modal
        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete this book title?',
                text: "This will remove the product, related reviews, variations, and image files.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }
        
        // Mobile Sidebar Drawer Toggle
        const headerOpenBtn = document.getElementById('mobile-menu-open-header');
        if (headerOpenBtn) {
            headerOpenBtn.addEventListener('click', function() {
                const actualOpenBtn = document.getElementById('mobile-menu-open');
                if (actualOpenBtn) actualOpenBtn.click();
            });
        }
    </script>
</body>
</html>