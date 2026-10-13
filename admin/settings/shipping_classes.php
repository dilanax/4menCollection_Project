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

// Edit Mode Data
$edit_mode = false;
$edit_class = null;
$existing_conditions = [];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM shipping_classes WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_class = $stmt->fetch();
    
    if ($edit_class) {
        $edit_mode = true;
        if (!empty($edit_class['cost_conditions'])) {
            $existing_conditions = json_decode($edit_class['cost_conditions'], true);
        }
    }
}

// Fetch All Classes
try {
    $stmt = $pdo->query("
        SELECT sc.*, (SELECT COUNT(id) FROM products p WHERE p.shipping_class_id = sc.id) as product_count 
        FROM shipping_classes sc 
        ORDER BY sc.id DESC
    ");
    $classes = $stmt->fetchAll();
} catch (Exception $e) {
    $classes = []; // Table might not exist yet
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Classes - Admin Panel</title>
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
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Shipping Classes</h1>
            </div>
            <div class="flex gap-2 bg-gray-100 p-1 rounded-lg border border-gray-200 shadow-inner">
                <a href="shipping_zones.php" class="text-gray-500 hover:text-gray-900 px-4 py-1.5 rounded-md font-bold text-sm transition">Zones</a>
                <a href="shipping_classes.php" class="bg-white text-blue-600 shadow-sm border border-gray-200 px-4 py-1.5 rounded-md font-bold text-sm transition">Classes</a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            <p class="text-sm text-gray-500 mb-6">Manage complex shipping costs based on item quantities. These apply as extra charges on top of standard zone methods.</p>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
                
                <div class="lg:col-span-4">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 sticky top-8">
                        <h2 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-100 pb-3">
                            <?php echo $edit_mode ? 'Edit Class' : 'Add New Class'; ?>
                        </h2>
                        
                        <form action="shipping_classes_action.php" method="POST" id="classForm">
                            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update_class' : 'add_class'; ?>">
                            <?php if($edit_mode): ?>
                                <input type="hidden" name="class_id" value="<?php echo $edit_class['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Class Name <span class="text-red-500">*</span></label>
                                <input type="text" name="class_name" required 
                                       value="<?php echo $edit_mode ? htmlspecialchars($edit_class['class_name']) : ''; ?>" 
                                       placeholder="e.g. Heavy Items" 
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Base/Default Cost (Rs.)</label>
                                <input type="number" step="0.01" name="additional_cost" required 
                                       value="<?php echo $edit_mode ? htmlspecialchars($edit_class['additional_cost']) : '0.00'; ?>"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 font-bold rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                                <p class="text-[10px] font-bold text-gray-500 mt-1">Applied if no quantity rules below are met.</p>
                            </div>

                            <div class="mb-6 border-t border-gray-100 pt-5">
                                <div class="flex justify-between items-center mb-3">
                                    <label class="block text-sm font-bold text-blue-700">Quantity Rules</label>
                                    <button type="button" onclick="addConditionRow()" class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-lg font-bold transition">
                                        + Add Rule
                                    </button>
                                </div>
                                <p class="text-[10px] font-bold text-gray-500 mb-4 bg-gray-50 p-2 rounded border border-gray-200">E.g., Qty 1-3 = Rs. 350. Qty 4-10 = Rs. 650. (Leave Max empty for infinity)</p>
                                
                                <div id="conditions-container" class="space-y-3">
                                    <?php if($edit_mode && !empty($existing_conditions)): ?>
                                        <?php foreach($existing_conditions as $index => $cond): ?>
                                            <div class="flex gap-2 items-center condition-row bg-white p-3 rounded-lg border border-gray-200 shadow-sm">
                                                <input type="number" name="qty_min[]" placeholder="Min" value="<?php echo $cond['min']; ?>" required class="w-1/4 bg-gray-50 border border-gray-300 text-gray-900 rounded px-2 py-1.5 text-xs font-bold focus:ring-blue-500 text-center">
                                                <span class="text-gray-400 text-xs font-bold">-</span>
                                                <input type="number" name="qty_max[]" placeholder="Max" value="<?php echo $cond['max'] > 0 ? $cond['max'] : ''; ?>" class="w-1/4 bg-gray-50 border border-gray-300 text-gray-900 rounded px-2 py-1.5 text-xs font-bold focus:ring-blue-500 text-center" title="Leave blank for Infinity">
                                                <span class="text-gray-400 text-xs font-bold">= Rs.</span>
                                                <input type="number" step="0.01" name="cond_cost[]" placeholder="Cost" value="<?php echo $cond['cost']; ?>" required class="w-1/3 bg-gray-50 border border-gray-300 text-blue-700 rounded px-2 py-1.5 text-xs font-bold focus:ring-blue-500">
                                                <button type="button" onclick="this.parentElement.remove()" class="w-8 h-8 flex items-center justify-center text-red-500 hover:bg-red-50 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-4 border-t border-gray-100">
                                <?php if($edit_mode): ?>
                                    <a href="shipping_classes.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-center border border-gray-300 font-bold py-2.5 px-4 rounded-lg transition text-sm">Cancel</a>
                                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg transition shadow-md text-sm">Update</button>
                                <?php else: ?>
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg transition shadow-md text-sm">Save Class</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-8">
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden desktop-table">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                                    <th class="px-6 py-4 font-bold">Class Name</th>
                                    <th class="px-6 py-4 font-bold">Base Cost</th>
                                    <th class="px-6 py-4 font-bold">Quantity Rules</th>
                                    <th class="px-6 py-4 font-bold text-center">Linked Products</th>
                                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <?php if(empty($classes)): ?>
                                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No shipping classes found. Please add one.</td></tr>
                                <?php else: ?>
                                    <?php foreach($classes as $c): ?>
                                        <tr class="hover:bg-gray-50 transition group <?php echo ($edit_mode && $edit_class['id'] == $c['id']) ? 'bg-blue-50 border-l-4 border-l-blue-600' : ''; ?>">
                                            <td class="px-6 py-4 font-bold text-gray-900 text-base">
                                                <?php echo htmlspecialchars($c['class_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 font-bold text-gray-600">
                                                + Rs. <?php echo number_format($c['additional_cost'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 text-xs text-gray-600">
                                                <?php 
                                                // FIXED: Check if empty before decoding
                                                $json_string = $c['cost_conditions'] ?? '';
                                                $conds = !empty($json_string) ? json_decode($json_string, true) : [];
                                                
                                                if(!empty($conds)) {
                                                    echo "<div class='space-y-1.5'>";
                                                    foreach($conds as $cn) {
                                                        $maxLabel = $cn['max'] > 0 ? $cn['max'] : '∞';
                                                        $costLabel = $cn['cost'] == 0 ? 'Free' : 'Rs. '.number_format($cn['cost'], 2);
                                                        echo "<span class='inline-block bg-blue-50 text-blue-700 border border-blue-200 px-2 py-1 rounded font-medium'>Qty {$cn['min']} to {$maxLabel} = <strong>{$costLabel}</strong></span><br>";
                                                    }
                                                    echo "</div>";
                                                } else {
                                                    echo "<span class='text-gray-400 italic'>No rules set</span>";
                                                }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="bg-gray-100 border border-gray-200 text-gray-600 text-xs font-bold px-3 py-1 rounded-full">
                                                    <?php echo $c['product_count']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex justify-end gap-2 items-center opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <a href="shipping_classes.php?edit=<?php echo $c['id']; ?>" class="text-xs bg-gray-50 text-gray-600 hover:bg-gray-200 border border-gray-200 px-3 py-1.5 rounded font-bold transition">Edit</a>
                                                    <button onclick="confirmDelete(<?php echo $c['id']; ?>)" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-100 px-3 py-1.5 rounded font-bold transition">Delete</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mobile-card-grid pb-10">
                        <?php if(empty($classes)): ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-8 text-center shadow-sm text-gray-500">No classes found.</div>
                        <?php else: ?>
                            <?php foreach($classes as $c): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm flex flex-col gap-4 <?php echo ($edit_mode && $edit_class['id'] == $c['id']) ? 'border-l-4 border-l-blue-600' : ''; ?>">
                                    <div class="flex justify-between items-start border-b border-gray-100 pb-3">
                                        <h3 class="font-bold text-gray-900 text-lg mb-1"><?php echo htmlspecialchars($c['class_name']); ?></h3>
                                        <span class="bg-gray-100 border border-gray-200 text-gray-600 text-[10px] font-bold px-2 py-1 rounded">Linked: <?php echo $c['product_count']; ?></span>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Base Cost</p>
                                        <p class="font-bold text-gray-900">Rs. <?php echo number_format($c['additional_cost'], 2); ?></p>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Quantity Rules</p>
                                        <?php 
                                        // FIXED: Check if empty before decoding
                                        $json_string = $c['cost_conditions'] ?? '';
                                        $conds = !empty($json_string) ? json_decode($json_string, true) : [];

                                        if(!empty($conds)) {
                                            echo "<div class='space-y-1.5 text-xs'>";
                                            foreach($conds as $cn) {
                                                $maxLabel = $cn['max'] > 0 ? $cn['max'] : '∞';
                                                $costLabel = $cn['cost'] == 0 ? 'Free' : 'Rs. '.number_format($cn['cost'], 2);
                                                echo "<span class='block bg-white border border-gray-200 px-2 py-1 rounded text-gray-700'>Qty {$cn['min']} - {$maxLabel} <span class='float-right font-bold text-blue-600'>{$costLabel}</span></span>";
                                            }
                                            echo "</div>";
                                        } else {
                                            echo "<p class='text-xs text-gray-400 italic'>No rules set</p>";
                                        }
                                        ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="shipping_classes.php?edit=<?php echo $c['id']; ?>" class="flex-1 text-center text-xs bg-gray-50 text-gray-600 hover:bg-gray-200 border border-gray-200 px-3 py-2 rounded-lg font-bold transition">Edit</a>
                                        <button onclick="confirmDelete(<?php echo $c['id']; ?>)" class="flex-1 text-center text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-100 px-3 py-2 rounded-lg font-bold transition">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <form id="deleteForm" action="shipping_classes_action.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_class">
        <input type="hidden" name="class_id" id="delete_id">
    </form>

    <script>
        <?php if($message): ?>
            Swal.fire({ toast: true, position: 'top-end', icon: '<?php echo $messageType; ?>', title: '<?php echo addslashes($message); ?>', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        <?php endif; ?>

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Class?',
                text: "Products using this class will revert to Standard Shipping. This cannot be undone.",
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

        function addConditionRow() {
            const container = document.getElementById('conditions-container');
            const html = `
                <div class="flex gap-2 items-center condition-row bg-white p-3 rounded-lg border border-gray-200 shadow-sm mt-2">
                    <input type="number" name="qty_min[]" placeholder="Min" required class="w-1/4 bg-gray-50 border border-gray-300 text-gray-900 rounded px-2 py-1.5 text-xs font-bold focus:ring-blue-500 text-center">
                    <span class="text-gray-400 text-xs font-bold">-</span>
                    <input type="number" name="qty_max[]" placeholder="Max" class="w-1/4 bg-gray-50 border border-gray-300 text-gray-900 rounded px-2 py-1.5 text-xs font-bold focus:ring-blue-500 text-center" title="Leave blank for Infinity">
                    <span class="text-gray-400 text-xs font-bold">= Rs.</span>
                    <input type="number" step="0.01" name="cond_cost[]" placeholder="Cost" required class="w-1/3 bg-gray-50 border border-gray-300 text-blue-700 rounded px-2 py-1.5 text-xs font-bold focus:ring-blue-500">
                    <button type="button" onclick="this.parentElement.remove()" class="w-8 h-8 flex items-center justify-center text-red-500 hover:bg-red-50 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
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