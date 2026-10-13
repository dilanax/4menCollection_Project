<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) { header("Location: /login"); exit; }

require_once '../../config/db.php';

$message = $_SESSION['swal_msg'] ?? '';
$messageType = $_SESSION['swal_type'] ?? '';
unset($_SESSION['swal_msg'], $_SESSION['swal_type']);

// Fetch PayHere Settings
$stmt = $pdo->prepare("SELECT * FROM payment_gateways WHERE gateway_id = 'payhere' LIMIT 1");
$stmt->execute();
$payhere = $stmt->fetch();

// If record doesn't exist, create initial row
if (!$payhere) {
    $pdo->query("INSERT INTO payment_gateways (gateway_id, display_name, is_active, environment) VALUES ('payhere', 'Pay Online (Card / PayHere)', 0, 'sandbox')");
    $stmt = $pdo->prepare("SELECT * FROM payment_gateways WHERE gateway_id = 'payhere' LIMIT 1");
    $stmt->execute();
    $payhere = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayHere Gateway Configuration | Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, input, select, button { font-family: 'Poppins', sans-serif !important; }
        .toggle-checkbox:checked { right: 0; border-color: #0369a1; }
        .toggle-checkbox:checked + .toggle-label { background-color: #0369a1; }
        .toggle-checkbox { right: 0; z-index: 1; border-color: #e2e8f0; transition: all 0.3s; }
        .toggle-label { width: 3.25rem; height: 1.75rem; background-color: #cbd5e1; border-radius: 9999px; position: relative; cursor: pointer; transition: all 0.3s; }
        .toggle-label::after { content: ''; position: absolute; top: 0.125rem; left: 0.125rem; width: 1.5rem; height: 1.5rem; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
        .toggle-checkbox:checked + .toggle-label::after { transform: translateX(1.5rem); }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-100 text-slate-800 antialiased">
    <?php if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } ?>
    
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">
        <header class="bg-white/95 backdrop-blur-md shadow-xs border-b border-slate-200 px-6 py-4 flex justify-between items-center z-10 sticky top-0">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Payment Methods</span>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">PayHere Gateway Setup</h1>
            </div>
            <a href="payment_controller.php" class="text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-xl transition shadow-xs">
                &larr; Back to Gateways
            </a>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="max-w-2xl mx-auto bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden p-6 sm:p-8 space-y-6">
                
                <form action="payment_action.php" method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="save_payhere">
                    <input type="hidden" name="id" value="<?= (int)$payhere['id']; ?>">

                    <!-- 1. Enable / Disable Toggle Switch -->
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                        <div>
                            <h4 class="text-sm font-extrabold text-slate-900">Enable PayHere Gateway</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Show this payment option on customer checkout page.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span id="status-label" class="text-xs font-black uppercase tracking-wider <?= !empty($payhere['is_active']) ? 'text-sky-700' : 'text-slate-400'; ?>">
                                <?= !empty($payhere['is_active']) ? 'ACTIVE' : 'DISABLED'; ?>
                            </span>
                            <div class="relative inline-block w-14 align-middle select-none">
                                <input type="checkbox" name="is_active" id="is_active_toggle" value="1" <?= !empty($payhere['is_active']) ? 'checked' : ''; ?> onchange="updateStatusText(this)" class="toggle-checkbox absolute block w-7 h-7 rounded-full bg-white border-4 appearance-none cursor-pointer"/>
                                <label for="is_active_toggle" class="toggle-label block overflow-hidden h-7 rounded-full bg-slate-300 cursor-pointer"></label>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Display Name on Checkout -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Checkout Display Name *</label>
                        <input type="text" name="display_name" required value="<?= htmlspecialchars($payhere['display_name'] ?? 'Pay Online (Card / PayHere)'); ?>" placeholder="e.g. Credit / Debit Card (PayHere)" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs font-bold rounded-xl px-4 py-2.5 focus:bg-white focus:border-sky-700 outline-none transition">
                    </div>

                    <!-- 3. Environment Mode (Sandbox vs Live) -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Environment Mode *</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 bg-slate-50 cursor-pointer hover:border-sky-700 transition">
                                <input type="radio" name="environment" value="sandbox" <?= ($payhere['environment'] ?? 'sandbox') === 'sandbox' ? 'checked' : ''; ?> class="text-sky-700 focus:ring-0">
                                <div>
                                    <span class="block text-xs font-extrabold text-slate-800">Sandbox</span>
                                    <span class="block text-[10px] text-slate-400">Testing & Debugging</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 bg-slate-50 cursor-pointer hover:border-sky-700 transition">
                                <input type="radio" name="environment" value="live" <?= ($payhere['environment'] ?? '') === 'live' ? 'checked' : ''; ?> class="text-sky-700 focus:ring-0">
                                <div>
                                    <span class="block text-xs font-extrabold text-slate-800">Live</span>
                                    <span class="block text-[10px] text-slate-400">Production Mode</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 4. Keys Section: Merchant ID & Secret -->
                    <div class="p-5 bg-sky-50/50 rounded-2xl border border-sky-100 space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Merchant ID *
                            </label>
                            <input type="text" name="merchant_id" required value="<?= htmlspecialchars($payhere['merchant_id'] ?? ''); ?>" placeholder="e.g. 121XXXX" class="w-full bg-white border border-slate-300 text-slate-900 font-mono text-xs font-bold rounded-xl px-4 py-2.5 focus:border-sky-700 outline-none transition">
                            <p class="text-[10px] text-slate-400 mt-1">Available under PayHere Dashboard &gt; Integrations.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Merchant Secret *
                            </label>
                            <input type="text" name="merchant_secret" required value="<?= htmlspecialchars($payhere['merchant_secret'] ?? ''); ?>" placeholder="Enter domain merchant secret" class="w-full bg-white border border-slate-300 text-slate-900 font-mono text-xs font-bold rounded-xl px-4 py-2.5 focus:border-sky-700 outline-none transition">
                            <p class="text-[10px] text-slate-400 mt-1">Generated for your domain (<code class="bg-white px-1.5 py-0.5 rounded text-sky-800 font-bold">readershub.loopzglobal.com</code>).</p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-3 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="w-full sm:w-auto bg-sky-700 hover:bg-sky-800 text-white font-bold py-3 px-8 rounded-xl text-xs uppercase tracking-wider transition shadow-md shadow-sky-700/20 active:scale-98">
                            Save PayHere Settings
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <script>
        <?php if($message): ?> 
            Swal.fire({ toast: true, position: 'top-end', icon: '<?= $messageType; ?>', title: '<?= addslashes($message); ?>', showConfirmButton: false, timer: 3000, timerProgressBar: true }); 
        <?php endif; ?>

        function updateStatusText(chk) {
            const lbl = document.getElementById('status-label');
            if (chk.checked) {
                lbl.innerText = 'ACTIVE';
                lbl.className = 'text-xs font-black uppercase tracking-wider text-sky-700';
            } else {
                lbl.innerText = 'DISABLED';
                lbl.className = 'text-xs font-black uppercase tracking-wider text-slate-400';
            }
        }
    </script>
</body>
</html>