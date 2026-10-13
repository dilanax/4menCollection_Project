<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection with Fallback
if (file_exists('config/db.php')) {
    require_once 'config/db.php';
} elseif (file_exists(__DIR__ . '/../../config/db.php')) {
    require_once __DIR__ . '/../../config/db.php';
}

// Cart එක හිස් නම් Shop එකට redirect කිරීම
if (empty($_SESSION['cart'])) {
    echo "<script>window.location.href = '/shop';</script>";
    exit;
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// 1. AJAX Actions (Shipping calculation & Order Placement)
require_once __DIR__ . '/checkout/ajax_handler.php';

// 2. Fetch All Active Payment Gateways
$active_gateways = [];
$active_gateway_ids = [];
$payhere_gateway = null;
$is_payhere_active = false;

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $gw_stmt = $pdo->query("SELECT * FROM payment_gateways WHERE is_active = 1 ORDER BY id ASC");
        if ($gw_stmt) {
            $active_gateways = $gw_stmt->fetchAll(PDO::FETCH_ASSOC);
            $active_gateway_ids = array_column($active_gateways, 'gateway_id');
        }

        foreach ($active_gateways as $gw) {
            if ($gw['gateway_id'] === 'payhere' && !empty($gw['merchant_id'])) {
                $payhere_gateway = $gw;
                $is_payhere_active = true;
                break;
            }
        }
    }
} catch (Exception $e) {
    $active_gateways = [];
    $active_gateway_ids = [];
    $is_payhere_active = false;
}

// 3. Ensure Pan Asia Bank Account exists & Fetch Active Bank Accounts
$active_bank_accounts = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $b_stmt = $pdo->query("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC");
        if ($b_stmt) {
            $active_bank_accounts = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    $active_bank_accounts = [];
}

// 4. Fetch Cart Items & Initial Subtotal for UI
$cart_items = [];
$initial_subtotal = 0;

foreach ($_SESSION['cart'] as $key => $item) {
    $pid = (int)$item['product_id'];
    $vid = (int)$item['variation_id'];
    $qty = (int)$item['quantity'];

    $stmt = $pdo->prepare("SELECT name, main_image, regular_price, sale_price FROM products WHERE id = ?");
    $stmt->execute([$pid]);
    $p = $stmt->fetch();

    if ($p) {
        $price = $p['sale_price'] > 0 ? $p['sale_price'] : $p['regular_price'];
        $var_name = '';
        if ($vid > 0) {
            $v_stmt = $pdo->prepare("SELECT variation_name, regular_price, sale_price FROM product_variations WHERE id = ?");
            $v_stmt->execute([$vid]);
            $v = $v_stmt->fetch();
            if ($v) {
                $price = $v['sale_price'] > 0 ? $v['sale_price'] : $v['regular_price'];
                $var_name = $v['variation_name'];
            }
        }
        $item_total = $price * $qty;
        $initial_subtotal += $item_total;
        
        $cart_items[] = [
            'name'           => $p['name'],
            'variation_name' => $var_name,
            'qty'            => $qty,
            'total'          => $item_total
        ];
    }
}
?>

<!-- Load SweetAlert2 Library explicitly for checkout -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.title = "Checkout | ReadersHub";
</script>

<!-- Outer Full-Width Container -->
<div class="w-full bg-[#f8fafc] py-6 sm:py-10 font-sans min-h-screen relative" style="font-family: 'Poppins', sans-serif;">
    
    <!-- Success Confirmation Modal -->
    <?php require_once __DIR__ . '/checkout/modal.php'; ?>

    <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 2xl:px-16">
        
        <!-- Breadcrumbs & Heading -->
        <div class="w-full mb-6 sm:mb-8 border-b border-slate-200 pb-4">
            <nav class="flex items-center text-xs font-semibold text-slate-400 mb-2 gap-2">
                <a href="/" class="hover:text-sky-700 transition-colors">Home</a>
                <span>/</span>
                <a href="/cart" class="hover:text-sky-700 transition-colors">Shopping Cart</a>
                <span>/</span>
                <span class="text-sky-700 font-bold">Checkout</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">Order Checkout</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Provide your delivery information and complete your order</p>
        </div>

        <!-- Returning Customer Notice Banner -->
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="w-full mb-8 bg-sky-50 border border-sky-200/80 text-sky-900 p-4 sm:p-5 rounded-2xl sm:rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between shadow-xs gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-sky-100 text-sky-700 flex items-center justify-center shrink-0 border border-sky-200">
                        <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm font-extrabold text-slate-900">Already a registered reader?</p>
                        <p class="text-xs text-slate-500 mt-0.5">Sign in for faster checkout. New reader? Fill the form below and an account will be created automatically.</p>
                    </div>
                </div>
                <a href="/login" class="bg-sky-700 hover:bg-sky-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-xs text-center shrink-0 active:scale-98">Sign In</a>
            </div>
        <?php endif; ?>

        <!-- Checkout Form -->
        <form id="checkout-form" method="POST" action="" class="w-full grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 xl:gap-10 items-start">
            <input type="hidden" name="action" value="place_order">
            <input type="hidden" name="zone_id" id="hidden_zone_id" value="">
            
            <!-- Left 7/8 Cols: Billing & Delivery Details -->
            <div class="lg:col-span-7 xl:col-span-8 space-y-6 w-full">
                <?php require_once __DIR__ . '/checkout/billing_form.php'; ?>
            </div>

            <!-- Right 5/4 Cols: Sticky Order Summary Card -->
            <div class="lg:col-span-5 xl:col-span-4 w-full">
                <?php require_once __DIR__ . '/checkout/order_summary.php'; ?>
            </div>
        </form>

    </div>
</div>

<!-- Scripts & Styles -->
<?php require_once __DIR__ . '/checkout/scripts.php'; ?>