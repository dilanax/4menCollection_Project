<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Security Check
$user_role = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($user_role, ['admin', 'shop_admin'])) { 
    header("Location: /login"); 
    exit; 
}

// Database Connection with Absolute Path fallback
$db_path = __DIR__ . '/../../config/db.php';
if (!file_exists($db_path)) {
    $db_path = dirname(__DIR__, 2) . '/config/db.php';
}
if (!file_exists($db_path)) {
    $db_path = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/config/db.php';
}

if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("Database configuration file not found at: " . htmlspecialchars($db_path));
}

// SweetAlert Messages
$message = $_SESSION['swal_msg'] ?? '';
$messageType = $_SESSION['swal_type'] ?? '';
unset($_SESSION['swal_msg'], $_SESSION['swal_type']);

// Auto-create bank_accounts table & seed COD / Bank Transfer if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bank_accounts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `bank_name` VARCHAR(100) NOT NULL,
        `account_name` VARCHAR(150) NOT NULL,
        `account_number` VARCHAR(50) NOT NULL,
        `branch_name` VARCHAR(100) NOT NULL,
        `instructions` TEXT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $existing_gids =$pdo->query("SELECT gateway_id FROM payment_gateways")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    
    if (!in_array('cod', $existing_gids)) {$pdo->exec("INSERT INTO payment_gateways (gateway_id, display_name, is_active, environment) VALUES ('cod', 'Cash On Delivery (COD)', 1, 'live')");
    }
    if (!in_array('bank_transfer', $existing_gids)) {$pdo->exec("INSERT INTO payment_gateways (gateway_id, display_name, is_active, environment) VALUES ('bank_transfer', 'Direct Bank Transfer / Deposit', 1, 'live')");
    }
} catch (Throwable $t) {
    // Suppress auto-seed errors so page always renders
}

// Fetch All Gateways
try {
    $stmt =$pdo->prepare("SELECT * FROM `payment_gateways` ORDER BY `id` ASC");
    $stmt->execute();
    $gateways =$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Settings - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; color: #1f2937; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        
        /* Custom Toggle Switch */
        .toggle-checkbox:checked { right: 0; border-color: #3b82f6; }
        .toggle-checkbox:checked + .toggle-label { background-color: #3b82f6; }
        .toggle-checkbox { right: 0; z-index: 1; border-color: #e5e7eb; transition: all 0.3s; }
        .toggle-label { width: 3rem; height: 1.5rem; background-color: #d1d5db; border-radius: 9999px; position: relative; cursor: pointer; transition: all 0.3s; }
        .toggle-label::after { content: ''; position: absolute; top: 0.125rem; left: 0.125rem; width: 1.25rem; height: 1.25rem; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
        .toggle-checkbox:checked + .toggle-label::after { transform: translateX(1.5rem); }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-gray-100">

    <?php 
    // Safely load sidebar with absolute paths
    if (file_exists(__DIR__ . '/../includes/sidebar.php')) { 
        require_once __DIR__ . '/../includes/sidebar.php'; 
    } elseif (file_exists(__DIR__ . '/../../sidebar.php')) { 
        require_once __DIR__ . '/../../sidebar.php'; 
    } elseif (file_exists(__DIR__ . '/../sidebar.php')) { 
        require_once __DIR__ . '/../sidebar.php'; 
    }
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-open-header" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Payment Gateways & Methods</h1>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            <p class="text-sm text-gray-500 mb-8 font-medium">Enable, disable and configure your store payment methods.</p>

            <div class="w-full space-y-4">
                <?php if (empty($gateways)): ?>
                    <div class="bg-white border border-gray-200 rounded-xl p-8 text-center shadow-sm text-gray-500 font-medium">
                        No payment methods found in the database.
                    </div>
                <?php else: ?>
                    <?php foreach ($gateways as$g): 
                        $gid =$g['gateway_id'] ?? '';
                        $is_payhere = ($gid === 'payhere');
                        $is_bank = ($gid === 'bank_transfer');
                        $is_cod = ($gid === 'cod');
                        $is_active = (int)($g['is_active'] ?? 0);
                    ?>
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 md:p-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 <?= $is_active ? 'border-l-4 border-l-blue-600' : 'opacity-70 bg-gray-50/50'; ?>">
                            
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <h3 class="font-bold text-gray-900 text-lg">
                                        <?= htmlspecialchars($g['display_name'] ?? ''); ?>
                                    </h3>
                                    <?php if ($is_payhere): ?>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase <?= ($g['environment'] ?? '') == 'live' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'; ?>">
                                            <?= ($g['environment'] ?? '') == 'live' ? 'LIVE' : 'SANDBOX'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-400 font-medium">Gateway Code: <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 text-xs"><?= htmlspecialchars($gid); ?></span></p>
                            </div>

                            <div class="flex items-center gap-4 shrink-0 border-t border-gray-100 sm:border-0 pt-4 sm:pt-0 justify-between sm:justify-end">
                                
                                <?php if ($is_bank): ?>
                                    <a href="bank_config.php" class="text-sm font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-4 py-2 rounded-lg transition shadow-sm">
                                        Manage Bank Accounts
                                    </a>
                                <?php elseif ($is_payhere): ?>
                                    <a href="payhere_config.php" class="text-sm font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-4 py-2 rounded-lg transition shadow-sm">
                                        Configure Keys
                                    </a>
                                <?php elseif ($is_cod): ?>
                                    <span class="text-xs font-semibold text-gray-400 bg-gray-50 border border-gray-200 px-3 py-2 rounded-lg">
                                        Ready to use
                                    </span>
                                <?php endif; ?>

                                <form action="payment_action.php" method="POST" class="flex items-center gap-3">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= (int)($g['id'] ?? 0); ?>">
                                    <input type="hidden" name="current_status" value="<?= $is_active; ?>">
                                    
                                    <div class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in mt-1">
                                        <input type="checkbox" id="toggle_<?= (int)($g['id'] ?? 0); ?>" <?= $is_active ? 'checked' : ''; ?> onchange="this.form.submit()" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"/>
                                        <label for="toggle_<?= (int)($g['id'] ?? 0); ?>" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                    <span class="text-xs font-black <?= $is_active ? 'text-blue-600' : 'text-gray-400'; ?> w-8">
                                        <?= $is_active ? 'ON' : 'OFF'; ?>
                                    </span>
                                </form>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        <?php if ($message): ?>
            Swal.fire({ toast: true, position: 'top-end', icon: '<?= $messageType; ?>', title: '<?= addslashes($message); ?>', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        <?php endif; ?>

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