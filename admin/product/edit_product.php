<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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

// Product ID Validation
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$product_id) {
    header("Location: all_product.php");
    exit;
}

// Fetch Product Data
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: all_product.php");
    exit;
}

// Fetch Pending Pre-orders for this specific product
$pending_preorders_count = 0;
try {
    $po_stmt = $pdo->prepare("SELECT COUNT(*) FROM product_preorders WHERE product_id = ? AND status = 'pending'");
    $po_stmt->execute([$product_id]);
    $pending_preorders_count = (int)$po_stmt->fetchColumn();
} catch (Exception $e) {
    $pending_preorders_count = 0;
}

// Fetch Dropdown Data
$categories_raw = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
$attributes = $pdo->query("SELECT * FROM attributes ORDER BY name ASC")->fetchAll();
$attribute_terms = $pdo->query("SELECT * FROM attribute_terms ORDER BY name ASC")->fetchAll();
$all_products = $pdo->query("SELECT id, name FROM products WHERE id != $product_id ORDER BY name ASC")->fetchAll(); 

$shipping_classes = [];
try {
    $shipping_classes = $pdo->query("SELECT id, class_name, additional_cost FROM shipping_classes ORDER BY class_name ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch Selected Relational Data
$sel_cats = $pdo->prepare("SELECT category_id FROM product_categories WHERE product_id = ?");
$sel_cats->execute([$product_id]);
$selected_categories = $sel_cats->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Fetch Saved Attributes cleanly
$sel_attrs = $pdo->prepare("SELECT attribute_id, terms_json FROM product_attributes WHERE product_id = ?");
$sel_attrs->execute([$product_id]);
$selected_attributes = [];
foreach ($sel_attrs->fetchAll() as $row) { 
    $selected_attributes[$row['attribute_id']] = json_decode($row['terms_json'], true) ?: []; 
}

$sel_vars = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ?");
$sel_vars->execute([$product_id]);
$saved_variations = $sel_vars->fetchAll();

$saved_upsells = !empty($product['upsell_ids']) ? explode(',', $product['upsell_ids']) : [];
$saved_crosssells = !empty($product['crosssell_ids']) ? explode(',', $product['crosssell_ids']) : [];

$terms_by_attr = [];
foreach ($attribute_terms as $t) { 
    $terms_by_attr[$t['attribute_id']][] = $t; 
}

// Category Tree Logic
function buildCategoryTree($elements, $parentId = 0) {
    $branch = array();
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = buildCategoryTree($elements, $element['id']);
            if ($children) { $element['children'] = $children; }
            $branch[] = $element;
        }
    }
    return $branch;
}
$categoryTree = buildCategoryTree($categories_raw);

