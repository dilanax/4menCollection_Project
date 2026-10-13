<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure cart array exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$messageType = '';

// ==========================================
// 1. AJAX & POST REQUESTS HANDLING
// ==========================================
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ADD TO CART
    if ($action == 'add') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $variation_id = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : 0;
        $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        $cart_key = $product_id . '_' . $variation_id;

        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$cart_key] = [
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity
            ];
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Book added to cart successfully!']);
            exit;
        }

        $message = "Book added to your cart!";
        $messageType = "success";
    }

    // UPDATE QUANTITY
    if ($action == 'update') {
        if (isset($_POST['qty']) && is_array($_POST['qty'])) {
            foreach ($_POST['qty'] as $cart_key => $qty) {
                $qty = (int)$qty;
                if ($qty > 0 && isset($_SESSION['cart'][$cart_key])) {
                    $_SESSION['cart'][$cart_key]['quantity'] = $qty;
                } elseif ($qty <= 0) {
                    unset($_SESSION['cart'][$cart_key]);
                }
            }
        }
        $message = "Cart quantities updated successfully!";
        $messageType = "success";
    }

    // REMOVE ITEM
    if ($action == 'remove') {
        $cart_key = $_POST['cart_key'] ?? '';
        if (isset($_SESSION['cart'][$cart_key])) {
            unset($_SESSION['cart'][$cart_key]);
            $message = "Book removed from your cart.";
            $messageType = "success";
        }
    }

    // Non-AJAX Form Redirect
    if (!$is_ajax) {
        header("Location: /cart?msg=" . urlencode($message) . "&type=" . $messageType);
        exit;
    }
}

// Flash Message Alerts
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'success';
}

// ==========================================
// 2. FETCH CART DATA FROM DATABASE
// ==========================================
$cart_items = [];
$subtotal = 0.00;

