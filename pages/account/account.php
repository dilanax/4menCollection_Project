<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($pdo)) {
    require_once 'config/db.php';
}

// Redirect unauthenticated guests to login
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$message = '';
$messageType = '';

// ==========================================
// 1. PROFILE & PASSWORD UPDATES
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_password') {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (strlen($new_pass) < 8) {
            $message = "New password must be at least 8 characters long.";
            $messageType = "error";
        } elseif ($new_pass !== $confirm_pass) {
            $message = "New passwords do not match!";
            $messageType = "error";
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_pass_data = $stmt->fetch();

            if ($user_pass_data && password_verify($current_pass, $user_pass_data['password'])) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($update->execute([$hashed, $user_id])) {
                    $message = "Your password has been successfully updated!";
                    $messageType = "success";
                }
            } else {
                $message = "Current password verification failed. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// ==========================================
// 2. FETCH USER DATA & ORDERS
// ==========================================
$stmt = $pdo->prepare("SELECT id, username, email, user_role, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$o_stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$o_stmt->execute([$user_id]);
$orders = $o_stmt->fetchAll();

function getCustomerStatusStyle($status) {
    switch (strtolower(trim($status))) {
        case 'pending': 
            return 'bg-amber-50 text-amber-700 border border-amber-200/70';
        case 'processing': 
            return 'bg-sky-50 text-sky-700 border border-sky-200/70';
        case 'ready': 
        case 'packed': 
            return 'bg-indigo-50 text-indigo-700 border border-indigo-200/70';
        case 'shipped': 
            return 'bg-purple-50 text-purple-700 border border-purple-200/70';
        case 'completed': 
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200/70';
        case 'cancelled': 
        case 'refunded': 
            return 'bg-rose-50 text-rose-700 border border-rose-200/70';
        default: 
            return 'bg-slate-100 text-slate-700 border border-slate-200';
    }
}

// Order Statistics
$total_orders = count($orders);
$completed_count = count(array_filter($orders, fn($o) => strtolower($o['order_status'] ?? '') === 'completed'));
$processing_count = count(array_filter($orders, fn($o) => in_array(strtolower($o['order_status'] ?? ''), ['pending', 'processing', 'ready', 'packed'])));
$total_spent = array_sum(array_column(array_filter($orders, fn($o) => strtolower($o['order_status'] ?? '') !== 'cancelled'), 'total_amount'));
?>

<script>
    document.title = "My Account | ReadersHub";
</script>

<div class="w-full bg-[#f8fafc] py-6 sm:py-10 font-sans min-h-screen">
    <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12">
        
        <!-- Header & Breadcrumbs -->
        <div class="mb-6 sm:mb-8 border-b border-slate-200 pb-4">
            <nav class="flex items-center text-xs font-semibold text-slate-400 mb-2 gap-2">
                <a href="/" class="hover:text-sky-700 transition">Home</a>
                <span>/</span>
                <span class="text-sky-700 font-bold">My Account</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Account Control Center
            </h1>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-2xl <?= $messageType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800'; ?> flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full <?= $messageType === 'success' ? 'bg-emerald-500/20 text-emerald-600' : 'bg-rose-500/20 text-rose-600'; ?> flex items-center justify-center shrink-0">
                        <?php if ($messageType === 'success'): ?>
                            <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs sm:text-sm font-semibold"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-6 lg:gap-8 items-start">
            
            <!-- Left Sidebar Navigation -->
            <div class="w-full lg:w-1/4 xl:w-1/5 shrink-0">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200/90 overflow-hidden sticky top-24">
                    
                    <!-- User Mini Profile -->
                    <div class="p-6 border-b border-slate-100 bg-gradient-to-b from-sky-50/60 to-white flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-sky-700 text-white flex items-center justify-center text-xl font-black uppercase shadow-md shadow-sky-700/20 shrink-0">
                            <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div class="min-w-0">
                            <p class="font-extrabold text-slate-900 text-base leading-tight truncate"><?= htmlspecialchars($user['username'] ?? 'Reader'); ?></p>
                            <p class="text-[11px] text-slate-400 font-medium truncate mt-0.5"><?= htmlspecialchars($user['email'] ?? 'No email set'); ?></p>
                        </div>
                    </div>

                    <!-- Navigation Links -->
                    <nav class="p-3 space-y-1">
                        <button type="button" onclick="showTab('dashboard')" id="btn-dashboard" class="account-tab-btn w-full flex items-center gap-3 px-4 py-3 text-xs sm:text-sm font-bold rounded-2xl transition text-left text-sky-700 bg-sky-50 border border-sky-100/50">
                            <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            Dashboard
                        </button>
                        
                        <button type="button" onclick="showTab('orders')" id="btn-orders" class="account-tab-btn w-full flex items-center justify-between px-4 py-3 text-xs sm:text-sm font-semibold rounded-2xl transition text-left border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                Book Orders
                            </span>
                            <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md border border-slate-200"><?= $total_orders; ?></span>
                        </button>

                        <button type="button" onclick="showTab('details')" id="btn-details" class="account-tab-btn w-full flex items-center gap-3 px-4 py-3 text-xs sm:text-sm font-semibold rounded-2xl transition text-left border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                            <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Security & Details
                        </button>

                        <div class="pt-2 border-t border-slate-100 my-1"></div>

                        <a href="/logout" class="flex items-center gap-3 px-4 py-3 text-xs sm:text-sm font-bold rounded-2xl text-rose-600 hover:bg-rose-50 transition border border-transparent hover:border-rose-100">
                            <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Log Out
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Right Area: Tab Contents -->
            <div class="w-full lg:w-3/4 xl:w-4/5">
                
                <!-- 1. DASHBOARD TAB -->
                <div id="tab-dashboard" class="account-content block space-y-6">
                    
                    <!-- Welcome Banner -->
                    <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/90 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] font-extrabold text-sky-700 uppercase tracking-widest block mb-1">ReadersHub Member</span>
                            <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">Ayubowan, <?= htmlspecialchars($user['username'] ?? 'Reader'); ?>!</h2>
                            <p class="text-xs sm:text-sm text-slate-500 mt-1.5 leading-relaxed max-w-xl">From here you can review recent orders, download official tax invoices, and track your reading shipments.</p>
                        </div>
                        <a href="/shop" class="inline-flex items-center justify-center gap-2 bg-sky-700 hover:bg-sky-800 text-white font-bold text-xs uppercase tracking-wider px-6 py-3.5 rounded-2xl transition shadow-sm self-start sm:self-auto shrink-0">
                            Find New Books &rarr;
                        </a>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
                        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-xs flex items-center gap-4 hover:border-sky-200 transition">
                            <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-700 border border-sky-100 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-slate-900"><?= $total_orders; ?></h3>
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Book Orders</p>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-xs flex items-center gap-4 hover:border-amber-200 transition">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-slate-900"><?= $processing_count; ?></h3>
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">In Preparation</p>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-xs flex items-center gap-4 hover:border-emerald-200 transition">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-100 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-slate-900"><?= $completed_count; ?></h3>
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Delivered Books</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Orders Quick List -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/90 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-base font-black text-slate-900">Recent Purchase History</h3>
                            <button onclick="showTab('orders')" class="text-xs font-bold text-sky-700 hover:text-sky-800 hover:underline flex items-center gap-1">
                                View All Orders &rarr;
                            </button>
                        </div>
                        
                        <?php if (empty($orders)): ?>
                            <div class="p-8 text-center text-xs text-slate-400">You have no order history yet.</div>
                        <?php else: ?>
                            <div class="divide-y divide-slate-100">
                                <?php foreach (array_slice($orders, 0, 4) as $o): ?>
                                    <a href="/invoice_single?id=<?= (int)$o['id']; ?>" class="p-5 flex items-center justify-between gap-4 hover:bg-sky-50/40 transition group block">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-slate-100 group-hover:bg-sky-100 text-slate-600 group-hover:text-sky-700 flex items-center justify-center transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </div>
                                            <div>
                                                <span class="font-mono text-xs font-bold text-sky-700 group-hover:underline block">#<?= htmlspecialchars($o['order_number']); ?></span>
                                                <span class="text-[11px] text-slate-400 font-medium"><?= date('M d, Y', strtotime($o['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="text-right flex items-center gap-3">
                                            <div>
                                                <span class="text-xs font-extrabold text-slate-800 block">Rs. <?= number_format($o['total_amount'], 2); ?></span>
                                                <span class="inline-block mt-0.5 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider <?= getCustomerStatusStyle($o['order_status']); ?>">
                                                    <?= htmlspecialchars($o['order_status']); ?>
                                                </span>
                                            </div>
                                            <span class="text-xs font-bold text-sky-700 hidden sm:inline-flex items-center gap-1 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-100">
                                                Invoice &rarr;
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- 2. ORDERS HISTORY TAB -->
                <div id="tab-orders" class="account-content hidden">
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/90 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                            <div>
                                <h2 class="text-lg font-black text-slate-900 tracking-tight">Your Book Shipments</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Click on any order to view & download official invoices (PDF & Image)</p>
                            </div>
                        </div>

                        <?php if (empty($orders)): ?>
                            <div class="p-16 text-center max-w-sm mx-auto">
                                <div class="w-16 h-16 bg-sky-50 text-sky-700 border border-sky-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                                    <svg class="w-8 h-8 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                </div>
                                <h3 class="text-lg font-extrabold text-slate-900 mb-1.5">No orders found</h3>
                                <p class="text-xs text-slate-400 mb-6 leading-relaxed">Looks like you haven't ordered any books with us yet. Start building your library!</p>
                                <a href="/shop" class="inline-block bg-sky-700 hover:bg-sky-800 text-white px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-wider transition shadow-xs">Explore Bookstore</a>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse min-w-[700px]">
                                    <thead>
                                        <tr class="bg-slate-50/50 border-b border-slate-100 text-slate-500 text-[10px] uppercase tracking-widest font-extrabold">
                                            <th class="py-4 px-6">Invoice #</th>
                                            <th class="py-4 px-6">Date</th>
                                            <th class="py-4 px-6">Amount</th>
                                            <th class="py-4 px-6">Order Status</th>
                                            <th class="py-4 px-6">Dispatch & Courier</th>
                                            <th class="py-4 px-6 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs">
                                        <?php foreach ($orders as $o): ?>
                                            <tr class="hover:bg-slate-50/70 transition cursor-pointer" onclick="window.location.href='/invoice_single?id=<?= (int)$o['id']; ?>'">
                                                <td class="py-4 px-6 font-mono font-bold text-sky-700">
                                                    <a href="/invoice_single?id=<?= (int)$o['id']; ?>" class="hover:underline flex items-center gap-1.5" onclick="event.stopPropagation();">
                                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                        #<?= htmlspecialchars($o['order_number']); ?>
                                                    </a>
                                                </td>
                                                <td class="py-4 px-6 text-slate-500 font-medium">
                                                    <?= date('M d, Y', strtotime($o['created_at'])); ?>
                                                </td>
                                                <td class="py-4 px-6 font-extrabold text-slate-900">
                                                    Rs. <?= number_format($o['total_amount'], 2); ?>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-wider <?= getCustomerStatusStyle($o['order_status']); ?>">
                                                        <?= htmlspecialchars($o['order_status']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <?php if (!empty($o['tracking_number'])): ?>
                                                        <div class="inline-flex flex-col items-start gap-1">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="text-[10px] font-bold bg-slate-100 px-1.5 py-0.5 rounded text-slate-600 border border-slate-200">
                                                                    <?= htmlspecialchars($o['tracking_provider'] ?: 'Courier'); ?>
                                                                </span>
                                                                <span class="font-mono font-extrabold text-sky-700 text-[11px]"><?= htmlspecialchars($o['tracking_number']); ?></span>
                                                            </div>
                                                            <?php if (!empty($o['tracking_link'])): ?>
                                                                <a href="<?= htmlspecialchars($o['tracking_link']); ?>" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();" class="text-[10px] font-bold text-sky-700 hover:text-sky-800 hover:underline inline-flex items-center gap-1">
                                                                    Track Parcel 
                                                                    <svg class="w-3 h-3 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-[11px] text-slate-400 italic">Processing dispatch</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-4 px-6 text-right">
                                                    <a href="/invoice_single?id=<?= (int)$o['id']; ?>" onclick="event.stopPropagation();" class="inline-flex items-center gap-1.5 bg-sky-50 hover:bg-sky-700 text-sky-700 hover:text-white border border-sky-200 px-3 py-1.5 rounded-xl font-bold text-[11px] transition shadow-2xs">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                        Invoice
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. DETAILS & SECURITY TAB -->
                <div id="tab-details" class="account-content hidden space-y-6">
                    
                    <!-- Profile Info Overview -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/90 p-6 sm:p-8">
                        <h2 class="text-lg font-black text-slate-900 tracking-tight mb-4 pb-3 border-b border-slate-100">Profile Information</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs sm:text-sm">
                            <div>
                                <span class="block font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Username</span>
                                <p class="font-extrabold text-slate-900"><?= htmlspecialchars($user['username'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <span class="block font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Registered Email</span>
                                <p class="font-extrabold text-slate-800"><?= htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <span class="block font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Account Role</span>
                                <span class="inline-block font-bold text-sky-700 bg-sky-50 border border-sky-100 px-3 py-1 rounded-md text-[10px] tracking-wider uppercase">
                                    <?= htmlspecialchars($user['user_role'] ?? 'Customer'); ?>
                                </span>
                            </div>
                            <div>
                                <span class="block font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Member Since</span>
                                <p class="text-slate-600 font-medium"><?= isset($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'N/A'; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Card -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/90 p-6 sm:p-8">
                        <h2 class="text-lg font-black text-slate-900 tracking-tight mb-1.5">Update Password</h2>
                        <p class="text-xs text-slate-500 mb-6">Ensure your account uses a secure password of at least 8 characters.</p>

                        <form action="/account" method="POST" class="space-y-4 max-w-md">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Current Password</label>
                                <input type="password" name="current_password" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition shadow-xs">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">New Password</label>
                                <input type="password" name="new_password" required minlength="8" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition shadow-xs">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Confirm New Password</label>
                                <input type="password" name="confirm_password" required minlength="8" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition shadow-xs">
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs uppercase tracking-wider px-6 py-3.5 rounded-xl transition shadow-sm active:scale-98">
                                    Update Security Key
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

<script>
    function showTab(tabName) {
        document.querySelectorAll('.account-content').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('block');
        });

        document.querySelectorAll('.account-tab-btn').forEach(el => {
            el.classList.remove('text-sky-700', 'bg-sky-50', 'border-sky-100/50');
            el.classList.add('text-slate-600', 'hover:bg-slate-50', 'hover:text-slate-900', 'border-transparent');
        });

        const targetTab = document.getElementById('tab-' + tabName);
        if (targetTab) {
            targetTab.classList.remove('hidden');
            targetTab.classList.add('block');
        }

        const activeBtn = document.getElementById('btn-' + tabName);
        if (activeBtn) {
            activeBtn.classList.remove('text-slate-600', 'hover:bg-slate-50', 'hover:text-slate-900', 'border-transparent');
            activeBtn.classList.add('text-sky-700', 'bg-sky-50', 'border-sky-100/50');
        }
    }
</script>