function renderCategories($tree, $selected_cats, $level = 0) {
    foreach ($tree as $cat) {
        $margin = $level * 1.25; 
        $checked = in_array($cat['id'], $selected_cats) ? 'checked' : '';
        echo '<label class="flex items-center gap-2 text-xs sm:text-sm text-slate-700 cursor-pointer hover:text-sky-700 py-1 font-medium transition-colors" style="margin-left: '.$margin.'rem;">';
        echo '<input type="checkbox" name="categories[]" value="'.(int)$cat['id'].'" '.$checked.' class="rounded border-slate-300 text-sky-700 focus:ring-sky-600 w-4 h-4">';
        echo ($level > 0 ? '<span class="text-slate-300 font-bold">—</span> ' : '') . htmlspecialchars($cat['name']);
        echo '</label>';
        if (isset($cat['children'])) { renderCategories($cat['children'], $selected_cats, $level + 1); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | 4MEN COLLECTION Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, input, textarea, select, button { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important; }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .select2-container--default .select2-selection--multiple { 
            background-color: #f8fafc; 
            border: 1px solid #e2e8f0; 
            border-radius: 0.75rem; 
            padding: 4px;
            font-size: 0.8125rem;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice { 
            background-color: #f1f5f9; 
            border: 1px solid #e2e8f0; 
            color: #0f172a; 
            border-radius: 6px; 
            font-weight: 600; 
            font-size: 12px;
            padding: 2px 8px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove { 
            color: #e11d48; 
            margin-right: 4px; 
            font-weight: bold;
        }
        .select2-dropdown { border: 1px solid #e2e8f0; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { 
            background-color: #000000; 
            color: white;
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-slate-800 antialiased">

    <?php 
    if (file_exists('../../sidebar.php')) { require_once '../../sidebar.php'; } 
    elseif (file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">
        
        <!-- Sticky Top Navigation Header -->
        <header class="bg-white/95 backdrop-blur-md shadow-xs border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3.5 flex justify-between items-center z-10 sticky top-0 gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <button id="mobile-menu-open-header" type="button" class="md:hidden p-2 text-slate-600 hover:text-sky-700 focus:outline-none rounded-xl hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="min-w-0">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Inventory Management</span>
                    <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 tracking-tight truncate">
                        Edit: <span class="text-sky-700"><?php echo htmlspecialchars($product['name']); ?></span>
                    </h1>
                </div>
            </div>
            
            <div class="flex items-center gap-2 shrink-0">
                <?php if ($pending_preorders_count > 0): ?>
                    <a href="/admin/orders/preorders.php" target="_blank" class="text-xs font-black text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-xl shadow-xs hidden sm:inline-flex items-center gap-1.5 animate-pulse">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span><?= $pending_preorders_count; ?> Pre-order<?= $pending_preorders_count > 1 ? 's' : ''; ?> Waiting</span>
                    </a>
                <?php endif; ?>

                <a href="/product/<?php echo $product['slug']; ?>" target="_blank" class="text-xs font-bold text-slate-700 hover:text-sky-700 px-3.5 py-2 border border-slate-200 rounded-xl bg-white hover:bg-slate-50 transition shadow-xs hidden sm:inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    <span>View Product</span>
                </a>
                <a href="all_product.php" class="text-xs font-bold text-slate-700 hover:text-slate-900 px-3.5 py-2 border border-slate-200 rounded-xl bg-white hover:bg-slate-50 transition shadow-xs">
                    &larr; Back
                </a>
            </div>
        </header>

        <!-- Main Form Scroll Area -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            
            <form action="edit_product_action.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                <!-- Left 8 Columns: Main Form Fields -->
                <div class="lg:col-span-8 space-y-6">
                    
                    <!-- Title & Slug -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Product Title *</label>
                        <input type="text" name="name" id="product_title" value="<?php echo htmlspecialchars($product['name']); ?>" required placeholder="e.g. Classic Oxford Cotton Shirt" class="w-full bg-slate-50 border border-slate-200 focus:bg-white text-lg sm:text-xl font-extrabold text-slate-900 placeholder-slate-400 rounded-xl px-4 py-2.5 focus:border-sky-700 outline-none transition-colors mb-2.5">
                        
                        <div class="flex flex-col sm:flex-row sm:items-center text-xs text-slate-500 font-medium gap-1 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                            <span class="shrink-0 font-bold text-slate-600">Permalink:</span>
                            <span class="truncate text-slate-400">/product/</span>
                            <input type="text" name="slug" id="product_slug" value="<?php echo htmlspecialchars($product['slug']); ?>" required class="bg-transparent border-none focus:ring-0 text-sky-700 p-0 font-mono font-bold text-xs w-full outline-none">
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Product Overview & Full Description</h3>
                        <textarea name="description" rows="7" placeholder="Detailed product description, fabric & material composition, fit & sizing guide, care instructions..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-xs sm:text-sm rounded-xl px-4 py-3 focus:bg-white focus:border-neutral-900 outline-none transition-colors"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <!-- Product Data Tabs Card -->
                    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden flex flex-col">
                        
                        <!-- Top Tab Bar Header -->
                        <div class="bg-slate-50 border-b border-slate-200 p-4 sm:p-5 flex flex-col sm:flex-row gap-3 sm:items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-neutral-900"></span>
                                <h3 class="text-sm font-black text-slate-900 tracking-tight">Product Specifications</h3>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                                <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none">
                                    <input type="checkbox" name="is_downloadable" id="is_downloadable" value="1" <?php echo $product['is_downloadable'] == 1 ? 'checked' : ''; ?> class="rounded border-slate-300 text-neutral-900 focus:ring-black w-4 h-4"> 
                                    <span>Digital Voucher / Downloadable</span>
                                </label>
                                
                                <select name="product_type" id="product_type" class="bg-white border border-slate-300 text-xs font-bold text-slate-800 rounded-xl px-3 py-2 outline-none focus:border-neutral-900 shadow-xs cursor-pointer">
                                    <option value="simple" <?php echo $product['type'] == 'simple' ? 'selected' : ''; ?>>Simple Product (Single Apparel Item)</option>
                                    <option value="variable" <?php echo $product['type'] == 'variable' ? 'selected' : ''; ?>>Variable Product (Sizes / Colors / Fits)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tabs Content Area -->
                        <div class="flex flex-col md:flex-row flex-1">
                            
                            <!-- Tab Navigation Buttons -->
                            <div class="w-full md:w-48 bg-slate-50/70 border-b md:border-b-0 md:border-r border-slate-200 flex flex-row md:flex-col overflow-x-auto md:overflow-x-visible shrink-0 p-2 md:p-2.5 gap-1">
                                <button type="button" class="tab-btn <?php echo $product['type'] == 'simple' ? 'active text-white bg-neutral-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'; ?> whitespace-nowrap text-left px-4 py-2.5 text-xs rounded-xl transition" data-target="tab-general">General Pricing</button>
                                <button type="button" class="tab-btn <?php echo $product['type'] == 'variable' ? 'active text-white bg-neutral-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'; ?> whitespace-nowrap text-left px-4 py-2.5 text-xs rounded-xl transition" data-target="tab-inventory">Inventory / SKU</button>
                                <button type="button" class="tab-btn text-slate-600 hover:text-slate-900 font-semibold whitespace-nowrap text-left px-4 py-2.5 text-xs rounded-xl transition" data-target="tab-shipping">Shipping & Parcel</button>
                                <button type="button" class="tab-btn text-slate-600 hover:text-slate-900 font-semibold whitespace-nowrap text-left px-4 py-2.5 text-xs rounded-xl transition" data-target="tab-linked">Linked Products</button>
                                <button type="button" class="tab-btn text-slate-600 hover:text-slate-900 font-semibold whitespace-nowrap text-left px-4 py-2.5 text-xs rounded-xl transition" data-target="tab-attributes">Attributes (Size/Color/Fabric)</button>
                                <button type="button" class="tab-btn <?php echo $product['type'] == 'variable' ? '' : 'hidden'; ?> text-slate-600 hover:text-slate-900 font-semibold whitespace-nowrap text-left px-4 py-2.5 text-xs rounded-xl transition" data-target="tab-variations" id="nav-variations">Variations</button>
                                <button type="button" class="tab-btn <?php echo $product['is_downloadable'] == 1 ? '' : 'hidden'; ?> text-slate-600 hover:text-slate-900 font-semibold whitespace-nowrap text-left px-4 py-2.5 text-xs rounded-xl transition" data-target="tab-download" id="nav-download">Digital Attachment</button>
                            </div>

                            <!-- Inner Panels -->
                            <div class="flex-1 p-5 sm:p-6 bg-white min-h-[320px]">
                                
                                <!-- 1. General Pricing -->
                                <div id="tab-general" class="tab-content <?php echo $product['type'] == 'variable' ? 'hidden' : 'block'; ?> space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">Regular Price (Rs.) *</label>
                                        <input type="number" step="0.01" name="regular_price" value="<?php echo htmlspecialchars($product['regular_price']); ?>" class="sm:col-span-2 bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none w-full sm:w-2/3">
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-start">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right mt-2">Sale Price (Rs.)</label>
                                        <div class="sm:col-span-2 w-full sm:w-2/3">
                                            <input type="number" step="0.01" name="sale_price" value="<?php echo htmlspecialchars($product['sale_price']); ?>" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none">
                                            <a href="#" id="schedule_btn" class="text-[11px] font-bold text-sky-700 hover:underline mt-1 inline-block"><?php echo ($product['sale_price_dates_from']) ? 'Cancel schedule' : 'Schedule Sale Duration'; ?></a>
                                            
                                            <div id="schedule_box" class="<?php echo ($product['sale_price_dates_from']) ? '' : 'hidden'; ?> mt-3 p-3.5 bg-sky-50/60 border border-sky-100 rounded-xl space-y-2.5">
                                                <div>
                                                    <label class="text-[10px] font-bold text-slate-600 block mb-1 uppercase">Sale Start Date</label>
                                                    <input type="date" name="sale_price_dates_from" value="<?php echo htmlspecialchars($product['sale_price_dates_from']); ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800">
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-bold text-slate-600 block mb-1 uppercase">Sale End Date</label>
                                                    <input type="date" name="sale_price_dates_to" value="<?php echo htmlspecialchars($product['sale_price_dates_to']); ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center border-t border-slate-100 pt-4 mt-2">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">Tax Status</label>
                                        <select name="tax_status" class="sm:col-span-2 bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none w-full sm:w-2/3">
                                            <option value="none" <?php echo $product['tax_status'] == 'none' ? 'selected' : ''; ?>>None</option>
                                            <option value="taxable" <?php echo $product['tax_status'] == 'taxable' ? 'selected' : ''; ?>>Taxable</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- 2. Inventory (With Pre-order Alert Box) -->
                                <div id="tab-inventory" class="tab-content <?php echo $product['type'] == 'variable' ? 'block' : 'hidden'; ?> space-y-4">
                                    
                                    <?php if ($pending_preorders_count > 0): ?>
                                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                                </div>
                                                <div>
                                                    <h4 class="text-xs font-black text-amber-900 uppercase tracking-wider"><?= $pending_preorders_count; ?> Pre-order Customer(s) Waiting</h4>
                                                    <p class="text-[11px] text-amber-700 mt-0.5">Increasing Stock Quantity above 0 will automatically dispatch restock notification emails.</p>
                                                </div>
                                            </div>
                                            <a href="/admin/orders/preorders.php" target="_blank" class="text-xs font-bold text-sky-800 bg-white hover:bg-sky-50 border border-slate-200 px-3 py-1.5 rounded-xl transition shadow-xs shrink-0 inline-flex items-center gap-1">
                                                <span>View Requests</span>
                                                <span>&rarr;</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">SKU / Product Code</label>
                                        <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" placeholder="e.g. 4M-SHIRT-001" class="sm:col-span-2 bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none w-full sm:w-2/3 font-mono">
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">Manage Stock?</label>
                                        <div class="sm:col-span-2 flex items-center">
                                            <input type="checkbox" id="manage_stock" name="manage_stock" value="1" <?php echo $product['manage_stock'] == 1 ? 'checked' : ''; ?> class="rounded border-slate-300 text-sky-700 focus:ring-sky-600 w-4 h-4 cursor-pointer">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center stock-fields <?php echo $product['manage_stock'] == 1 ? '' : 'hidden'; ?>">
                                        <label class="text-xs font-bold text-sky-700 sm:text-right">Stock Quantity</label>
                                        <input type="number" name="stock_quantity" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" class="sm:col-span-2 bg-sky-50/50 border border-sky-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none w-full sm:w-1/3">
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">Stock Status</label>
                                        <select name="stock_status" class="sm:col-span-2 bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none w-full sm:w-2/3">
                                            <option value="instock" <?php echo $product['stock_status'] == 'instock' ? 'selected' : ''; ?>>In stock</option>
                                            <option value="outofstock" <?php echo $product['stock_status'] == 'outofstock' ? 'selected' : ''; ?>>Out of stock</option>
                                            <option value="onbackorder" <?php echo $product['stock_status'] == 'onbackorder' ? 'selected' : ''; ?>>On backorder</option>
                                        </select>
                                    </div>

                                    <div class="border-t border-slate-100 pt-4 mt-2 space-y-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                            <label class="text-xs font-bold text-slate-700 sm:text-right">Min Purchase Qty</label>
                                            <input type="number" name="min_purchase_qty" value="<?php echo htmlspecialchars($product['min_purchase_qty'] ?? 1); ?>" min="1" class="sm:col-span-2 bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none w-full sm:w-1/3">
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                            <label class="text-xs font-bold text-slate-700 sm:text-right">Max Purchase Qty</label>
                                            <input type="number" name="max_purchase_qty" value="<?php echo htmlspecialchars($product['max_purchase_qty']); ?>" placeholder="Unlimited" class="sm:col-span-2 bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none w-full sm:w-1/3">
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Shipping -->
                                <div id="tab-shipping" class="tab-content hidden space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">Weight (kg)</label>
                                        <input type="number" step="0.01" name="weight" value="<?php echo htmlspecialchars($product['weight']); ?>" placeholder="e.g. 0.45" class="sm:col-span-2 bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none w-full sm:w-1/3">
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-start">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right mt-2">Dimensions (cm)</label>
                                        <div class="sm:col-span-2 grid grid-cols-3 gap-2 w-full sm:w-2/3">
                                            <input type="number" step="0.1" name="length" value="<?php echo htmlspecialchars($product['length']); ?>" placeholder="L" class="bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3 py-2 text-xs text-center focus:bg-white focus:border-sky-700 outline-none">
                                            <input type="number" step="0.1" name="width" value="<?php echo htmlspecialchars($product['width']); ?>" placeholder="W" class="bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3 py-2 text-xs text-center focus:bg-white focus:border-sky-700 outline-none">
                                            <input type="number" step="0.1" name="height" value="<?php echo htmlspecialchars($product['height']); ?>" placeholder="H" class="bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3 py-2 text-xs text-center focus:bg-white focus:border-sky-700 outline-none">
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center border-t border-slate-100 pt-4 mt-2">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">Shipping Class</label>
                                        <div class="sm:col-span-2 w-full sm:w-2/3">
                                            <select name="shipping_class_id" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none">
                                                <option value="">Standard Courier Rates</option>
                                                <?php if (!empty($shipping_classes)): ?>
                                                    <?php foreach ($shipping_classes as $sc): ?>
                                                        <option value="<?php echo $sc['id']; ?>" <?php echo (isset($product['shipping_class_id']) && $product['shipping_class_id'] == $sc['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($sc['class_name']); ?> (+Rs. <?php echo number_format($sc['additional_cost'], 2); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. Linked Products -->
                                <div id="tab-linked" class="tab-content hidden space-y-4">
                                    <div>
                                        <label class="text-xs font-bold text-slate-700 block mb-1.5">Upsells <span class="text-slate-400 font-normal">(Products recommended on Product Details page)</span></label>
                                        <select name="upsell_ids[]" class="select2-multiple w-full" multiple="multiple">
                                            <?php foreach ($all_products as $p): ?>
                                                <option value="<?php echo $p['id']; ?>" <?php echo in_array($p['id'], $saved_upsells) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs font-bold text-slate-700 block mb-1.5">Cross-sells <span class="text-slate-400 font-normal">(Products recommended inside Shopping Cart)</span></label>
                                        <select name="crosssell_ids[]" class="select2-multiple w-full" multiple="multiple">
                                            <?php foreach ($all_products as $p): ?>
                                                <option value="<?php echo $p['id']; ?>" <?php echo in_array($p['id'], $saved_crosssells) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- 5. Attributes (Size, Color, Fabric etc.) -->
                                <div id="tab-attributes" class="tab-content hidden space-y-4">
                                    <div class="bg-neutral-50 border border-neutral-200 p-3.5 rounded-2xl">
                                        <p class="text-xs font-bold text-neutral-900">Configure Clothing Attributes</p>
                                        <p class="text-[11px] text-neutral-600 mt-0.5">Select size(s), color(s), material(s) or custom terms below. Checked attributes will be displayed on the product page and used in shop filters.</p>
                                    </div>

                                    <?php foreach ($attributes as $attr): ?>
                                        <div class="border border-slate-200 rounded-2xl p-4 sm:p-5 bg-slate-50/50">
                                            <div class="flex items-center justify-between mb-3 border-b border-slate-200/80 pb-2">
                                                <h4 class="font-extrabold text-xs sm:text-sm text-slate-900 uppercase tracking-wide flex items-center gap-2">
                                                    <span class="w-2 h-2 rounded-full bg-sky-700"></span>
                                                    <?php echo htmlspecialchars($attr['name']); ?>
                                                </h4>
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?php echo htmlspecialchars($attr['type']); ?></span>
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <?php if (isset($terms_by_attr[$attr['id']])): ?>
                                                    <?php foreach ($terms_by_attr[$attr['id']] as $term): ?>
                                                        <?php 
                                                        $isChecked = isset($selected_attributes[$attr['id']]) && in_array($term['name'], $selected_attributes[$attr['id']]); 
                                                        ?>
                                                        <label class="flex items-center gap-2 text-xs font-semibold px-3 py-2 rounded-xl border cursor-pointer select-none transition-all <?php echo $isChecked ? 'bg-sky-50 text-sky-800 border-sky-300 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'; ?>">
                                                            <input 
                                                                type="checkbox" 
                                                                name="attributes[<?php echo $attr['id']; ?>][]" 
                                                                value="<?php echo htmlspecialchars($term['name']); ?>" 
                                                                <?php echo $isChecked ? 'checked' : ''; ?> 
                                                                class="rounded border-slate-300 text-sky-700 focus:ring-sky-600 attr-checkbox w-4 h-4 cursor-pointer" 
                                                                data-attr-name="<?php echo htmlspecialchars($attr['name']); ?>" 
                                                                data-term-name="<?php echo htmlspecialchars($term['name']); ?>"
                                                            >
                                                            <span><?php echo htmlspecialchars($term['name']); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-xs text-slate-400 italic">No terms added to this attribute.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- 6. Variations -->
                                <div id="tab-variations" class="tab-content hidden space-y-4">
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 pb-4 border-b border-slate-200">
                                        <div>
                                            <h4 class="text-xs font-black text-slate-900 uppercase">Product Variations</h4>
                                            <p class="text-[11px] text-slate-500">Add custom prices & stock for each edition or format.</p>
                                        </div>
                                        <button type="button" id="btn-generate-variations" class="bg-sky-50 text-sky-800 border border-sky-200 hover:bg-sky-700 hover:text-white font-bold text-xs px-4 py-2 rounded-xl transition shadow-xs">
                                            Regenerate Variations
                                        </button>
                                    </div>

                                    <div id="variations-container" class="space-y-3.5 mt-3">
                                        <?php if (!empty($saved_variations)): ?>
                                            <?php foreach ($saved_variations as $var): ?>
                                                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 shadow-xs">
                                                    <div class="flex justify-between items-center mb-3 border-b border-slate-200 pb-2">
                                                        <h4 class="font-extrabold text-xs sm:text-sm text-sky-800"><?php echo htmlspecialchars($var['variation_name']); ?></h4>
                                                        <input type="hidden" name="var_id[]" value="<?php echo $var['id']; ?>">
                                                        <input type="hidden" name="var_name[]" value="<?php echo htmlspecialchars($var['variation_name']); ?>">
                                                        <input type="hidden" name="existing_var_image[]" value="<?php echo htmlspecialchars($var['image']); ?>">
                                                    </div>
                                                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 items-end">
                                                        <div class="col-span-2 sm:col-span-1">
                                                            <?php if ($var['image']): ?>
                                                                <img src="/<?php echo htmlspecialchars($var['image']); ?>" class="h-10 w-10 object-cover rounded-lg border border-slate-200 shadow-xs mb-1">
                                                            <?php endif; ?>
                                                            <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Image</label>
                                                            <input type="file" name="var_image[]" accept="image/*" class="w-full text-[10px] text-slate-500 bg-white border border-slate-200 rounded-lg p-1">
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">SKU</label>
                                                            <input type="text" name="var_sku[]" value="<?php echo htmlspecialchars($var['sku']); ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-sky-700 font-mono">
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] font-bold text-sky-700 uppercase block mb-1">Reg. Price *</label>
                                                            <input type="number" step="0.01" name="var_regular_price[]" value="<?php echo htmlspecialchars($var['regular_price']); ?>" required class="w-full bg-white border border-sky-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 outline-none focus:border-sky-700 font-bold">
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Sale Price</label>
                                                            <input type="number" step="0.01" name="var_sale_price[]" value="<?php echo htmlspecialchars($var['sale_price']); ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs">
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Stock Qty</label>
                                                            <input type="number" name="var_stock[]" value="<?php echo htmlspecialchars($var['stock_quantity']); ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs">
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-slate-400 text-xs text-center py-8 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50 font-medium">No variations configured yet.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- 7. Downloadable -->
                                <div id="tab-download" class="tab-content hidden space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">Attachment / Voucher File</label>
                                        <div class="sm:col-span-2">
                                            <?php if ($product['download_file']): ?>
                                                <p class="text-xs font-bold text-neutral-800 mb-2 truncate bg-neutral-100 p-2 rounded-xl border border-neutral-200">Current file: <?php echo basename($product['download_file']); ?></p>
                                            <?php endif; ?>
                                            <input type="hidden" name="existing_download_file" value="<?php echo htmlspecialchars($product['download_file']); ?>">
                                            <input type="file" name="download_file" accept=".pdf,.epub,.zip" class="text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-xl w-full sm:w-2/3 p-1 cursor-pointer">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 items-center">
                                        <label class="text-xs font-bold text-slate-700 sm:text-right">Download Limit</label>
                                        <input type="number" name="download_limit" value="<?php echo htmlspecialchars($product['download_limit']); ?>" placeholder="Leave blank for unlimited" class="sm:col-span-2 bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-neutral-900 outline-none w-full sm:w-1/3">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Short Description -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Short Description / Highlights</h3>
                        <textarea name="short_description" rows="3" placeholder="Brief summary for product card display (e.g. Premium 100% breathable cotton, slim-fit silhouette, easy iron)..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-xs sm:text-sm rounded-xl px-4 py-3 focus:bg-white focus:border-neutral-900 outline-none transition-colors"><?php echo htmlspecialchars($product['short_description']); ?></textarea>
                    </div>

                    <!-- SEO Settings -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <div class="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                            <svg class="w-4 h-4 text-neutral-900 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">Search Engine Optimization (SEO)</h3>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1">Focus Keywords</label>
                                <input type="text" name="focus_keywords" value="<?php echo htmlspecialchars($product['focus_keywords']); ?>" placeholder="e.g. mens shirt, slim fit blazer, cotton chinos, formal wear" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-neutral-900 outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1">Meta Title</label>
                                <input type="text" name="meta_title" value="<?php echo htmlspecialchars($product['meta_title']); ?>" id="seo_title_input" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1">Meta Description</label>
                                <textarea name="meta_description" id="seo_desc_input" rows="2" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none"><?php echo htmlspecialchars($product['meta_description']); ?></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right 4 Columns: Side Controls & Image Upload -->
                <div class="lg:col-span-4 space-y-6">
                    
                    <!-- Publish Box -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3 border-b border-slate-100 pb-2.5">Publish Status</h3>
                        
                        <div class="flex items-center justify-between text-xs mb-3">
                            <span class="text-slate-500 font-bold">Current Status:</span>
                            <span class="font-extrabold uppercase px-2 py-0.5 rounded-md <?php echo $product['status'] === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'; ?>">
                                <?php echo htmlspecialchars($product['status']); ?>
                            </span>
                        </div>

                        <div class="mb-4">
                            <label class="text-[11px] font-bold text-slate-600 block mb-1">Publish Timestamp</label>
                            <input type="datetime-local" name="publish_date" value="<?php echo date('Y-m-d\TH:i', strtotime($product['publish_date'] ?? date('Y-m-d H:i:s'))); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 outline-none focus:border-sky-700">
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 pt-2 border-t border-slate-100">
                            <button type="submit" name="save_draft" class="bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition">Draft</button>
                            <button type="submit" name="publish" class="bg-neutral-900 hover:bg-black text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition shadow-sm">Update</button>
                        </div>
                    </div>

                    <!-- Categories -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3 border-b border-slate-100 pb-2.5">Product Categories</h3>
                        <div class="max-h-60 overflow-y-auto pr-1 bg-slate-50/70 p-3 rounded-2xl border border-slate-200">
                            <?php renderCategories($categoryTree, $selected_categories); ?>
                        </div>
                    </div>

                    <!-- Brand -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3 border-b border-slate-100 pb-2.5">Brand</h3>
                        <select name="brand_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 font-medium rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-neutral-900 outline-none cursor-pointer">
                            <option value="">Select Brand...</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>" <?php echo $product['brand_id'] == $brand['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Product Tags -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3 border-b border-slate-100 pb-2.5">Tags</h3>
                        <input type="text" name="tags" value="<?php echo htmlspecialchars($product['tags']); ?>" placeholder="Shirts, Formal Wear, Slim Fit, Casual, Luxury" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-neutral-900 outline-none">
                        <p class="text-[10px] text-slate-400 mt-1.5">Separate with commas.</p>
                    </div>

                    <!-- Main Product Image with Removable Support -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-2 border-b border-slate-100 pb-2">Main Product Image *</h3>
                        
                        <input type="hidden" id="existing_main_image" name="existing_main_image" value="<?php echo htmlspecialchars($product['main_image'] ?? ''); ?>">
                        <input type="hidden" id="remove_main_image" name="remove_main_image" value="0">

                        <div id="main_image_dropzone" class="<?php echo !empty($product['main_image']) ? 'hidden' : ''; ?> relative border-2 border-dashed border-slate-200 hover:border-neutral-900 rounded-2xl p-4 text-center bg-slate-50/50 hover:bg-neutral-50 transition-all cursor-pointer">
                            <input type="file" id="main_image_input" name="main_image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <div class="flex flex-col items-center justify-center py-2">
                                <svg class="w-8 h-8 text-neutral-800 mb-2 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                <span class="text-xs font-bold text-slate-700">Change Product Image</span>
                                <span class="text-[10px] text-slate-400 mt-0.5">JPEG, PNG, or WEBP (3:4 or square recommended)</span>
                            </div>
                        </div>

                        <div id="main_image_preview_container" class="<?php echo empty($product['main_image']) ? 'hidden' : ''; ?> relative text-center bg-slate-50 p-2.5 rounded-2xl border border-slate-200">
                            <img id="main_image_preview" src="<?php echo !empty($product['main_image']) ? '/' . htmlspecialchars($product['main_image']) : ''; ?>" alt="Product Preview" class="w-28 sm:w-32 aspect-[3/4] object-cover rounded-xl border border-slate-300 mx-auto shadow-xs">
                            <button type="button" id="remove_main_image_btn" class="absolute top-2 right-2 bg-rose-600 hover:bg-rose-700 text-white rounded-full p-1.5 shadow-md transition-transform active:scale-95" title="Remove product image">
                                <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            <span class="text-[10px] font-bold text-slate-500 mt-2 block truncate px-2" id="main_image_name"><?php echo !empty($product['main_image']) ? basename($product['main_image']) : ''; ?></span>
                        </div>

                        <!-- Gallery Extra Images -->
                        <div class="border-t border-slate-100 pt-4 mt-5">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Gallery Extra Images</h3>
                                <span class="text-[10px] text-slate-400 font-semibold" id="gallery_count_text"></span>
                            </div>

                            <div class="relative border-2 border-dashed border-slate-200 hover:border-sky-700 rounded-2xl p-3 text-center bg-slate-50/50 hover:bg-sky-50/30 transition-all cursor-pointer mb-3">
                                <input type="file" id="gallery_input" name="gallery_images[]" accept="image/*" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                <div class="flex items-center justify-center gap-2 py-1">
                                    <svg class="w-4 h-4 text-sky-700 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    <span class="text-xs font-bold text-slate-700">Add Extra Images</span>
                                </div>
                            </div>

                            <div id="gallery_preview_container" class="flex flex-wrap gap-2.5">
                                <?php 
                                $saved_gallery = !empty($product['gallery']) ? json_decode($product['gallery'], true) : []; 
                                if (is_array($saved_gallery)):
                                    foreach ($saved_gallery as $g_img):
                                ?>
                                    <div class="existing-gallery-item relative group w-16 aspect-[2/3] rounded-xl overflow-hidden border border-slate-200 shadow-xs bg-slate-100 shrink-0">
                                        <input type="hidden" name="retained_gallery[]" value="<?php echo htmlspecialchars($g_img); ?>">
                                        <img src="/<?php echo htmlspecialchars($g_img); ?>" class="w-full h-full object-cover">
                                        <button type="button" onclick="removeExistingGalleryItem(this)" class="absolute top-1 right-1 w-5 h-5 bg-rose-600 hover:bg-rose-700 text-white rounded-full flex items-center justify-center shadow-md transition-transform active:scale-95" title="Remove this photo">
                                            <svg class="w-3 h-3 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        </div>

                    </div>

                </div>
            </form>

        </div>
    </main>

    <script>
        // SweetAlert Flash
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

        $(document).ready(function() {
            $('.select2-multiple').select2({
                placeholder: "Search and select products...",
                allowClear: true,
                width: '100%' 
            });
        });

        // Cover Image Preview & Removal Logic
        const mainInput = document.getElementById('main_image_input');
        const mainDropzone = document.getElementById('main_image_dropzone');
        const mainPreviewContainer = document.getElementById('main_image_preview_container');
        const mainPreviewImg = document.getElementById('main_image_preview');
        const mainNameSpan = document.getElementById('main_image_name');
        const removeMainBtn = document.getElementById('remove_main_image_btn');
        const existingMainHidden = document.getElementById('existing_main_image');
        const removeMainHidden = document.getElementById('remove_main_image');

        mainInput?.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    mainPreviewImg.src = e.target.result;
                    mainNameSpan.innerText = file.name;
                    mainDropzone.classList.add('hidden');
                    mainPreviewContainer.classList.remove('hidden');
                    removeMainHidden.value = '0';
                };
                reader.readAsDataURL(file);
            }
        });

        removeMainBtn?.addEventListener('click', function(e) {
            e.stopPropagation();
            mainInput.value = '';
            mainPreviewImg.src = '';
            mainNameSpan.innerText = '';
            existingMainHidden.value = '';
            removeMainHidden.value = '1';
            mainPreviewContainer.classList.add('hidden');
            mainDropzone.classList.remove('hidden');
        });

        // Gallery Images (Retained & New Individual Removal)
        let galleryDT = new DataTransfer();
        const galleryInput = document.getElementById('gallery_input');
        const galleryContainer = document.getElementById('gallery_preview_container');
        const galleryCountText = document.getElementById('gallery_count_text');

        function updateGalleryCount() {
            const existingCount = document.querySelectorAll('.existing-gallery-item').length;
            const newCount = galleryDT.files.length;
            const total = existingCount + newCount;
            if (galleryCountText) {
                galleryCountText.innerText = `${total} image${total === 1 ? '' : 's'} (${existingCount} current, ${newCount} new)`;
            }
        }
        updateGalleryCount();

        window.removeExistingGalleryItem = function(btn) {
            const card = btn.closest('.existing-gallery-item');
            if (card) {
                card.remove();
                updateGalleryCount();
            }
        };

        galleryInput?.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                Array.from(this.files).forEach(file => {
                    galleryDT.items.add(file);
                });
                galleryInput.files = galleryDT.files;
                renderNewGalleryPreviews();
            }
        });

        function renderNewGalleryPreviews() {
            document.querySelectorAll('.new-gallery-item').forEach(el => el.remove());

            Array.from(galleryDT.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const card = document.createElement('div');
                    card.className = "new-gallery-item relative group w-16 aspect-[2/3] rounded-xl overflow-hidden border-2 border-sky-400 shadow-xs bg-slate-100 shrink-0";
                    card.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover">
                        <button type="button" onclick="removeNewGalleryItem(${index})" class="absolute top-1 right-1 w-5 h-5 bg-rose-600 hover:bg-rose-700 text-white rounded-full flex items-center justify-center shadow-md transition-transform active:scale-95" title="Remove this photo">
                            <svg class="w-3 h-3 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    `;
                    galleryContainer.appendChild(card);
                };
                reader.readAsDataURL(file);
            });
            updateGalleryCount();
        }

        window.removeNewGalleryItem = function(indexToRemove) {
            const newDT = new DataTransfer();
            Array.from(galleryDT.files).forEach((file, idx) => {
                if (idx !== indexToRemove) {
                    newDT.items.add(file);
                }
            });
            galleryDT = newDT;
            galleryInput.files = galleryDT.files;
            renderNewGalleryPreviews();
        };

        // Sale Schedule Toggle
        document.getElementById('schedule_btn')?.addEventListener('click', function(e) {
            e.preventDefault();
            const box = document.getElementById('schedule_box');
            if (box.classList.contains('hidden')) {
                box.classList.remove('hidden');
                this.innerText = 'Cancel schedule';
            } else {
                box.classList.add('hidden');
                this.innerText = 'Schedule Sale Duration';
                document.querySelector('input[name="sale_price_dates_from"]').value = '';
                document.querySelector('input[name="sale_price_dates_to"]').value = '';
            }
        });

        // Slug Auto-generator
        document.getElementById('product_title')?.addEventListener('keyup', function() {
            let slug = this.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            document.getElementById('product_slug').value = slug;
        });

        // Tabs Logic
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                tabContents.forEach(c => c.classList.add('hidden'));
                tabBtns.forEach(b => {
                    b.classList.remove('active', 'text-sky-700', 'bg-white', 'shadow-xs', 'font-black');
                    b.classList.add('text-slate-600', 'font-semibold');
                });
                
                const targetId = this.getAttribute('data-target');
                const targetEl = document.getElementById(targetId);
                if (targetEl) targetEl.classList.remove('hidden');

                this.classList.remove('text-slate-600', 'font-semibold');
                this.classList.add('active', 'text-sky-700', 'bg-white', 'shadow-xs', 'font-black');
            });
        });

        // Product Type Toggle
        const productType = document.getElementById('product_type');
        const navVariations = document.getElementById('nav-variations');
        const tabGeneral = document.querySelector('[data-target="tab-general"]');
        const navDownload = document.getElementById('nav-download');
        const isDownloadable = document.getElementById('is_downloadable');

        function updateUI() {
            if (productType.value === 'variable') {
                navVariations.classList.remove('hidden');
                tabGeneral.classList.add('hidden');
                document.querySelector('[data-target="tab-inventory"]')?.click(); 
            } else {
                navVariations.classList.add('hidden');
                tabGeneral.classList.remove('hidden');
            }

            if (isDownloadable.checked) navDownload.classList.remove('hidden');
            else navDownload.classList.add('hidden');
        }

        productType?.addEventListener('change', updateUI);
        isDownloadable?.addEventListener('change', updateUI);

        // Stock Toggle
        const manageStock = document.getElementById('manage_stock');
        const stockFields = document.querySelector('.stock-fields');
        manageStock?.addEventListener('change', function() {
            if (this.checked) stockFields.classList.remove('hidden');
            else stockFields.classList.add('hidden');
        });

        // Variations Generator
        document.getElementById('btn-generate-variations')?.addEventListener('click', function() {
            if (!confirm("Regenerating variations will clear unsaved variation items. Continue?")) return;

            const container = document.getElementById('variations-container');
            container.innerHTML = ''; 
            
            let selectedAttributes = {};
            document.querySelectorAll('.attr-checkbox:checked').forEach(cb => {
                let attrName = cb.getAttribute('data-attr-name');
                let termName = cb.getAttribute('data-term-name');
                if (!selectedAttributes[attrName]) selectedAttributes[attrName] = [];
                selectedAttributes[attrName].push(termName);
            });

            let attrKeys = Object.keys(selectedAttributes);
            if (attrKeys.length === 0) {
                container.innerHTML = '<p class="text-rose-600 font-bold bg-rose-50 p-3 rounded-xl border border-rose-200 text-xs">Please select terms in the Attributes tab first.</p>';
                return;
            }

            const cartesian = (...a) => a.reduce((a, b) => a.flatMap(d => b.map(e => [d, e].flat())));
            let arraysToCombine = Object.values(selectedAttributes);
            let combinations = arraysToCombine.length > 1 ? cartesian(...arraysToCombine) : arraysToCombine[0].map(item => [item]);

            combinations.forEach((combo) => {
                let comboName = Array.isArray(combo) ? combo.join(' - ') : combo;
                
                let html = `
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 shadow-xs">
                        <div class="flex justify-between items-center mb-3 border-b border-slate-200 pb-2">
                            <h4 class="font-extrabold text-xs sm:text-sm text-sky-800">${comboName}</h4>
                            <input type="hidden" name="var_id[]" value=""> 
                            <input type="hidden" name="var_name[]" value="${comboName}">
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 items-end">
                            <div class="col-span-2 sm:col-span-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Image</label>
                                <input type="file" name="var_image[]" accept="image/*" class="w-full text-[10px] text-slate-500 bg-white border border-slate-200 rounded-lg p-1">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">SKU</label>
                                <input type="text" name="var_sku[]" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-sky-700 uppercase block mb-1">Reg. Price *</label>
                                <input type="number" step="0.01" name="var_regular_price[]" required class="w-full bg-white border border-sky-300 rounded-lg px-2.5 py-1.5 text-xs font-bold">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Sale Price</label>
                                <input type="number" step="0.01" name="var_sale_price[]" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Stock Qty</label>
                                <input type="number" name="var_stock[]" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs">
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', html);
            });
        });
    </script>
</body>
</html>