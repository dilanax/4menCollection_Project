<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Security Check (No pipe operators used)
if (empty($_SESSION['user_id'])) {
    header("Location: /login");
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';$allowed_roles = array('admin', 'shop_admin');
if (!in_array($user_role,$allowed_roles)) {
    header("Location: /login");
    exit;
}

// Database Connection
require_once '../../config/db.php';

// SweetAlert Messages
$message =$_SESSION['swal_msg'] ?? '';
$messageType =$_SESSION['swal_type'] ?? '';
unset($_SESSION['swal_msg'],$_SESSION['swal_type']);

// Fetch Bank Accounts List
$accounts = array();
try {
    $stmt =$pdo->prepare("SELECT * FROM `bank_accounts` ORDER BY `id` DESC");
    $stmt->execute();
    $accounts =$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Auto-seed default Pan Asia Bank account if table is empty
if (empty($accounts)) {
    try {
        $ins =$pdo->prepare("INSERT INTO `bank_accounts` (bank_name, account_name, account_number, branch_name, instructions, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $ins->execute(array(
            'Pan Asia Bank',
            'Readers Hub (Pvt) Ltd',
            '106211100130',
            'Balangoda branch',
            'Please WhatsApp deposit slip with Order ID to 070 347 27 27'
        ));
        
        $stmt =$pdo->prepare("SELECT * FROM `bank_accounts` ORDER BY `id` DESC");
        $stmt->execute();
        $accounts =$stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Check Edit Mode
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_acc = null;
if ($edit_id > 0) {
    try {
        $e_stmt =$pdo->prepare("SELECT * FROM `bank_accounts` WHERE `id` = ?");
        $e_stmt->execute(array($edit_id));
        $edit_acc =$e_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {$edit_acc = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Accounts Configuration - ReadersHub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; color: #1f2937; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-gray-100">

    <?php 
    if (file_exists('../includes/sidebar.php')) { 
        require_once '../includes/sidebar.php'; 
    } elseif (file_exists('../../sidebar.php')) { 
        require_once '../../sidebar.php'; 
    } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0">
            <div class="flex items-center gap-4">
                <a href="payment_controller.php" class="text-xs font-bold text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                    &larr; Back to Gateways
                </a>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Direct Bank Transfer Accounts</h1>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left 5 Columns: Add / Edit Bank Account Form -->
                <div class="lg:col-span-5 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                    <h2 class="text-sm font-black text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-3">
                        <?= $edit_acc ? 'Edit Bank Account' : '+ Add New Bank Account'; ?>
                    </h2>

                    <form action="payment_action.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="save_bank_account">
                        <input type="hidden" name="account_id" value="<?= $edit_acc ? (int)$edit_acc['id'] : 0; ?>">

                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Bank Name *</label>
                            <input type="text" name="bank_name" required value="<?= htmlspecialchars($edit_acc['bank_name'] ?? 'Pan Asia Bank'); ?>" placeholder="e.g. Pan Asia Bank" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-gray-800 outline-none focus:border-blue-600 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Account Holder Name *</label>
                            <input type="text" name="account_name" required value="<?= htmlspecialchars($edit_acc['account_name'] ?? 'Readers Hub (Pvt) Ltd'); ?>" placeholder="e.g. Readers Hub (Pvt) Ltd" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-gray-800 outline-none focus:border-blue-600 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Account Number *</label>
                            <input type="text" name="account_number" required value="<?= htmlspecialchars($edit_acc['account_number'] ?? '106211100130'); ?>" placeholder="e.g. 106211100130" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-mono text-gray-800 outline-none focus:border-blue-600 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Branch Name *</label>
                            <input type="text" name="branch_name" required value="<?= htmlspecialchars($edit_acc['branch_name'] ?? 'Balangoda branch'); ?>" placeholder="e.g. Balangoda branch" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-gray-800 outline-none focus:border-blue-600 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Deposit Instructions (Optional)</label>
                            <textarea name="instructions" rows="2" placeholder="e.g. Please WhatsApp your deposit slip with Order ID to 070 347 27 27" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 outline-none focus:border-blue-600 transition"><?= htmlspecialchars($edit_acc['instructions'] ?? 'Please WhatsApp your deposit slip with Order ID to 070 347 27 27'); ?></textarea>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition shadow-sm active:scale-98">
                                <?= $edit_acc ? 'Update Account' : 'Save Account'; ?>
                            </button>
                            <?php if ($edit_acc): ?>
                                <a href="bank_config.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold px-4 py-2.5 rounded-xl text-xs transition">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Right 7 Columns: Active Accounts List -->
                <div class="lg:col-span-7 space-y-4">
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
                            <h2 class="text-sm font-black text-gray-900 uppercase tracking-wider">Active Bank Accounts</h2>
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-md"><?= count($accounts); ?> Account(s)</span>
                        </div>

                        <?php if (empty($accounts)): ?>
                            <div class="text-center py-8 text-gray-400 text-xs border-2 border-dashed border-gray-200 rounded-xl bg-gray-50">
                                No bank accounts configured yet. Add your primary bank account on the left.
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($accounts as$acc): ?>
                                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <h3 class="font-extrabold text-gray-900 text-sm"><?= htmlspecialchars($acc['bank_name']); ?></h3>
                                                <span class="text-[10px] font-bold text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded"><?= htmlspecialchars($acc['branch_name']); ?></span>
                                            </div>
                                            <p class="text-xs font-mono font-bold text-blue-600">Acc: <?= htmlspecialchars($acc['account_number']); ?></p>
                                            <p class="text-[11px] text-gray-500">Name: <?= htmlspecialchars($acc['account_name']); ?></p>
                                        </div>

                                        <div class="flex items-center gap-2 shrink-0 border-t sm:border-0 border-gray-200 pt-2 sm:pt-0">
                                            <a href="bank_config.php?edit=<?= $acc['id']; ?>" class="px-3 py-1.5 text-blue-600 bg-white border border-gray-200 hover:bg-blue-50 rounded-lg text-xs font-bold transition">
                                                Edit
                                            </a>
                                            <form action="payment_action.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this bank account?');">
                                                <input type="hidden" name="action" value="delete_bank_account">
                                                <input type="hidden" name="id" value="<?= $acc['id']; ?>">
                                                <button type="submit" class="px-3 py-1.5 text-red-600 bg-white border border-gray-200 hover:bg-red-50 rounded-lg text-xs font-bold transition">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        <?php if ($message): ?>
            Swal.fire({ toast: true, position: 'top-end', icon: '<?= $messageType; ?>', title: '<?= addslashes($message); ?>', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        <?php endif; ?>
    </script>
</body>
</html>