if (!empty($_SESSION['cart']) && isset($pdo) && $pdo instanceof PDO) {
    foreach ($_SESSION['cart'] as $key => $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $vid = (int)($item['variation_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 1);

        $stmt = $pdo->prepare("SELECT id, name, slug, main_image, regular_price, sale_price, type FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $p = $stmt->fetch();

        if ($p) {
            $price = ($p['sale_price'] > 0) ? (float)$p['sale_price'] : (float)$p['regular_price'];
            $image = $p['main_image'];
            $var_name = '';

            if ($vid > 0) {
                $v_stmt = $pdo->prepare("SELECT variation_name, image, regular_price, sale_price FROM product_variations WHERE id = ?");
                $v_stmt->execute([$vid]);
                $v = $v_stmt->fetch();
                if ($v) {
                    $price = ($v['sale_price'] > 0) ? (float)$v['sale_price'] : (float)$v['regular_price'];
                    if (!empty($v['image'])) {
                        $image = $v['image'];
                    }
                    $var_name = $v['variation_name'];
                }
            }

            $item_total = $price * $qty;
            $subtotal += $item_total;

            $cart_items[] = [
                'cart_key'       => $key,
                'product_id'     => $pid,
                'name'           => $p['name'],
                'slug'           => $p['slug'],
                'image'          => !empty($image) ? '/' . ltrim(htmlspecialchars($image), '/') : '/assets/placeholder.jpg',
                'variation_name' => $var_name,
                'price'          => $price,
                'quantity'       => $qty,
                'total'          => $item_total
            ];
        }
    }
}

// ==========================================
// 3. DYNAMIC FREE SHIPPING CHECK (FROM DB ONLY)
// ==========================================
$active_free_offer = null;
if (isset($pdo) && $pdo instanceof PDO && $subtotal > 0) {
    try {
        $stmt_offer = $pdo->query("
            SELECT r.*, p.name as province_name 
            FROM geo_delivery_rates r
            LEFT JOIN geo_regions p ON r.province_id = p.id
            WHERE r.is_active = 1 
            AND (r.shipping_type = 'free' OR (r.min_order_free IS NOT NULL AND r.min_order_free > 0))
            ORDER BY r.min_order_free ASC, r.id ASC
            LIMIT 1
        ");
        $active_free_offer = $stmt_offer->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $active_free_offer = null;
    }
}

$has_free_threshold = ($active_free_offer && !empty($active_free_offer['min_order_free']) && $active_free_offer['min_order_free'] > 0);
$free_threshold_val = $has_free_threshold ? (float)$active_free_offer['min_order_free'] : 0.00;
$shipping_difference = $has_free_threshold ? max(0, $free_threshold_val - $subtotal) : 0;
$shipping_progress = $has_free_threshold ? min(100, round(($subtotal / $free_threshold_val) * 100)) : 0;
$offer_region_label = !empty($active_free_offer['province_name']) ? htmlspecialchars($active_free_offer['province_name']) : 'Islandwide';
?>

<div class="w-full bg-[#f8fafc] py-6 sm:py-10 min-h-[75vh]" style="font-family: 'Poppins', sans-serif;">
    <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12">
        
        <!-- Breadcrumbs & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 border-b border-slate-200 pb-4">
            <div>
                <nav class="flex items-center text-xs font-semibold text-slate-400 mb-2 gap-2">
                    <a href="/" class="hover:text-sky-700 transition">Home</a>
                    <span>/</span>
                    <a href="/shop" class="hover:text-sky-700 transition">Shop</a>
                    <span>/</span>
                    <span class="text-sky-700 font-bold">Shopping Cart</span>
                </nav>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    Your Book Basket
                    <span class="text-xs sm:text-sm font-bold bg-sky-50 text-sky-800 border border-sky-100 px-3 py-0.5 rounded-full">
                        <?= count($cart_items); ?> <?= count($cart_items) === 1 ? 'Title' : 'Titles'; ?>
                    </span>
                </h1>
            </div>
            <a href="/shop" class="inline-flex items-center gap-2 text-xs sm:text-sm font-bold text-sky-700 hover:text-sky-800 hover:underline transition self-start sm:self-auto">
                <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Continue Browsing Bookstore
            </a>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-2xl <?= $messageType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800'; ?> flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full <?= $messageType === 'success' ? 'bg-emerald-500/20 text-emerald-600' : 'bg-rose-500/20 text-rose-600'; ?> flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <?php if ($messageType === 'success'): ?>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            <?php else: ?>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            <?php endif; ?>
                        </svg>
                    </div>
                    <span class="text-xs sm:text-sm font-semibold"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($cart_items)): ?>
            
            <!-- Real Dynamic Free Delivery Offer (ONLY shown if an active offer rule is configured in Admin) -->
            <?php if ($active_free_offer): ?>
                <div class="mb-6 p-4 sm:p-5 bg-white rounded-2xl sm:rounded-3xl border border-sky-200/80 shadow-xs">
                    <?php if ($has_free_threshold): ?>
                        <div class="flex items-center justify-between text-xs sm:text-sm font-semibold text-slate-700 mb-2">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-sky-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                <?php if ($shipping_difference <= 0): ?>
                                    <span class="text-emerald-600 font-bold">🎉 Congratulations! You qualify for Free <?= $offer_region_label; ?> Delivery!</span>
                                <?php else: ?>
                                    Add <span class="text-sky-700 font-black">Rs. <?= number_format($shipping_difference, 2); ?></span> more of books to unlock <strong class="text-slate-900">Free <?= $offer_region_label; ?> Delivery</strong>!
                                <?php endif; ?>
                            </span>
                            <span class="text-slate-400 font-medium"><?= $shipping_progress; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r from-sky-500 to-sky-700 h-2 rounded-full transition-all duration-500" style="width: <?= $shipping_progress; ?>%;"></div>
                        </div>
                    <?php elseif ($active_free_offer['shipping_type'] === 'free'): ?>
                        <div class="flex items-center gap-2.5 text-xs sm:text-sm font-bold text-emerald-700">
                            <svg class="w-5 h-5 stroke-[2] text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Special Offer: Free <?= $offer_region_label; ?> Delivery is currently available!</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Cart Form: List & Summary -->
            <form action="/cart" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
                <input type="hidden" name="action" value="update">
                
                <!-- Left 8 Cols: Books List -->
                <div class="lg:col-span-8 space-y-4">
                    <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xs border border-slate-200/90 overflow-hidden">
                        
                        <!-- Table Heading (Desktop) -->
                        <div class="hidden md:grid grid-cols-12 gap-4 px-6 py-4 border-b border-slate-100 bg-slate-50/70 text-xs font-bold text-slate-500 uppercase tracking-wider">
                            <div class="col-span-6">Book Item</div>
                            <div class="col-span-2 text-center">Unit Price</div>
                            <div class="col-span-2 text-center">Quantity</div>
                            <div class="col-span-2 text-right">Subtotal</div>
                        </div>

                        <!-- Table Items List -->
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="p-4 sm:p-6 grid grid-cols-1 md:grid-cols-12 gap-4 items-center hover:bg-slate-50/40 transition">
                                    
                                    <!-- Book Info & Cover Image -->
                                    <div class="col-span-1 md:col-span-6 flex items-center gap-4">
                                        <!-- Remove Button -->
                                        <button 
                                            type="button" 
                                            onclick="removeItem('<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8'); ?>')" 
                                            class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition shrink-0" 
                                            title="Remove Book"
                                        >
                                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                        
                                        <!-- Book Thumbnail -->
                                        <a href="/product/<?= htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="w-16 h-22 sm:w-18 sm:h-24 bg-slate-100 border border-slate-200 rounded-xl overflow-hidden shrink-0 flex items-center justify-center shadow-xs hover:opacity-90 transition">
                                            <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover">
                                        </a>
                                        
                                        <!-- Titles & Variations -->
                                        <div class="min-w-0">
                                            <a href="/product/<?= htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="font-extrabold text-slate-900 hover:text-sky-700 transition text-xs sm:text-sm leading-snug line-clamp-2 mb-1">
                                                <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                            <?php if (!empty($item['variation_name'])): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-sky-50 text-sky-700 border border-sky-200/60">
                                                    <?= htmlspecialchars($item['variation_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    <div class="col-span-1 md:col-span-2 flex md:flex-col justify-between md:items-center text-xs sm:text-sm">
                                        <span class="md:hidden text-xs text-slate-400 font-semibold uppercase">Price:</span>
                                        <span class="font-semibold text-slate-700">Rs. <?= number_format($item['price'], 2); ?></span>
                                    </div>

                                    <!-- Quantity Stepper -->
                                    <div class="col-span-1 md:col-span-2 flex md:justify-center justify-between items-center">
                                        <span class="md:hidden text-xs text-slate-400 font-semibold uppercase">Quantity:</span>
                                        <div class="inline-flex items-center border border-slate-200 rounded-xl bg-slate-50 overflow-hidden h-9 sm:h-10 w-26 sm:w-28">
                                            <button type="button" onclick="updateQty('qty_<?= $item['cart_key']; ?>', -1)" class="w-8 h-full flex items-center justify-center text-slate-500 hover:bg-sky-50 hover:text-sky-700 font-bold transition">-</button>
                                            <input 
                                                type="number" 
                                                id="qty_<?= $item['cart_key']; ?>" 
                                                name="qty[<?= $item['cart_key']; ?>]" 
                                                value="<?= $item['quantity']; ?>" 
                                                min="1" 
                                                class="w-full text-center font-bold text-xs sm:text-sm text-slate-800 bg-transparent border-none p-0 focus:ring-0"
                                            >
                                            <button type="button" onclick="updateQty('qty_<?= $item['cart_key']; ?>', 1)" class="w-8 h-full flex items-center justify-center text-slate-500 hover:bg-sky-50 hover:text-sky-700 font-bold transition">+</button>
                                        </div>
                                    </div>

                                    <!-- Subtotal -->
                                    <div class="col-span-1 md:col-span-2 flex md:justify-end justify-between items-center text-right">
                                        <span class="md:hidden text-xs text-slate-400 font-semibold uppercase">Subtotal:</span>
                                        <span class="text-xs sm:text-sm font-black text-slate-900">Rs. <?= number_format($item['total'], 2); ?></span>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Cart Actions Bar -->
                        <div class="p-4 sm:p-5 bg-slate-50/70 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <a href="/shop" class="text-xs sm:text-sm font-bold text-slate-600 hover:text-sky-700 transition flex items-center gap-1.5 order-2 sm:order-1">
                                &larr; Back to Bookstore
                            </a>
                            <button type="submit" class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-xs order-1 sm:order-2">
                                Update Cart Quantities
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Right 4 Cols: Order Summary Sticky Card -->
                <div class="lg:col-span-4 w-full">
                    <div class="bg-white p-6 sm:p-7 rounded-2xl sm:rounded-3xl shadow-xs border border-slate-200/90 sticky top-24 space-y-5">
                        <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight pb-3.5 border-b border-slate-100">
                            Order Summary
                        </h2>
                        
                        <!-- Calculation Breakdown -->
                        <div class="space-y-3 text-xs sm:text-sm">
                            <div class="flex justify-between text-slate-600">
                                <span>Cart Subtotal</span>
                                <span class="font-bold text-slate-900">Rs. <?= number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between text-slate-600 items-center">
                                <span>Shipping Fee</span>
                                <span class="text-[11px] font-bold text-slate-600 bg-slate-100 px-2.5 py-0.5 rounded-md border border-slate-200">
                                    Calculated at Checkout
                                </span>
                            </div>
                        </div>

                        <!-- Grand Total -->
                        <div class="border-t border-slate-100 pt-4">
                            <div class="flex justify-between items-baseline">
                                <span class="text-sm font-bold text-slate-900">Items Total</span>
                                <span class="text-2xl font-black text-sky-700">Rs. <?= number_format($subtotal, 2); ?></span>
                            </div>
                            <p class="text-[10px] text-slate-400 text-right mt-1">Delivery fee is based on your selected delivery location & parcel weight</p>
                        </div>

                        <!-- Checkout CTA Button -->
                        <a href="/checkout" class="block w-full bg-sky-700 hover:bg-sky-800 text-white text-center px-6 py-3.5 rounded-xl text-xs sm:text-sm font-bold uppercase tracking-wider transition shadow-md shadow-sky-700/20 active:scale-98">
                            Proceed to Checkout &rarr;
                        </a>

                        <!-- Security Seal -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-center gap-2 text-xs text-slate-400">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span>Encrypted 256-bit Secure Checkout</span>
                        </div>
                    </div>
                </div>

            </form>

            <!-- Hidden Form for Item Removal -->
            <form id="remove-form" action="/cart" method="POST" class="hidden">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="cart_key" id="remove_cart_key" value="">
            </form>

        <?php else: ?>
            
            <!-- Empty Cart State -->
            <div class="bg-white p-8 sm:p-14 rounded-3xl shadow-xs border border-slate-200/90 text-center max-w-xl mx-auto my-6">
                <div class="w-20 h-20 bg-sky-50 text-sky-700 rounded-2xl flex items-center justify-center mx-auto mb-5 border border-sky-100">
                    <svg class="w-10 h-10 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <h3 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight mb-2">Your reading basket is empty</h3>
                <p class="text-xs sm:text-sm text-slate-500 mb-6 max-w-sm mx-auto leading-relaxed">
                    You have not added any books to your shopping cart yet. Discover your next favorite read in our online catalog.
                </p>
                <a href="/shop" class="inline-flex items-center gap-2 bg-sky-700 hover:bg-sky-800 text-white px-7 py-3 rounded-xl font-bold transition shadow-md shadow-sky-700/20 text-xs uppercase tracking-wider">
                    Browse Bookstore Catalog &rarr;
                </a>
            </div>

        <?php endif; ?>

    </div>
</div>

<script>
    document.title = "Shopping Cart | ReadersHub";

    function updateQty(inputId, change) {
        const input = document.getElementById(inputId);
        if (!input) return;
        let current = parseInt(input.value) || 1;
        let min = parseInt(input.getAttribute('min')) || 1;
        let newVal = current + change;
        if (newVal >= min) {
            input.value = newVal;
        }
    }

    function removeItem(cartKey) {
        if (confirm('Are you sure you want to remove this book from your cart?')) {
            document.getElementById('remove_cart_key').value = cartKey;
            document.getElementById('remove-form').submit();
        }
    }
</script>