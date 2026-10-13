<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'shop_admin'])) {
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
    unset($_SESSION['swal_msg'], $_SESSION['swal_type']);
}

// ==========================================
// FILTERING & REPORTING LOGIC
// ==========================================
$where_clauses = ["1=1"];
$params = [];

// 1. Search Filter
$search = trim(htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'));
if (!empty($search)) {
    $where_clauses[] = "(o.order_number LIKE ? OR o.customer_first_name LIKE ? OR o.customer_last_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

// 2. Status Filter
$filter_status = htmlspecialchars($_GET['status'] ?? '', ENT_QUOTES, 'UTF-8');
if (!empty($filter_status)) {
    $where_clauses[] = "o.order_status = ?";
    $params[] = $filter_status;
}

// 3. Date Filter
$date_preset = htmlspecialchars($_GET['date_preset'] ?? 'all', ENT_QUOTES, 'UTF-8');
$date_from = htmlspecialchars($_GET['date_from'] ?? '', ENT_QUOTES, 'UTF-8');
$date_to = htmlspecialchars($_GET['date_to'] ?? '', ENT_QUOTES, 'UTF-8');

if ($date_preset === 'today') {
    $where_clauses[] = "DATE(o.created_at) = CURDATE()";
} elseif ($date_preset === 'this_month') {
    $where_clauses[] = "YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
} elseif ($date_preset === 'last_month') {
    $where_clauses[] = "YEAR(o.created_at) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(o.created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)";
} elseif ($date_preset === 'this_year') {
    $where_clauses[] = "YEAR(o.created_at) = YEAR(CURDATE())";
} elseif ($date_preset === 'custom' && !empty($date_from) && !empty($date_to)) {
    $where_clauses[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

$where_sql = implode(' AND ', $where_clauses);

// ==========================================
// FULL EXCEL (CSV) EXPORT LOGIC
// ==========================================
if (isset($_GET['export_excel'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=readershub_orders_' . date('Y-m-d_His') . '.csv');
    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'Order Number',
        'Date & Time',
        'Status',
        'Customer Name',
        'Email',
        'Phone',
        'Delivery Address',
        'Town / City',
        'District',
        'Province',
        'Postal Code',
        'Items (Name x Qty @ Price)',
        'Items Subtotal (Rs.)',
        'Shipping Fee (Rs.)',
        'Total Due (Rs.)',
        'Payment Method',
        'Courier Service',
        'Tracking Number',
        'Customer Notes'
    ]);

    $exp_sql = "
        SELECT o.*, 
               (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ' @ ', oi.price, ')') SEPARATOR ' | ') 
                FROM order_items oi WHERE oi.order_id = o.id) as item_details
        FROM orders o 
        WHERE $where_sql 
        ORDER BY o.created_at DESC
    ";
    $exp_stmt = $pdo->prepare($exp_sql);
    $exp_stmt->execute($params);

    while ($row = $exp_stmt->fetch(PDO::FETCH_ASSOC)) {
        $fullName = trim($row['customer_first_name'] . ' ' . $row['customer_last_name']);
        fputcsv($output, [
            $row['order_number'],
            date('Y-m-d H:i', strtotime($row['created_at'])),
            strtoupper($row['order_status']),
            $fullName,
            $row['customer_email'],
            $row['customer_phone'],
            $row['billing_address'],
            $row['billing_town'] ?? '',
            $row['billing_district'] ?? '',
            $row['billing_province'] ?? '',
            $row['billing_zip_code'] ?? '',
            $row['item_details'] ?? 'No items recorded',
            number_format((float)$row['subtotal'], 2, '.', ''),
            number_format((float)$row['shipping_cost'], 2, '.', ''),
            number_format((float)$row['total_amount'], 2, '.', ''),
            $row['payment_method'],
            $row['tracking_provider'] ?? '',
            $row['tracking_number'] ?? '',
            $row['order_notes'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// --- FETCH REPORTING METRICS ---
$rev_sql = "SELECT SUM(o.total_amount) FROM orders o WHERE o.order_status = 'completed' AND $where_sql";
$stmt_rev = $pdo->prepare($rev_sql);
$stmt_rev->execute($params);
$report_revenue = (float)$stmt_rev->fetchColumn();

$count_sql = "SELECT COUNT(*) FROM orders o WHERE $where_sql";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$report_total_orders = (int)$stmt_count->fetchColumn();

$comp_sql = "SELECT COUNT(*) FROM orders o WHERE o.order_status = 'completed' AND $where_sql";
$stmt_comp = $pdo->prepare($comp_sql);
$stmt_comp->execute($params);
$report_completed = (int)$stmt_comp->fetchColumn();

$pend_sql = "SELECT COUNT(*) FROM orders o WHERE o.order_status IN ('pending', 'processing') AND $where_sql";
$stmt_pend = $pdo->prepare($pend_sql);
$stmt_pend->execute($params);
$report_pending = (int)$stmt_pend->fetchColumn();

// ==========================================
// PAGINATION LOGIC (20 ORDERS PER PAGE)
// ==========================================
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = ceil($report_total_orders / $per_page);
if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

// --- FETCH ORDERS LIST WITH PAGINATION ---
$sql = "SELECT o.id, o.order_number, o.customer_first_name, o.customer_last_name, o.customer_email, o.customer_phone, o.total_amount, o.order_status, o.payment_method, o.created_at 
        FROM orders o 
        WHERE $where_sql 
        ORDER BY o.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusStyle($status) {
    switch ($status) {
        case 'pending': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'processing': return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'ready': 
        case 'packed': return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        case 'shipped': return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'completed': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'cancelled': 
        case 'refunded': return 'bg-rose-50 text-rose-700 border-rose-200';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}

// Build URL for Excel export
$export_query_params = $_GET;
$export_query_params['export_excel'] = '1';
$export_url = 'orders.php?' . http_build_query($export_query_params);

function getPaginationUrl($target_page) {
    $params = $_GET;
    $params['page'] = $target_page;
    return 'orders.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders & Reports | ReadersHub Admin</title>
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
    if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">

        <!-- Header -->
        <header class="bg-white/95 backdrop-blur-md shadow-xs border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3.5 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-open-header" type="button" class="md:hidden p-2 text-slate-600 hover:text-sky-700 focus:outline-none rounded-xl hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Sales Management</span>
                    <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 tracking-tight">Orders & Dispatch Reports</h1>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <!-- Export Excel / CSV Button -->
                <a href="<?= htmlspecialchars($export_url); ?>" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 hover:border-sky-700 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5 active:scale-98">
                    <svg class="w-4 h-4 text-emerald-600 stroke-[2.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export Excel (CSV)</span>
                </a>

                <!-- Create Order Button -->
                <a href="create_order.php" class="bg-sky-700 hover:bg-sky-800 text-white px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-md shadow-sky-700/20 flex items-center gap-1.5 active:scale-98">
                    <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Order</span>
                </a>
            </div>
        </header>

        <!-- Main Full Width Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 xl:p-10">
            <div class="w-full space-y-6">

                <!-- 4 Metrics Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-5">
                    <div class="bg-white p-4 sm:p-5 rounded-3xl shadow-xs border border-slate-200/90">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block mb-1">Delivered Revenue</span>
                        <h3 class="text-xl sm:text-2xl font-black text-sky-700">Rs. <?= number_format($report_revenue, 2); ?></h3>
                        <p class="text-[10px] text-slate-400 mt-1 font-medium">Completed orders within range</p>
                    </div>

                    <div class="bg-white p-4 sm:p-5 rounded-3xl shadow-xs border border-slate-200/90">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block mb-1">Total Invoices</span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900"><?= number_format($report_total_orders); ?></h3>
                        <p class="text-[10px] text-slate-400 mt-1 font-medium">All filtered customer orders</p>
                    </div>

                    <div class="bg-white p-4 sm:p-5 rounded-3xl shadow-xs border border-slate-200/90">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block mb-1">Completed</span>
                        <h3 class="text-xl sm:text-2xl font-black text-emerald-600"><?= number_format($report_completed); ?></h3>
                        <p class="text-[10px] text-slate-400 mt-1 font-medium">Delivered to customers</p>
                    </div>

                    <div class="bg-white p-4 sm:p-5 rounded-3xl shadow-xs border border-slate-200/90">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block mb-1">Pending / Processing</span>
                        <h3 class="text-xl sm:text-2xl font-black text-amber-500"><?= number_format($report_pending); ?></h3>
                        <p class="text-[10px] text-slate-400 mt-1 font-medium">Needs packing & courier</p>
                    </div>
                </div>

                <!-- Filter & Date Range Bar -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-4 sm:p-6">
                    <form action="orders.php" method="GET" class="flex flex-col xl:flex-row gap-3">

                        <!-- Search Box -->
                        <div class="flex-1">
                            <div class="relative">
                                <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search by Order ID, Name, Phone, or Email..." class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl pl-10 pr-4 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none transition-colors">
                            </div>
                        </div>

                        <!-- Dropdowns & Date Presets -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <select name="status" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-3 py-2 text-xs font-semibold focus:bg-white focus:border-sky-700 outline-none cursor-pointer">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?= $filter_status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="ready" <?= $filter_status == 'ready' ? 'selected' : ''; ?>>Ready to Ship</option>
                                <option value="packed" <?= $filter_status == 'packed' ? 'selected' : ''; ?>>Packed</option>
                                <option value="shipped" <?= $filter_status == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="completed" <?= $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="refunded" <?= $filter_status == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>

                            <select name="date_preset" id="date_preset" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-3 py-2 text-xs font-semibold focus:bg-white focus:border-sky-700 outline-none cursor-pointer">
                                <option value="all" <?= $date_preset == 'all' ? 'selected' : ''; ?>>All Time</option>
                                <option value="today" <?= $date_preset == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="this_month" <?= $date_preset == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="last_month" <?= $date_preset == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                <option value="this_year" <?= $date_preset == 'this_year' ? 'selected' : ''; ?>>This Year</option>
                                <option value="custom" <?= $date_preset == 'custom' ? 'selected' : ''; ?>>Custom Date Range...</option>
                            </select>

                            <div id="custom_dates" class="flex items-center gap-1.5 <?= $date_preset == 'custom' ? '' : 'hidden'; ?>">
                                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from); ?>" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-2.5 py-1.5 text-xs outline-none focus:border-sky-700">
                                <span class="text-slate-400 text-[10px] font-bold">TO</span>
                                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to); ?>" class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl px-2.5 py-1.5 text-xs outline-none focus:border-sky-700">
                            </div>

                            <button type="submit" class="bg-sky-700 hover:bg-sky-800 text-white px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-sm active:scale-98">
                                Filter
                            </button>

                            <?php if(!empty($_GET)): ?>
                                <a href="orders.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 border border-slate-200 rounded-xl text-xs font-bold transition">Clear</a>
                            <?php endif; ?>
                        </div>

                    </form>
                </div>

                <!-- Desktop Table View (>= 1024px) -->
                <div class="hidden lg:block bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-400 text-[10px] uppercase tracking-widest font-extrabold">
                                <th class="px-6 py-4">Order ID & Date</th>
                                <th class="px-6 py-4">Customer</th>
                                <th class="px-6 py-4">Total & Payment</th>
                                <th class="px-6 py-4 text-center">Status & Notify</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <?php if(empty($orders)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center text-slate-400">
                                        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                            <svg class="w-6 h-6 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        </div>
                                        <p class="font-bold text-slate-800 text-sm mb-0.5">No orders found</p>
                                        <p class="text-xs text-slate-400">Try choosing a different date range or status.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($orders as $o): ?>
                                <tr class="hover:bg-slate-50/60 transition group">
                                    <td class="px-6 py-4">
                                        <a href="view_order.php?id=<?= $o['id']; ?>" class="font-mono font-extrabold text-sky-700 hover:underline text-sm block">
                                            #<?= htmlspecialchars($o['order_number']); ?>
                                        </a>
                                        <span class="text-[11px] text-slate-400 font-medium">
                                            <?= date('M d, Y • h:i A', strtotime($o['created_at'])); ?>
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <p class="font-extrabold text-slate-900 text-sm"><?= htmlspecialchars($o['customer_first_name'] . ' ' . $o['customer_last_name']); ?></p>
                                        <p class="text-[11px] text-slate-400"><?= htmlspecialchars($o['customer_email']); ?> | <?= htmlspecialchars($o['customer_phone'] ?? ''); ?></p>
                                    </td>

                                    <td class="px-6 py-4">
                                        <p class="font-black text-slate-900 text-sm">Rs. <?= number_format($o['total_amount'], 2); ?></p>
                                        <span class="inline-block mt-0.5 px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-[9px] font-bold text-slate-600 uppercase tracking-wider">
                                            <?= htmlspecialchars($o['payment_method']); ?>
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <form action="orders_action.php?<?= http_build_query($_GET); ?>" method="POST" class="inline-block">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?= $o['id']; ?>">
                                            <select name="order_status" onchange="this.form.submit()" class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full border outline-none cursor-pointer appearance-none text-center shadow-xs transition <?= getStatusStyle($o['order_status']); ?>" title="Changing status will automatically send Zoho email notification">
                                                <option value="pending" <?= $o['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?= $o['order_status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="ready" <?= $o['order_status'] == 'ready' ? 'selected' : ''; ?>>Ready</option>
                                                <option value="packed" <?= $o['order_status'] == 'packed' ? 'selected' : ''; ?>>Packed</option>
                                                <option value="shipped" <?= $o['order_status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="completed" <?= $o['order_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?= $o['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="refunded" <?= $o['order_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                            </select>
                                        </form>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                            <a href="view_order.php?id=<?= $o['id']; ?>" class="inline-flex items-center gap-1 text-xs bg-slate-50 hover:bg-sky-50 text-sky-700 hover:text-sky-900 border border-slate-200 font-bold px-3 py-1.5 rounded-xl transition shadow-xs">
                                                <span>Manage</span>
                                                <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                            </a>
                                            <!-- Delete Order Button -->
                                            <button type="button" onclick="confirmDeleteOrder(<?= $o['id']; ?>, '<?= htmlspecialchars($o['order_number'], ENT_QUOTES, 'UTF-8'); ?>')" class="inline-flex items-center justify-center p-1.5 text-rose-500 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 rounded-xl transition shadow-xs" title="Delete Order">
                                                <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Responsive Card Grid (Mobile & Tablet < 1024px) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:hidden gap-3.5 sm:gap-4">
                    <?php if(empty($orders)): ?>
                        <div class="col-span-full bg-white border border-slate-200 rounded-3xl p-8 text-center text-slate-400 text-xs">
                            No orders found.
                        </div>
                    <?php else: ?>
                        <?php foreach($orders as $o): ?>
                            <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col justify-between gap-3">

                                <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                                    <div>
                                        <a href="view_order.php?id=<?= $o['id']; ?>" class="font-mono font-black text-sky-700 hover:underline text-sm">
                                            #<?= htmlspecialchars($o['order_number']); ?>
                                        </a>
                                        <p class="text-[10px] text-slate-400 mt-0.5"><?= date('M d, Y • h:i A', strtotime($o['created_at'])); ?></p>
                                    </div>
                                    <form action="orders_action.php?<?= http_build_query($_GET); ?>" method="POST">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $o['id']; ?>">
                                        <select name="order_status" onchange="this.form.submit()" class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full border <?= getStatusStyle($o['order_status']); ?>">
                                            <option value="pending" <?= $o['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?= $o['order_status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="ready" <?= $o['order_status'] == 'ready' ? 'selected' : ''; ?>>Ready</option>
                                            <option value="packed" <?= $o['order_status'] == 'packed' ? 'selected' : ''; ?>>Packed</option>
                                            <option value="shipped" <?= $o['order_status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="completed" <?= $o['order_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?= $o['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="refunded" <?= $o['order_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                        </select>
                                    </form>
                                </div>

                                <div>
                                    <p class="font-bold text-slate-900 text-xs"><?= htmlspecialchars($o['customer_first_name'] . ' ' . $o['customer_last_name']); ?></p>
                                    <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($o['customer_email']); ?></p>
                                </div>

                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 flex justify-between items-center text-xs">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Total Due</span>
                                        <span class="font-black text-slate-900">Rs. <?= number_format($o['total_amount'], 2); ?></span>
                                    </div>
                                    <span class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[9px] font-bold text-slate-600 uppercase">
                                        <?= htmlspecialchars($o['payment_method']); ?>
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <a href="view_order.php?id=<?= $o['id']; ?>" class="flex-1 bg-sky-700 hover:bg-sky-800 text-white text-center py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-xs">
                                        View Details
                                    </a>
                                    <button type="button" onclick="confirmDeleteOrder(<?= $o['id']; ?>, '<?= htmlspecialchars($o['order_number'], ENT_QUOTES, 'UTF-8'); ?>')" class="p-2 text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition shadow-xs" title="Delete Order">
                                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ==================== 20-BY-20 PAGINATION CONTROLS ==================== -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs text-slate-500 font-medium text-center sm:text-left">
                            Showing <span class="font-bold text-slate-800"><?= min($offset + 1, $report_total_orders); ?></span> to 
                            <span class="font-bold text-slate-800"><?= min($offset + $per_page, $report_total_orders); ?></span> of 
                            <span class="font-bold text-sky-700"><?= number_format($report_total_orders); ?></span> total orders
                        </div>

                        <div class="flex items-center gap-1.5 flex-wrap justify-center">
                            <!-- Previous Page Button -->
                            <?php if ($page > 1): ?>
                                <a href="<?= htmlspecialchars(getPaginationUrl($page - 1)); ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 transition">
                                    &larr; Prev
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 rounded-xl border border-slate-100 text-xs font-semibold text-slate-300 cursor-not-allowed">
                                    &larr; Prev
                                </span>
                            <?php endif; ?>

                            <!-- Numbered Page Links (Smart Window) -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<a href="' . htmlspecialchars(getPaginationUrl(1)) . '" class="w-8 h-8 flex items-center justify-center rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-sky-50 transition">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-1 text-slate-400 text-xs">...</span>';
                                }
                            }

                            for ($p = $start_page; $p <= $end_page; $p++) {
                                if ($p == $page) {
                                    echo '<span class="w-8 h-8 flex items-center justify-center rounded-xl bg-sky-700 text-white text-xs font-black shadow-xs">' . $p . '</span>';
                                } else {
                                    echo '<a href="' . htmlspecialchars(getPaginationUrl($p)) . '" class="w-8 h-8 flex items-center justify-center rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 transition">' . $p . '</a>';
                                }
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-1 text-slate-400 text-xs">...</span>';
                                }
                                echo '<a href="' . htmlspecialchars(getPaginationUrl($total_pages)) . '" class="w-8 h-8 flex items-center justify-center rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-sky-50 transition">' . $total_pages . '</a>';
                            }
                            ?>

                            <!-- Next Page Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="<?= htmlspecialchars(getPaginationUrl($page + 1)); ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 transition">
                                    Next &rarr;
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 rounded-xl border border-slate-100 text-xs font-semibold text-slate-300 cursor-not-allowed">
                                    Next &rarr;
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- Hidden Form for Safe Deletion -->
    <form id="delete-order-form" action="orders_action.php?<?= http_build_query($_GET); ?>" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_order">
        <input type="hidden" name="order_id" id="delete_order_id" value="">
    </form>

    <script>
        // SweetAlert2 Confirmation for Order Deletion
        function confirmDeleteOrder(orderId, orderNumber) {
            Swal.fire({
                title: 'Delete Order #' + orderNumber + '?',
                text: 'This will permanently remove the order record and all associated order items. This cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Delete Order',
                cancelButtonText: 'Cancel',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_order_id').value = orderId;
                    document.getElementById('delete-order-form').submit();
                }
            });
        }

        // Custom Date Picker Toggle
        const presetSelect = document.getElementById('date_preset');
        const customDatesBox = document.getElementById('custom_dates');

        presetSelect?.addEventListener('change', function() {
            if(this.value === 'custom') {
                customDatesBox.classList.remove('hidden');
                customDatesBox.classList.add('flex');
            } else {
                customDatesBox.classList.add('hidden');
                customDatesBox.classList.remove('flex');
            }
        });

        // SweetAlert Flash Notifications
        <?php if($message): ?>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?= $messageType; ?>',
                title: '<?= addslashes($message); ?>',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        <?php endif; ?>

        // Mobile Header Menu Toggle
        const headerOpenBtn = document.getElementById('mobile-menu-open-header');
        if(headerOpenBtn) {
            headerOpenBtn.addEventListener('click', function() {
                const actualOpenBtn = document.getElementById('mobile-menu-open');
                if(actualOpenBtn) actualOpenBtn.click();
            });
        }
    </script>
</body>
</html>