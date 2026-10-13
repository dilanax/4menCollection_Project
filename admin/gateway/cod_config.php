<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) { header("Location: /login"); exit; }

require_once '../../config/db.php';

$message = $_SESSION['swal_msg'] ?? '';
$messageType = $_SESSION['swal_type'] ?? '';
unset($_SESSION['swal_msg'], $_SESSION['swal_type']);

$stmt = $pdo->prepare("SELECT * FROM payment_gateways WHERE gateway_id = 'cod' LIMIT 1");
$stmt->execute();
$cod = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash On Delivery Settings | ReadersHub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body, * { font-family: 'Poppins', sans-serif !important; }</style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-slate-800 antialiased">

    <?php 
    if(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; }
    elseif(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">
        <header class="bg-white/95 shadow-xs border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center z-10 sticky top-0">
            <div class="flex items-center gap-3">
                <a href="payment_controller.php" class="text-xs font-bold text-slate-500 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl transition">&larr; Back</a>
                <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900">Cash On Delivery (COD) Settings</h1>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="max-w-xl mx-auto bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                <form action="payment_action.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="save_cod">

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Display Title at Checkout</label>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($cod['display_name'] ?? 'Cash On Delivery (COD)'); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 outline-none focus:border-sky-700">
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?= ($cod['is_active'] ?? 1) ? 'checked' : ''; ?> class="w-4 h-4 text-sky-700 rounded border-slate-300">
                        <label for="is_active" class="text-xs font-bold text-slate-800 cursor-pointer">Enable Cash On Delivery</label>
                    </div>

                    <button type="submit" class="w-full bg-sky-700 hover:bg-sky-800 text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition shadow-md shadow-sky-700/20">
                        Save Settings
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        <?php if($message): ?>
            Swal.fire({ toast: true, position: 'top-end', icon: '<?= $messageType; ?>', title: '<?= addslashes($message); ?>', showConfirmButton: false, timer: 3000 });
        <?php endif; ?>
    </script>
</body>
</html>