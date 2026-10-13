<?php
// Session එක ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. දැඩි ආරක්ෂක පරීක්ෂාව (Strict Security Check - Admin Only)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Admin නෙවෙයි නම් (උදා: customer හෝ shop_admin) එයාව එලවනවා
    header("Location: /login");
    exit;
}

// 2. Database එකට සම්බන්ධ වීම
require_once '../../config/db.php';

// 3. Dashboard එකට අවශ්‍ය දත්ත ලබා ගැනීම (Data Aggregation)
$total_customers = 0;
$total_products = 0;
$pending_orders = 0;
$total_revenue = 0;
$recent_users = [];
$recent_orders = [];

// Chart Data Arrays
$chart_months = [];
$chart_revenue = [];
$status_labels = ['Pending', 'Processing', 'Ready/Packed', 'Shipped', 'Completed', 'Cancelled/Refunded'];
$status_counts = [0, 0, 0, 0, 0, 0];

try {
    // A. Basic Stats
    $total_customers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_role = 'customer'")->fetchColumn();
    $total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'published'")->fetchColumn();
    $pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
    
    // B. Total Revenue (Completed orders only)
    $total_revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE order_status = 'completed'")->fetchColumn() ?: 0;

    // C. Recent Users (Last 5)
    $recent_users = $pdo->query("SELECT username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // D. Recent Orders (Last 5)
    $recent_orders = $pdo->query("SELECT order_number, customer_first_name, total_amount, order_status, created_at FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // E. Data for Line Chart (Revenue last 6 months)
    $rev_stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b') as month_name, SUM(total_amount) as monthly_total 
        FROM orders 
        WHERE order_status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at), month_name
        ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC
    ");
    $rev_data = $rev_stmt->fetchAll();
    foreach ($rev_data as $row) {
        $chart_months[] = $row['month_name'];
        $chart_revenue[] = (float)$row['monthly_total'];
    }
    // Fallback if no data
    if(empty($chart_months)) { $chart_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']; $chart_revenue = [0, 0, 0, 0, 0, 0]; }

    // F. Data for Doughnut Chart (Order Status Breakdown)
    $stat_stmt = $pdo->query("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
    $stat_data = $stat_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $status_counts[0] = $stat_data['pending'] ?? 0;
    $status_counts[1] = $stat_data['processing'] ?? 0;
    $status_counts[2] = ($stat_data['ready'] ?? 0) + ($stat_data['packed'] ?? 0);
    $status_counts[3] = $stat_data['shipped'] ?? 0;
    $status_counts[4] = $stat_data['completed'] ?? 0;
    $status_counts[5] = ($stat_data['cancelled'] ?? 0) + ($stat_data['refunded'] ?? 0);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Status Color Helper for Table - Clean, intuitive status indicators
function getBadgeStyle($status) {
    switch ($status) {
        case 'pending': 
            return 'bg-amber-50 text-amber-800 border-amber-300 font-semibold';
        case 'processing': 
            return 'bg-sky-50 text-sky-800 border-sky-200 font-semibold';
        case 'ready':
        case 'packed':
            return 'bg-violet-50 text-violet-800 border-violet-200 font-semibold';
        case 'shipped': 
            return 'bg-indigo-50 text-indigo-800 border-indigo-200 font-semibold';
        case 'completed': 
            return 'bg-emerald-50 text-emerald-800 border-emerald-200 font-semibold shadow-xs';
        case 'cancelled': 
        case 'refunded': 
            return 'bg-rose-50 text-rose-700 border-rose-200 font-medium line-through';
        default: 
            return 'bg-slate-100 text-slate-700 border-slate-200 font-medium';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>4MEN COLLECTION | Executive Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background-color: #f8fafc;
        }
        /* Sleek custom scrollbars */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-[#f8fafc] flex h-screen overflow-hidden text-slate-900 antialiased">

    <?php 
    if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#f8fafc]">
        
        <!-- Luxury Top Header Bar -->
        <header class="bg-white border-b border-slate-200/80 px-6 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0 shadow-xs">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-open-header" class="md:hidden text-slate-700 hover:text-black p-1.5 rounded-xl hover:bg-slate-100 transition focus:outline-none" aria-label="Open Sidebar Menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Executive Dashboard</h1>
                    </div>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">4MEN Collection Operations</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4 sm:space-x-5">
                <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 bg-slate-50 rounded-full border border-slate-200/80 text-xs text-slate-600 font-medium">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span><?= date('l, M d, Y'); ?></span>
                </div>

                <div class="text-right hidden sm:block">
                    <p class="text-xs text-slate-500 font-medium">Signed in as <strong class="text-slate-900 font-semibold"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></p>
                    <span class="inline-block px-2 py-0.5 mt-0.5 bg-slate-900 text-white text-[10px] font-semibold tracking-wider uppercase rounded">SUPER ADMIN</span>
                </div>

                <div class="h-10 w-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-sm shadow-md border border-slate-800 tracking-wider">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- Main Scrollable Dashboard Canvas -->
        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 space-y-6">
            
            <!-- Modern User-Friendly KPI Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                
                <!-- 1. Total Net Revenue -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:border-emerald-500/50 hover:shadow-md transition-all duration-300 flex items-center justify-between group">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Net Revenue</span>
                            <span class="text-xs font-semibold bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full border border-emerald-200/80">Paid</span>
                        </div>
                        <h3 class="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Rs. <?= number_format($total_revenue); ?></h3>
                        <p class="text-xs text-slate-500 font-normal">Completed orders net value</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>

                <!-- 2. Pending Orders -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:border-amber-500/50 hover:shadow-md transition-all duration-300 flex items-center justify-between group">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pending Orders</span>
                            <?php if ($pending_orders > 0): ?>
                                <span class="text-xs font-semibold bg-amber-500 text-white px-2 py-0.5 rounded-full animate-pulse">Action Required</span>
                            <?php else: ?>
                                <span class="text-xs font-medium bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">All Clear</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-2xl lg:text-3xl font-bold <?= $pending_orders > 0 ? 'text-amber-600' : 'text-slate-900'; ?> tracking-tight"><?= number_format($pending_orders); ?></h3>
                        <p class="text-xs text-slate-500 font-normal">Awaiting fulfillment & dispatch</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 border border-amber-500/20 flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                </div>

                <!-- 3. Registered Clients -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:border-sky-500/50 hover:shadow-md transition-all duration-300 flex items-center justify-between group">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Registered Clients</span>
                            <span class="text-xs font-semibold bg-sky-50 text-sky-700 px-2 py-0.5 rounded-full border border-sky-200/80">Active</span>
                        </div>
                        <h3 class="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight"><?= number_format($total_customers); ?></h3>
                        <p class="text-xs text-slate-500 font-normal">Customer accounts</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-600 border border-sky-500/20 flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>

                <!-- 4. Active Products -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:border-violet-500/50 hover:shadow-md transition-all duration-300 flex items-center justify-between group">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Menswear Catalog</span>
                            <span class="text-xs font-semibold bg-violet-50 text-violet-700 px-2 py-0.5 rounded-full border border-violet-200/80">Published</span>
                        </div>
                        <h3 class="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight"><?= number_format($total_products); ?></h3>
                        <p class="text-xs text-slate-500 font-normal">Active items in store</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-violet-500/10 text-violet-600 border border-violet-500/20 flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </div>
                </div>

            </div>

            <!-- Charts Section (Analytics with Coordinated Modern Theme) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Revenue Analytics (Line Chart) -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs flex flex-col">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 tracking-tight">Revenue Trajectory</h3>
                            <p class="text-xs text-slate-500 font-normal mt-0.5">Monthly revenue trends over the last 6 months</p>
                        </div>
                        <span class="text-xs font-medium uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">Currency: LKR (Rs)</span>
                    </div>
                    <div class="relative h-72 w-full flex-1">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Order Status Breakdown (Doughnut Chart) -->
                <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs flex flex-col">
                    <div class="mb-6 pb-4 border-b border-slate-100">
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">Fulfillment Status</h3>
                        <p class="text-xs text-slate-500 font-normal mt-0.5">Distribution across all order stages</p>
                    </div>
                    <div class="relative h-64 w-full flex-1 flex items-center justify-center">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- Tables Section (Recent Orders & Clients) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Recent Orders Table -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-slate-900"></span>
                            <h3 class="text-sm font-bold text-slate-900">Recent Orders</h3>
                        </div>
                        <a href="/admin/orders/orders.php" class="text-xs font-semibold text-slate-700 hover:text-black transition underline underline-offset-4">View All &rarr;</a>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white border-b border-slate-100 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                                    <th class="px-6 py-3.5">Order Number</th>
                                    <th class="px-6 py-3.5">Client & Value</th>
                                    <th class="px-6 py-3.5 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if(!empty($recent_orders)): ?>
                                    <?php foreach($recent_orders as $ord): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-6 py-3.5">
                                            <p class="text-sm font-semibold text-slate-900">#<?= htmlspecialchars($ord['order_number']); ?></p>
                                            <p class="text-xs text-slate-500 font-normal mt-0.5"><?= date('M d, Y', strtotime($ord['created_at'])); ?></p>
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($ord['customer_first_name'] ?? 'Guest'); ?></p>
                                            <p class="text-xs font-bold text-emerald-700">Rs. <?= number_format($ord['total_amount'] ?? 0); ?></p>
                                        </td>
                                        <td class="px-6 py-3.5 text-right">
                                            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full border <?= getBadgeStyle($ord['order_status']); ?>">
                                                <?= ucfirst(htmlspecialchars($ord['order_status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="px-6 py-8 text-center text-xs text-slate-400">No recent orders found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- New Customers Table -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-slate-900"></span>
                            <h3 class="text-sm font-bold text-slate-900">New Clients</h3>
                        </div>
                        <a href="/admin/customer/users/users.php" class="text-xs font-semibold text-slate-700 hover:text-black transition underline underline-offset-4">View All &rarr;</a>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white border-b border-slate-100 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                                    <th class="px-6 py-3.5">User Information</th>
                                    <th class="px-6 py-3.5 text-right">Registered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if(!empty($recent_users)): ?>
                                    <?php foreach($recent_users as $user): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-6 py-3.5 flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                                                <?= strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars($user['username']); ?></p>
                                                <p class="text-xs text-slate-500 truncate mt-0.5"><?= htmlspecialchars($user['email']); ?></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3.5 text-right text-xs text-slate-500 font-medium">
                                            <?= date('M d, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="px-6 py-8 text-center text-xs text-slate-400">No client records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <script>
        // 1. Line Chart (Revenue - Sophisticated Modern Theme)
        const ctxRev = document.getElementById('revenueChart').getContext('2d');
        const gradientRevenue = ctxRev.createLinearGradient(0, 0, 0, 300);
        gradientRevenue.addColorStop(0, 'rgba(15, 23, 42, 0.15)');
        gradientRevenue.addColorStop(1, 'rgba(15, 23, 42, 0.00)');

        new Chart(ctxRev, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_months); ?>,
                datasets: [{
                    label: 'Revenue (Rs)',
                    data: <?= json_encode($chart_revenue); ?>,
                    borderColor: '#0f172a',
                    backgroundColor: gradientRevenue,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#0f172a',
                    pointBorderWidth: 2.5,
                    pointRadius: 4.5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.38
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: 'bold' },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 13, weight: '600' },
                        callbacks: {
                            label: function(context) {
                                return 'Rs. ' + Number(context.parsed.y).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [4, 4], color: '#e2e8f0' },
                        border: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 12 }, color: '#64748b' }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 12 }, color: '#64748b' }
                    }
                }
            }
        });

        // 2. Doughnut Chart (Coordinated User-Friendly Status Palette)
        const ctxStat = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStat, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($status_labels); ?>,
                datasets: [{
                    data: <?= json_encode($status_counts); ?>,
                    backgroundColor: [
                        '#f59e0b', // Pending (Amber)
                        '#0284c7', // Processing (Sky)
                        '#8b5cf6', // Ready/Packed (Purple)
                        '#6366f1', // Shipped (Indigo)
                        '#10b981', // Completed (Emerald)
                        '#f43f5e'  // Cancelled/Refunded (Rose)
                    ],
                    borderWidth: 2.5,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { family: 'Plus Jakarta Sans', size: 12, weight: '500' },
                            color: '#334155',
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 14
                        }
                    }
                }
            }
        });

        // Mobile Sidebar Toggle Hook
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