<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    header("Location: /login");
    exit;
}

require_once '../../config/db.php';

// SweetAlert Messages
$message = '';
$messageType = '';
if (isset($_SESSION['swal_msg'])) {
    $message = $_SESSION['swal_msg'];
    $messageType = $_SESSION['swal_type'];
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
}

// Zone ID Validation
$zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;
if ($zone_id === 0) {
    header("Location: shipping_zones.php");
    exit;
}

// Fetch Zone Details
$zone_stmt = $pdo->prepare("SELECT zone_name FROM shipping_zones WHERE id = ?");
$zone_stmt->execute([$zone_id]);
$zone = $zone_stmt->fetch();
if (!$zone) {
    header("Location: shipping_zones.php");
    exit;
}

// Edit Mode Data
$edit_mode = false;
$edit_method = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM shipping_methods WHERE id = ? AND zone_id = ?");
    $stmt->execute([$edit_id, $zone_id]);
    $edit_method = $stmt->fetch();
    if ($edit_method) { $edit_mode = true; }
}

// Fetch All Methods for this Zone
$stmt = $pdo->prepare("SELECT * FROM shipping_methods WHERE zone_id = ? ORDER BY id ASC");
$stmt->execute([$zone_id]);
$methods = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Methods - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; color: #1f2937; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        
        @media (max-width: 1023px) {
            .mobile-card-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
            .desktop-table { display: none; }
        }
        @media (min-width: 1024px) {
            .mobile-card-grid { display: none; }
            .desktop-table { display: table; width: 100%; }
        }

        /* Custom Toggle Switch */
        .toggle-checkbox:checked {
            right: 0;
            border-color: #3b82f6;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #3b82f6;
        }
        .toggle-checkbox {
            right: 0;
            z-index: 1;
            border-color: #e5e7eb;
            transition: all 0.3s;
        }
        .toggle-label {
            width: 3rem;
            height: 1.5rem;
            background-color: #d1d5db;
            border-radius: 9999px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }
        .toggle-label::after {
            content: '';
            position: absolute;
            top: 0.125rem;
            left: 0.125rem;
            width: 1.25rem;
            height: 1.25rem;
            background-color: white;
            border-radius: 50%;
            transition: all 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .toggle-checkbox:checked + .toggle-label::after {
            transform: translateX(1.5rem);
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-gray-100">

    <?php 
    if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-open-header" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Shipping Methods</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="shipping_zones.php" class="text-sm font-bold text-gray-600 hover:text-gray-900 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition whitespace-nowrap flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Zones
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            
            <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-2xl font-black text-gray-900">Manage Methods</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Zone: <span class="text-blue-600 font-bold"><?php echo htmlspecialchars($zone['zone_name']); ?></span></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                
                <div class="lg:col-span-1">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 sticky top-8">
                        <h2 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-100 pb-3">
                            <?php echo $edit_mode ? 'Edit Method' : 'Add New Method'; ?>
                        </h2>
                        
                        <form action="shipping_methods_action.php" method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update_method' : 'add_method'; ?>">
                            <input type="hidden" name="zone_id" value="<?php echo $zone_id; ?>">
                            <?php if($edit_mode): ?>
                                <input type="hidden" name="method_id" value="<?php echo $edit_method['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Method Name <span class="text-red-500">*</span></label>
                                <input type="text" name="method_name" required 
                                       value="<?php echo $edit_mode ? htmlspecialchars($edit_method['method_name']) : ''; ?>" 
                                       placeholder="e.g. Standard Delivery, Free Shipping" 
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Cost (Rs.) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="cost" required 
                                       value="<?php echo $edit_mode ? htmlspecialchars($edit_method['cost']) : '0.00'; ?>"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 font-bold rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                                <p class="text-[10px] font-bold text-gray-500 mt-1">Enter 0 for Free Shipping.</p>
                            </div>

                            <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                                <label class="block text-sm font-bold text-blue-800 mb-1.5">Minimum Order Amount (Rs.)</label>
                                <input type="number" step="0.01" name="min_order_amount" 
                                       value="<?php echo ($edit_mode && $edit_method['min_order_amount'] !== null) ? htmlspecialchars($edit_method['min_order_amount']) : ''; ?>"
                                       placeholder="Leave empty if not applicable" 
                                       class="w-full bg-white border border-blue-200 text-gray-900 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                                <p class="text-[10px] font-bold text-blue-600 mt-1.5">E.g. If you set 5000, this method only shows up if the cart total is above 5000.</p>
                            </div>

                            <div class="flex gap-3 pt-4 border-t border-gray-100">
                                <?php if($edit_mode): ?>
                                    <a href="shipping_methods.php?zone_id=<?php echo $zone_id; ?>" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-center border border-gray-300 font-bold py-2.5 px-4 rounded-lg transition text-sm">Cancel</a>
                                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg transition shadow-md text-sm">Update</button>
                                <?php else: ?>
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg transition shadow-md text-sm">Save Method</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden desktop-table">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                                    <th class="px-6 py-4 font-bold">Method Name</th>
                                    <th class="px-6 py-4 font-bold">Cost</th>
                                    <th class="px-6 py-4 font-bold">Conditions</th>
                                    <th class="px-6 py-4 font-bold text-center">Status</th>
                                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <?php if(empty($methods)): ?>
                                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No shipping methods found for this zone. Add one from the left.</td></tr>
                                <?php else: ?>
                                    <?php foreach($methods as $method): ?>
                                        <tr class="hover:bg-gray-50 transition group <?php echo $method['is_active'] ? '' : 'opacity-60 bg-gray-50'; ?> <?php echo ($edit_mode && $edit_method['id'] == $method['id']) ? 'bg-blue-50 border-l-4 border-l-blue-600 opacity-100' : ''; ?>">
                                            <td class="px-6 py-4">
                                                <p class="font-bold text-gray-900"><?php echo htmlspecialchars($method['method_name']); ?></p>
                                            </td>
                                            <td class="px-6 py-4 font-black text-gray-900 text-sm">
                                                <?php echo $method['cost'] == 0 ? '<span class="text-green-600">Free</span>' : 'Rs. ' . number_format($method['cost'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if($method['min_order_amount'] > 0): ?>
                                                    <span class="text-[10px] bg-blue-50 border border-blue-200 text-blue-700 px-2.5 py-1 rounded font-bold whitespace-nowrap">
                                                        Min: Rs. <?php echo number_format($method['min_order_amount'], 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs font-medium text-gray-400 italic">- None -</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <form action="shipping_methods_action.php" method="POST" class="inline-block flex justify-center">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="zone_id" value="<?php echo $zone_id; ?>">
                                                    <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $method['is_active']; ?>">
                                                    
                                                    <div class="relative inline-block w-11 mr-2 align-middle select-none transition duration-200 ease-in">
                                                        <input type="checkbox" name="toggle" id="toggle_<?php echo $method['id']; ?>" <?php echo $method['is_active'] ? 'checked' : ''; ?> onchange="this.form.submit()" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"/>
                                                        <label for="toggle_<?php echo $method['id']; ?>" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                                    </div>
                                                </form>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex justify-end gap-2 items-center opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <a href="shipping_methods.php?zone_id=<?php echo $zone_id; ?>&edit=<?php echo $method['id']; ?>" class="text-xs bg-gray-50 text-gray-600 hover:bg-gray-200 border border-gray-200 px-3 py-1.5 rounded font-bold transition">Edit</a>
                                                    <button onclick="confirmDelete(<?php echo $method['id']; ?>)" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-100 px-3 py-1.5 rounded font-bold transition">Delete</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mobile-card-grid pb-10">
                        <?php if(empty($methods)): ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-8 text-center shadow-sm text-gray-500">No shipping methods found.</div>
                        <?php else: ?>
                            <?php foreach($methods as $method): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm flex flex-col gap-4 <?php echo $method['is_active'] ? '' : 'opacity-60 bg-gray-50'; ?> <?php echo ($edit_mode && $edit_method['id'] == $method['id']) ? 'border-l-4 border-l-blue-600 opacity-100' : ''; ?>">
                                    
                                    <div class="flex justify-between items-start border-b border-gray-100 pb-3">
                                        <div>
                                            <h3 class="font-bold text-gray-900 text-lg mb-1"><?php echo htmlspecialchars($method['method_name']); ?></h3>
                                            <p class="font-black text-gray-900 text-sm"><?php echo $method['cost'] == 0 ? '<span class="text-green-600">Free Shipping</span>' : 'Rs. ' . number_format($method['cost'], 2); ?></p>
                                        </div>
                                        <form action="shipping_methods_action.php" method="POST">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="zone_id" value="<?php echo $zone_id; ?>">
                                            <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $method['is_active']; ?>">
                                            
                                            <div class="relative inline-block w-11 align-middle select-none transition duration-200 ease-in">
                                                <input type="checkbox" id="toggle_m_<?php echo $method['id']; ?>" <?php echo $method['is_active'] ? 'checked' : ''; ?> onchange="this.form.submit()" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"/>
                                                <label for="toggle_m_<?php echo $method['id']; ?>" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Conditions</p>
                                        <?php if($method['min_order_amount'] > 0): ?>
                                            <span class="text-[10px] bg-blue-50 border border-blue-200 text-blue-700 px-2 py-1 rounded font-bold">Min Order: Rs. <?php echo number_format($method['min_order_amount'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-medium text-gray-400 italic">None</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex gap-2 border-t border-gray-100 pt-3">
                                        <a href="shipping_methods.php?zone_id=<?php echo $zone_id; ?>&edit=<?php echo $method['id']; ?>" class="flex-1 text-center text-xs bg-gray-50 text-gray-600 hover:bg-gray-200 border border-gray-200 px-3 py-2 rounded-lg font-bold transition">Edit</a>
                                        <button onclick="confirmDelete(<?php echo $method['id']; ?>)" class="flex-1 text-center text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-100 px-3 py-2 rounded-lg font-bold transition">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 p-4 bg-blue-50 border border-blue-100 rounded-xl flex items-start gap-3 shadow-sm">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-sm font-medium text-blue-800 leading-relaxed">
                            <strong class="font-bold">How it works at Checkout:</strong> If a customer's address falls into this zone, the system will show all <strong>Active</strong> methods here. If a method has a "Minimum Order Amount" (e.g. 5000), it will only be visible to the customer if their cart subtotal is greater than or equal to that amount.
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <form id="deleteForm" action="shipping_methods_action.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_method">
        <input type="hidden" name="zone_id" value="<?php echo $zone_id; ?>">
        <input type="hidden" name="method_id" id="delete_id">
    </form>

    <script>
        <?php if($message): ?>
            Swal.fire({ toast: true, position: 'top-end', icon: '<?php echo $messageType; ?>', title: '<?php echo addslashes($message); ?>', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        <?php endif; ?>

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Method?',
                text: "This shipping method will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#4b5563',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

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