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

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id === 0) {
    header("Location: orders.php");
    exit;
}

// SweetAlert Flash
$message = '';
$messageType = '';
if (isset($_SESSION['swal_msg'])) {
    $message = $_SESSION['swal_msg'];
    $messageType = $_SESSION['swal_type'];
    unset($_SESSION['swal_msg'], $_SESSION['swal_type']);
}

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT o.*, z.zone_name, m.method_name 
    FROM orders o 
    LEFT JOIN shipping_zones z ON o.shipping_zone_id = z.id
    LEFT JOIN shipping_methods m ON o.shipping_method_id = m.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit;
}

// Fetch Ordered Items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Catalog Products for Search Dropdown
$products = $pdo->query("SELECT id, name, sku, regular_price, sale_price FROM products WHERE status = 'published' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($order['order_number']); ?> | ReadersHub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, input, textarea, select, button { font-family: 'Poppins', sans-serif !important; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .select2-container--default .select2-selection--single {
            height: 42px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            padding-left: 6px;
            font-size: 0.8125rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1e293b;
            font-weight: 600;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 8px;
        }
        .select2-dropdown {
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-slate-800 antialiased">

    <?php 
    if (file_exists('../../sidebar.php')) { require_once '../../sidebar.php'; } 
    elseif (file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">

        <!-- Top Sticky Header -->
        <header class="bg-white/95 backdrop-blur-md shadow-xs border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3.5 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-open-header" type="button" class="md:hidden p-2 text-slate-600 hover:text-sky-700 focus:outline-none rounded-xl hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="orders.php" class="text-slate-500 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1">
                    &larr; Back
                </a>
                <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 tracking-tight">
                    Order <span class="text-sky-700 font-mono">#<?= htmlspecialchars($order['order_number']); ?></span>
                </h1>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Resend Order Confirmation Email Button -->
                <form action="view_order_action.php" method="POST" class="inline" onsubmit="return confirm('Resend order confirmation email to <?= htmlspecialchars($order['customer_email']); ?>?');">
                    <input type="hidden" name="action" value="resend_confirmation_email">
                    <input type="hidden" name="order_id" value="<?= $order_id; ?>">
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-1.5 rounded-xl text-xs font-bold transition shadow-xs active:scale-98" title="Resend complete order email to customer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span>Resend Email</span>
                    </button>
                </form>

                <!-- Single Invoice View & Download Link -->
                <a href="/invoice_single?id=<?= $order_id; ?>" target="_blank" class="inline-flex items-center gap-1.5 bg-white hover:bg-sky-50 text-sky-700 border border-sky-200 hover:border-sky-300 px-3.5 py-1.5 rounded-xl text-xs font-bold transition shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>View / Print Invoice</span>
                    <span class="text-[10px]">&nearr;</span>
                </a>

                <span class="px-3 py-1 border rounded-xl text-xs font-black uppercase tracking-wider <?= getStatusStyle($order['order_status']); ?>">
                    <?= htmlspecialchars($order['order_status']); ?>
                </span>
            </div>
        </header>

        <!-- Full Width Scroll Container -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 xl:p-10">
            <div class="w-full grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- Left 8 Columns: Items & Add Product Search -->
                <div class="lg:col-span-8 space-y-6 w-full">

                    <!-- Items Card -->
                    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden w-full">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Ordered Items</h2>
                            <span class="text-xs font-bold text-slate-500"><?= count($items); ?> Items</span>
                        </div>

                        <!-- Desktop Table -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-widest font-extrabold">
                                        <th class="px-5 py-3">Book Title</th>
                                        <th class="px-5 py-3 text-center">Unit Price</th>
                                        <th class="px-5 py-3 text-center">Quantity</th>
                                        <th class="px-5 py-3 text-right">Total</th>
                                        <th class="px-5 py-3 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs">
                                    <?php foreach ($items as $item): ?>
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-5 py-3.5">
                                            <p class="font-extrabold text-slate-900 text-sm"><?= htmlspecialchars($item['product_name']); ?></p>
                                            <?php if (!empty($item['variation_name'])): ?>
                                                <span class="text-[11px] text-sky-700 font-semibold mt-0.5 block"><?= htmlspecialchars($item['variation_name']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3.5 text-center text-slate-600 font-bold">
                                            Rs. <?= number_format($item['price'], 2); ?>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <form action="view_order_action.php" method="POST" class="inline-flex items-center gap-1.5">
                                                <input type="hidden" name="action" value="update_item_qty">
                                                <input type="hidden" name="order_id" value="<?= $order_id; ?>">
                                                <input type="hidden" name="item_id" value="<?= $item['id']; ?>">
                                                <input type="number" name="quantity" value="<?= $item['quantity']; ?>" min="1" class="w-14 bg-slate-50 border border-slate-200 text-center font-bold text-slate-900 rounded-lg py-1 px-1 text-xs outline-none focus:border-sky-700">
                                                <button type="submit" class="text-[10px] bg-sky-50 text-sky-700 hover:bg-sky-100 font-bold px-2 py-1 rounded-md transition">Set</button>
                                            </form>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-black text-slate-900">
                                            Rs. <?= number_format($item['total'], 2); ?>
                                        </td>
                                        <td class="px-5 py-3.5 text-right">
                                            <form action="view_order_action.php" method="POST" onsubmit="return confirm('Remove this title from order?');">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="order_id" value="<?= $order_id; ?>">
                                                <input type="hidden" name="item_id" value="<?= $item['id']; ?>">
                                                <button type="submit" class="p-1.5 text-rose-500 hover:text-rose-700 bg-rose-50 rounded-lg hover:bg-rose-100 transition" title="Delete Item">
                                                    <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Items List -->
                        <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
                            <?php foreach ($items as $item): ?>
                                <div class="pt-3 first:pt-0 flex flex-col gap-2">
                                    <div class="flex justify-between items-start gap-2">
                                        <div>
                                            <p class="font-extrabold text-slate-900 text-xs leading-snug"><?= htmlspecialchars($item['product_name']); ?></p>
                                            <?php if (!empty($item['variation_name'])): ?>
                                                <span class="text-[10px] text-sky-700 font-semibold"><?= htmlspecialchars($item['variation_name']); ?></span>
                                            <?php endif; ?>
                                            <p class="text-[11px] text-slate-500 mt-0.5">Rs. <?= number_format($item['price'], 2); ?> each</p>
                                        </div>
                                        <span class="font-black text-xs text-slate-900">Rs. <?= number_format($item['total'], 2); ?></span>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-slate-50 pt-2">
                                        <form action="view_order_action.php" method="POST" class="flex items-center gap-1">
                                            <input type="hidden" name="action" value="update_item_qty">
                                            <input type="hidden" name="order_id" value="<?= $order_id; ?>">
                                            <input type="hidden" name="item_id" value="<?= $item['id']; ?>">
                                            <span class="text-[10px] font-bold text-slate-400">Qty:</span>
                                            <input type="number" name="quantity" value="<?= $item['quantity']; ?>" min="1" class="w-12 bg-slate-50 border border-slate-200 text-center font-bold text-slate-900 rounded py-0.5 text-xs">
                                            <button type="submit" class="text-[10px] bg-sky-50 text-sky-700 font-bold px-2 py-1 rounded">Update</button>
                                        </form>
                                        <form action="view_order_action.php" method="POST" onsubmit="return confirm('Remove title?');">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="order_id" value="<?= $order_id; ?>">
                                            <input type="hidden" name="item_id" value="<?= $item['id']; ?>">
                                            <button type="submit" class="text-rose-600 bg-rose-50 px-2.5 py-1 rounded text-[10px] font-bold">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Add Product Search Box -->
                        <div class="p-5 bg-slate-50/70 border-t border-slate-100">
                            <form action="view_order_action.php" method="POST" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                                <input type="hidden" name="action" value="add_item">
                                <input type="hidden" name="order_id" value="<?= $order_id; ?>">

                                <div class="flex-1 min-w-0">
                                    <label class="block text-[10px] uppercase tracking-wider font-extrabold text-slate-500 mb-1">Search & Add Product to Order</label>
                                    <select name="product_id" id="product_search_select" required class="w-full">
                                        <option value="">Search by title or ISBN...</option>
                                        <?php foreach ($products as $p): 
                                            $price = $p['sale_price'] > 0 ? $p['sale_price'] : $p['regular_price'];
                                        ?>
                                            <option value="<?= $p['id']; ?>">
                                                <?= htmlspecialchars($p['name']); ?> <?= $p['sku'] ? "({$p['sku']})" : ''; ?> - Rs. <?= number_format($price, 2); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="w-full sm:w-24">
                                    <label class="block text-[10px] uppercase tracking-wider font-extrabold text-slate-500 mb-1">Qty</label>
                                    <input type="number" name="quantity" value="1" min="1" required class="w-full bg-slate-50 border border-slate-300 text-center font-bold text-slate-900 rounded-xl h-[42px] px-2 text-xs focus:bg-white focus:border-sky-700 outline-none">
                                </div>

                                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-5 h-[42px] rounded-xl text-xs uppercase tracking-wider transition shrink-0 active:scale-98">
                                    + Add Item
                                </button>
                            </form>
                            <p class="text-[10px] text-sky-700 font-semibold mt-2">Note: Adding a title will trigger an automatic invoice update notification email to the customer.</p>
                        </div>
                    </div>

                    <!-- Totals Card with Invoice Button -->
                    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-5 sm:p-6 flex flex-col sm:flex-row justify-between items-center gap-4 w-full">
                        <div>
                            <a href="/invoice_single?id=<?= $order_id; ?>" target="_blank" class="inline-flex items-center gap-2 bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 px-4 py-2.5 rounded-xl font-bold text-xs transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Download PDF / Print Invoice</span>
                            </a>
                        </div>
                        <div class="w-full sm:w-1/2 space-y-2.5 text-xs sm:text-sm">
                            <div class="flex justify-between text-slate-500 font-medium">
                                <span>Subtotal:</span>
                                <span class="font-bold text-slate-900">Rs. <?= number_format($order['subtotal'], 2); ?></span>
                            </div>
                            <div class="flex justify-between text-slate-500 font-medium border-b border-slate-100 pb-2.5">
                                <span>Courier Fee:</span>
                                <span class="font-bold text-slate-900">Rs. <?= number_format($order['shipping_cost'], 2); ?></span>
                            </div>
                            <div class="flex justify-between text-base sm:text-lg font-black pt-1 text-slate-900">
                                <span>Total Amount:</span>
                                <span class="text-sky-700">Rs. <?= number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right 4 Columns: Status, Courier Tracking, Customer Info -->
                <div class="lg:col-span-4 space-y-6 w-full">

                    <!-- Status Updater Card -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs w-full">
                        <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider border-b border-slate-100 pb-3 mb-4">
                            Update Order Status
                        </h3>
                        <form action="view_order_action.php" method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?= $order_id; ?>">

                            <select name="order_status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl px-3.5 py-2.5 text-xs outline-none focus:border-sky-700 cursor-pointer">
                                <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="ready" <?= $order['order_status'] === 'ready' ? 'selected' : ''; ?>>Ready to Ship</option>
                                <option value="packed" <?= $order['order_status'] === 'packed' ? 'selected' : ''; ?>>Packed</option>
                                <option value="shipped" <?= $order['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="completed" <?= $order['order_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="refunded" <?= $order['order_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>

                            <button type="submit" class="w-full bg-sky-700 hover:bg-sky-800 text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition shadow-md shadow-sky-700/20 active:scale-98">
                                Update & Notify Email
                            </button>
                        </form>
                    </div>

                    <!-- Courier Tracking & Notes Card -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs w-full">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                            <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Courier Dispatch</h3>
                            <svg class="w-4 h-4 text-sky-700 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                        </div>

                        <form action="view_order_action.php" method="POST" class="space-y-3.5">
                            <input type="hidden" name="action" value="update_tracking_notes">
                            <input type="hidden" name="order_id" value="<?= $order_id; ?>">

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Courier Partner</label>
                                <input type="text" name="tracking_provider" value="<?= htmlspecialchars($order['tracking_provider'] ?? ''); ?>" placeholder="e.g. Koombiyo, Domex, Pronto" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3 py-2 text-xs outline-none focus:border-sky-700">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Tracking Number / Waybill</label>
                                <input type="text" name="tracking_number" value="<?= htmlspecialchars($order['tracking_number'] ?? ''); ?>" placeholder="Tracking code" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3 py-2 text-xs font-mono outline-none focus:border-sky-700">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Direct Tracking Link</label>
                                <input type="url" name="tracking_link" value="<?= htmlspecialchars($order['tracking_link'] ?? ''); ?>" placeholder="https://tracking.provider.com/..." class="w-full bg-slate-50 border border-slate-200 text-sky-700 rounded-xl px-3 py-2 text-xs outline-none focus:border-sky-700 font-mono">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Admin Notes (Private)</label>
                                <textarea name="admin_notes" rows="3" placeholder="Notes visible only to admins..." class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-3 py-2 text-xs outline-none focus:border-sky-700"><?= htmlspecialchars($order['admin_notes'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition shadow-xs active:scale-98">
                                Save Dispatch Info
                            </button>
                        </form>
                    </div>

                    <!-- Customer Info Card -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs space-y-4 w-full">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                            <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">
                                Customer Information
                            </h3>
                            <a href="mailto:<?= htmlspecialchars($order['customer_email']); ?>" class="text-[11px] font-bold text-sky-700 hover:underline">
                                Email Customer &rarr;
                            </a>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                                <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Name</span>
                                <p class="font-extrabold text-slate-900 text-sm"><?= htmlspecialchars($order['customer_first_name'] . ' ' . $order['customer_last_name']); ?></p>
                            </div>

                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                                <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Email</span>
                                <a href="mailto:<?= htmlspecialchars($order['customer_email']); ?>" class="font-bold text-sky-700 hover:underline"><?= htmlspecialchars($order['customer_email']); ?></a>
                            </div>

                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                                <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Phone</span>
                                <a href="tel:<?= htmlspecialchars($order['customer_phone']); ?>" class="font-bold text-sky-700 hover:underline"><?= htmlspecialchars($order['customer_phone']); ?></a>
                            </div>

                            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                                <span class="text-[10px] font-bold uppercase text-slate-400 block mb-1">Delivery Address</span>
                                <p class="text-slate-700 font-medium leading-relaxed">
                                    <?= htmlspecialchars($order['billing_address']); ?><br>
                                    <?php if (!empty($order['billing_town'])) echo htmlspecialchars($order['billing_town']) . "<br>"; ?>
                                    <?php if (!empty($order['billing_district'])) echo htmlspecialchars($order['billing_district']); ?>
                                    <?php if (!empty($order['billing_zip_code'])) echo " - " . htmlspecialchars($order['billing_zip_code']); ?>
                                    <br>
                                    <?php if (!empty($order['billing_province'])) echo htmlspecialchars($order['billing_province']); ?>
                                </p>
                            </div>

                            <?php if (!empty($order['order_notes'])): ?>
                                <div class="bg-amber-50 text-amber-900 p-3.5 rounded-2xl border border-amber-200">
                                    <span class="text-[10px] font-black uppercase tracking-wider block mb-1">Customer Delivery Note:</span>
                                    <p class="font-medium text-[11px]"><?= nl2br(htmlspecialchars($order['order_notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </main>

    <script>
        // SweetAlert Flash Notifications
        <?php if ($message): ?>
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

        // Initialize Select2 for Live Search on Products
        $(document).ready(function() {
            $('#product_search_select').select2({
                placeholder: "Type book name or SKU...",
                allowClear: true,
                width: '100%'
            });
        });

        // Mobile Header Menu Toggle
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