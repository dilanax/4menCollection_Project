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
    // Note: This is a simple query. For production, you might need a calendar table for missing months.
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

// Status Color Helper for Table
function getBadgeStyle($status) {
    switch ($status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'processing': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'completed': return 'bg-green-100 text-green-800 border-green-200';
        case 'cancelled': case 'refunded': return 'bg-red-100 text-red-800 border-red-200';
        default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Loopz Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden text-gray-800">

    <?php 
    if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-6 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-open-header" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Admin Dashboard</h1>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="text-right hidden sm:block">
                    <p class="text-sm text-gray-600">Logged in as: <strong class="text-gray-900"><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
                    <span class="inline-block px-2 py-0.5 mt-1 bg-red-100 text-red-800 text-[10px] font-bold rounded uppercase tracking-wider border border-red-200">Super Admin</span>
                </div>
                <div class="h-10 w-10 rounded-full bg-gray-900 text-white flex items-center justify-center font-bold text-lg shadow-md border-2 border-white ring-2 ring-gray-200">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between hover:shadow-md transition relative overflow-hidden group">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Total Revenue</p>
                        <p class="text-2xl lg:text-3xl font-black text-gray-900">Rs.<?php echo number_format($total_revenue); ?></p>
                    </div>
                    <div class="p-4 rounded-xl bg-green-50 text-green-600 z-10 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between hover:shadow-md transition group">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Pending Orders</p>
                        <p class="text-2xl lg:text-3xl font-black <?php echo $pending_orders > 0 ? 'text-orange-600' : 'text-gray-900'; ?>"><?php echo number_format($pending_orders); ?></p>
                    </div>
                    <div class="p-4 rounded-xl bg-orange-50 text-orange-600 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between hover:shadow-md transition group">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Customers</p>
                        <p class="text-2xl lg:text-3xl font-black text-gray-900"><?php echo number_format($total_customers); ?></p>
                    </div>
                    <div class="p-4 rounded-xl bg-blue-50 text-blue-600 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between hover:shadow-md transition group">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Active Products</p>
                        <p class="text-2xl lg:text-3xl font-black text-gray-900"><?php echo number_format($total_products); ?></p>
                    </div>
                    <div class="p-4 rounded-xl bg-purple-50 text-purple-600 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Revenue Overview</h3>
                    <div class="relative h-72 w-full">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Order Status Breakdown</h3>
                    <div class="relative h-64 w-full flex justify-center">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/80 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            Recent Orders
                        </h3>
                        <a href="/admin/orders/orders.php" class="text-sm text-blue-600 font-bold hover:text-blue-800 transition">View All</a>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white border-b border-gray-50 text-gray-400 text-[10px] uppercase tracking-widest">
                                    <th class="px-6 py-3 font-bold">Order ID</th>
                                    <th class="px-6 py-3 font-bold">Customer</th>
                                    <th class="px-6 py-3 font-bold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if(!empty($recent_orders)): ?>
                                    <?php foreach($recent_orders as $ord): ?>
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="px-6 py-3">
                                            <p class="text-sm font-bold text-gray-900">#<?php echo htmlspecialchars($ord['order_number']); ?></p>
                                            <p class="text-[10px] text-gray-500 mt-0.5"><?php echo date('M d', strtotime($ord['created_at'])); ?></p>
                                        </td>
                                        <td class="px-6 py-3 text-sm font-medium text-gray-700">
                                            <?php echo htmlspecialchars($ord['customer_first_name']); ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded uppercase tracking-wider border <?php echo getBadgeStyle($ord['order_status']); ?>">
                                                <?php echo $ord['order_status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">No orders found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/80 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            New Customers
                        </h3>
                        <a href="/admin/customer/users/users.php" class="text-sm text-blue-600 font-bold hover:text-blue-800 transition">View All</a>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white border-b border-gray-50 text-gray-400 text-[10px] uppercase tracking-widest">
                                    <th class="px-6 py-3 font-bold">User Details</th>
                                    <th class="px-6 py-3 font-bold text-right">Joined</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if(!empty($recent_users)): ?>
                                    <?php foreach($recent_users as $user): ?>
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="px-6 py-3 flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-bold text-xs shrink-0">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($user['username']); ?></p>
                                                <p class="text-[10px] text-gray-500 truncate"><?php echo htmlspecialchars($user['email']); ?></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-right text-xs text-gray-500 font-medium">
                                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="px-6 py-8 text-center text-sm text-gray-500">No users found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <script>
        // 1. Line Chart (Revenue)
        const ctxRev = document.getElementById('revenueChart').getContext('2d');
        const gradientBlue = ctxRev.createLinearGradient(0, 0, 0, 300);
        gradientBlue.addColorStop(0, 'rgba(37, 99, 235, 0.2)'); // tailwind blue-600
        gradientBlue.addColorStop(1, 'rgba(37, 99, 235, 0)');

        new Chart(ctxRev, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_months); ?>,
                datasets: [{
                    label: 'Revenue (Rs)',
                    data: <?php echo json_encode($chart_revenue); ?>,
                    borderColor: '#2563eb', // blue-600
                    backgroundColor: gradientBlue,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4 // Smooth curves
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        padding: 12,
                        titleFont: { family: 'Poppins', size: 13 },
                        bodyFont: { family: 'Poppins', size: 14, weight: 'bold' },
                        callbacks: {
                            label: function(context) {
                                return 'Rs. ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [4, 4], color: '#f3f4f6' }, // gray-100
                        border: { display: false },
                        ticks: { font: { family: 'Poppins' }, color: '#6b7280' }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { font: { family: 'Poppins' }, color: '#6b7280' }
                    }
                }
            }
        });

        // 2. Doughnut Chart (Order Status)
        const ctxStat = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStat, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: [
                        '#f59e0b', // Yellow/Orange (Pending)
                        '#3b82f6', // Blue (Processing)
                        '#6366f1', // Indigo (Ready/Packed)
                        '#a855f7', // Purple (Shipped)
                        '#10b981', // Green (Completed)
                        '#ef4444'  // Red (Cancelled/Refunded)
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { family: 'Poppins', size: 11 },
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 15
                        }
                    }
                }
            }
        });

        // Mobile Sidebar Toggle
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