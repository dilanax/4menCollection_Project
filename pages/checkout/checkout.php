<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Router හරහා එන නිසා Path එක හරි
require_once 'config/db.php'; 

// Cart එක හිස් නම් Shop එකට යවන්න
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo "<script>window.location.href = '/shop';</script>";
    exit;
}

// ========================================================================
// FETCH DATA FOR UI
// ========================================================================
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
            'name' => $p['name'],
            'variation_name' => $var_name,
            'qty' => $qty,
            'total' => $item_total
        ];
    }
}

// Fetch Active Payment Gateways from Database
try {
    $pg_stmt = $pdo->prepare("SELECT * FROM `payment_gateways` WHERE `is_active` = 1 ORDER BY `id` ASC");
    $pg_stmt->execute();
    $active_gateways = $pg_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $active_gateways = [];
}

$is_logged_in = isset($_SESSION['user_id']);
?>

<script>
    document.title = "Checkout | Your Store";
</script>

<div class="bg-gray-50 py-10 font-sans min-h-screen relative">

    <div id="success-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 text-center transform scale-95 transition-transform duration-300 shadow-2xl">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Order Confirmed!</h2>
            <p class="text-gray-500 mb-6">Thank you for your purchase. Your order number is <strong id="modal-order-number" class="text-gray-900"></strong>. We will contact you shortly.</p>
            <p class="text-xs text-blue-600 mb-6 hidden" id="account-creation-msg">If you are a new customer, we have automatically created an account for you. Please check your email for login details.</p>
            <a href="/" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-md">
                Return to Home
            </a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900">Checkout</h1>
            <nav class="flex text-sm text-gray-500 mt-2">
                <a href="/cart" class="hover:text-blue-600 transition">Cart</a>
                <span class="mx-2">/</span>
                <span class="text-gray-900 font-medium">Checkout</span>
            </nav>
        </div>

        <?php if(!$is_logged_in): ?>
        <div class="mb-8 bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between shadow-sm gap-4">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p class="text-sm font-medium text-gray-900">Returning customer?</p>
                    <p class="text-xs">If you already have an account, login for faster checkout. New? Just fill the form below and we'll create one for you automatically!</p>
                </div>
            </div>
            <a href="/login" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition shadow-sm text-center sm:text-left whitespace-nowrap">Login Now</a>
        </div>
        <?php endif; ?>

        <form id="checkout-form" class="flex flex-col lg:flex-row gap-8 items-start">
            <input type="hidden" name="action" value="place_order">
            <input type="hidden" name="zone_id" id="hidden_zone_id" value="">
            
            <div class="w-full lg:w-2/3 space-y-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6 border-b border-gray-100 pb-4">Billing details</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First name <span class="text-red-500">*</span></label>
                            <input type="text" name="billing_first_name" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last name <span class="text-red-500">*</span></label>
                            <input type="text" name="billing_last_name" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Country / Region <span class="text-red-500">*</span></label>
                        <select name="billing_country" required class="w-full border border-gray-300 bg-gray-50 text-gray-900 font-bold rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition cursor-not-allowed" readonly>
                            <option value="Sri Lanka" selected>Sri Lanka</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Province <span class="text-red-500">*</span></label>
                            <select name="billing_province" id="billing_province_select" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition bg-white">
                                <option value="">Select Province...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">District <span class="text-red-500">*</span></label>
                            <select name="billing_district" id="billing_district_select" disabled required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition bg-gray-50 text-gray-500 cursor-not-allowed">
                                <option value="">Select Province first...</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Street address <span class="text-red-500">*</span></label>
                        <input type="text" name="billing_address_1" required placeholder="House number and street name" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition mb-3">
                        <input type="text" name="billing_address_2" placeholder="Apartment, suite, unit, etc. (optional)" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Town / City <span class="text-red-500">*</span></label>
                            <input type="text" name="billing_city" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Postcode / ZIP <span class="text-red-500">*</span></label>
                            <input type="text" name="billing_zip" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone <span class="text-red-500">*</span></label>
                            <input type="tel" name="billing_phone" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email address <span class="text-red-500">*</span></label>
                            <input type="email" name="billing_email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="ship_to_different" name="ship_to_different" value="1" class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 cursor-pointer transition">
                        <label for="ship_to_different" class="text-xl font-bold text-gray-900 cursor-pointer select-none">Ship to a different address?</label>
                    </div>

                    <div id="shipping_details_section" class="hidden mt-6 pt-6 border-t border-gray-100 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First name <span class="text-red-500">*</span></label>
                                <input type="text" name="shipping_first_name" class="shipping-field w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last name <span class="text-red-500">*</span></label>
                                <input type="text" name="shipping_last_name" class="shipping-field w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Country / Region <span class="text-red-500">*</span></label>
                            <select name="shipping_country" class="shipping-field w-full border border-gray-300 bg-gray-50 text-gray-900 font-bold rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition cursor-not-allowed" readonly>
                                <option value="Sri Lanka" selected>Sri Lanka</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Province <span class="text-red-500">*</span></label>
                                <select name="shipping_province" id="province_select" class="shipping-field w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition bg-white">
                                    <option value="">Select Province...</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">District <span class="text-red-500">*</span></label>
                                <select name="shipping_district" id="district_select" disabled class="shipping-field w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition bg-gray-50 text-gray-500 cursor-not-allowed">
                                    <option value="">Select Province first...</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Street address <span class="text-red-500">*</span></label>
                            <input type="text" name="shipping_address_1" placeholder="House number and street name" class="shipping-field w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition mb-3">
                            <input type="text" name="shipping_address_2" placeholder="Apartment, suite, unit, etc. (optional)" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Town / City <span class="text-red-500">*</span></label>
                                <input type="text" name="shipping_city" class="shipping-field w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Postcode / ZIP <span class="text-red-500">*</span></label>
                                <input type="text" name="shipping_zip" class="shipping-field w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Order notes (optional)</label>
                        <textarea name="order_notes" rows="4" placeholder="Notes about your order, e.g. special notes for delivery." class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition"></textarea>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-1/3">
                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-gray-100 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b border-gray-100">Your order</h2>
                    
                    <div class="space-y-4 mb-6 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                        <div class="flex justify-between text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                            <span>Product</span>
                            <span>Subtotal</span>
                        </div>
                        <?php foreach($cart_items as $item): ?>
                            <div class="flex justify-between items-start text-sm border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                                <div class="pr-4 leading-tight">
                                    <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <strong class="text-gray-900 ml-1">× <?php echo $item['qty']; ?></strong>
                                    <?php if($item['variation_name']): ?>
                                        <p class="text-[11px] text-gray-500 mt-1 font-medium bg-gray-50 inline-block px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($item['variation_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="text-gray-900 font-bold whitespace-nowrap">Rs. <?php echo number_format($item['total'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="space-y-4 mb-6 border-t border-gray-200 pt-4">
                        <div class="flex justify-between text-gray-600">
                            <span class="font-bold">Subtotal</span>
                            <span class="font-bold text-gray-900" id="display_subtotal">Rs. <?php echo number_format($initial_subtotal, 2); ?></span>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 shadow-sm">
                            <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center justify-between">
                                Shipping
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </h4>
                            
                            <div id="shipping_message" class="text-xs font-bold text-amber-600">
                                Enter your Shipping District to see options.
                            </div>
                            
                            <div id="shipping_methods_container" class="space-y-2 hidden"></div>
                        </div>

                        <div id="handling_fee_summary_row" class="flex justify-between text-gray-600 hidden border-t border-dashed border-gray-200 pt-3">
                            <span class="font-medium text-sm flex items-center gap-1">
                                Gateway Handling Fee
                                <span id="handling_fee_badge" class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.2 rounded font-bold"></span>
                            </span>
                            <span class="font-bold text-gray-900 text-sm" id="display_handling_fee">Rs. 0.00</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 mb-8">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-900">Total</span>
                            <span class="text-2xl font-black text-blue-600" id="display_total">Rs. <?php echo number_format($initial_subtotal, 2); ?></span>
                        </div>
                    </div>

                    <div class="mb-8 space-y-3">
                        <?php if(empty($active_gateways)): ?>
                            <div class="p-3 bg-red-50 text-red-500 rounded-lg text-xs font-bold border border-red-100">
                                Operational Error: No payment gateways are enabled.
                            </div>
                        <?php else: ?>
                            <?php foreach($active_gateways as $index => $gateway): 
                                // Mapping dynamic attributes for javascript mapping logic
                                $fee_enabled = (int)$gateway['enable_handling_fee'];
                                $fee_percent = floatval($gateway['handling_fee_percentage']);
                                $fee_fixed = floatval($gateway['handling_fee_fixed']);
                            ?>
                                <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 transition-all [&:has(input:checked)]:border-blue-500 [&:has(input:checked)]:bg-blue-50">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="radio" name="payment_method" 
                                               value="<?php echo htmlspecialchars($gateway['gateway_id']); ?>" 
                                               data-fee-enabled="<?php echo $fee_enabled; ?>"
                                               data-fee-percent="<?php echo $fee_percent; ?>"
                                               data-fee-fixed="<?php echo $fee_fixed; ?>"
                                               <?php echo $index === 0 ? 'checked' : ''; ?> 
                                               class="gateway-radio-input mt-1 w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                        <div>
                                            <span class="font-bold text-gray-900 block mb-1"><?php echo htmlspecialchars($gateway['display_name']); ?></span>
                                            
                                            <?php if($gateway['gateway_id'] === 'payhere'): ?>
                                                <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mt-1 mb-2">(Secure Cards Payment)</p>
                                            <?php endif; ?>
                                            
                                            <p class="text-xs text-gray-600 leading-relaxed payment-desc <?php echo $index === 0 ? 'block' : 'hidden'; ?>">
                                                <?php if($gateway['gateway_id'] === 'payhere' && $gateway['enable_handling_fee'] == 1): ?>
                                                    Note: A convenience gateway handling fee may be added to this transaction.
                                                <?php else: ?>
                                                    Proceed with your transaction securely.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <p class="text-[11px] text-gray-500 mb-6 leading-relaxed">
                        Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our <a href="/privacy-policy" class="text-blue-600 hover:underline font-bold">privacy policy</a>.
                    </p>

                    <div id="form-error" class="hidden mb-4 p-3 bg-red-50 text-red-600 text-sm font-bold rounded-lg border border-red-200"></div>

                    <button type="submit" id="place-order-btn" <?php echo empty($active_gateways) ? 'disabled' : ''; ?> class="w-full bg-gray-900 hover:bg-black text-white text-center px-6 py-4 rounded-xl text-lg font-bold transition shadow-lg relative overflow-hidden flex justify-center items-center h-[60px]">
                        <span class="btn-text">Place order</span>
                        <span class="btn-loading hidden items-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Processing...
                        </span>
                    </button>

                </div>
            </div>

        </form>
    </div>
</div>

<script>
    // --- 1. Toggle Shipping Address Section ---
    const shipDifferentCheckbox = document.getElementById('ship_to_different');
    const shippingSection = document.getElementById('shipping_details_section');
    const shippingFields = document.querySelectorAll('.shipping-field');

    shipDifferentCheckbox.addEventListener('change', function() {
        if(this.checked) {
            shippingSection.classList.remove('hidden');
            shippingFields.forEach(f => {
                if(f.name !== 'shipping_address_2' && f.name !== 'shipping_zip') {
                    f.required = true;
                }
            });
            document.getElementById('district_select').dispatchEvent(new Event('change'));
        } else {
            shippingSection.classList.add('hidden');
            shippingFields.forEach(f => f.required = false);
            document.getElementById('billing_district_select').dispatchEvent(new Event('change'));
        }
    });

    // --- 2. Payment Method Descriptions Toggle & Handling Fee Trigger ---
    const paymentRadios = document.querySelectorAll('.gateway-radio-input');
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.payment-desc').forEach(desc => desc.classList.add('hidden'));
            if(this.checked) {
                this.closest('label').querySelector('.payment-desc').classList.remove('hidden');
            }
            // Recalculate total immediately when payment option switches
            updateTotal(currentShippingCost);
        });
    });

    // --- 3. Sri Lanka Districts Hierarchy Map ---
    const locations = {
        "Western Province": ["Colombo District", "Gampaha District", "Kalutara District"],
        "Central Province": ["Kandy District", "Matale District", "Nuwara Eliya District"],
        "Southern Province": ["Galle District", "Matara District", "Hambantota District"],
        "Northern Province": ["Jaffna District", "Kilinochchi District", "Mannar District", "Vavuniya District", "Mullaitivu District"],
        "Eastern Province": ["Trincomalee District", "Batticaloa District", "Ampara District"],
        "North Western Province": ["Kurunegala District", "Puttalam District"],
        "North Central Province": ["Anuradhapura District", "Polonnaruwa District"],
        "Uva Province": ["Badulla District", "Moneragala District"],
        "Sabaragamuwa Province": ["Ratnapura District", "Kegalle District"]
    };

    const bProvinceSelect = document.getElementById('billing_province_select');
    const bDistrictSelect = document.getElementById('billing_district_select');
    const sProvinceSelect = document.getElementById('province_select');
    const sDistrictSelect = document.getElementById('district_select');

    for (let province in locations) {
        bProvinceSelect.innerHTML += `<option value="${province}">${province}</option>`;
        sProvinceSelect.innerHTML += `<option value="${province}">${province}</option>`;
    }

    function setupHierarchy(provSelect, distSelect) {
        provSelect.addEventListener('change', function() {
            const selectedProv = this.value;
            distSelect.innerHTML = '<option value="">Select District...</option>';
            
            if(selectedProv && locations[selectedProv]) {
                locations[selectedProv].forEach(dist => {
                    distSelect.innerHTML += `<option value="${dist}">${dist}</option>`;
                });
                distSelect.disabled = false;
                distSelect.classList.remove('bg-gray-50', 'text-gray-500', 'cursor-not-allowed');
                distSelect.classList.add('bg-white');
            } else {
                distSelect.disabled = true;
                distSelect.classList.add('bg-gray-50', 'text-gray-500', 'cursor-not-allowed');
                distSelect.classList.remove('bg-white');
                distSelect.innerHTML = '<option value="">Select Province first...</option>';
            }
            distSelect.dispatchEvent(new Event('change'));
        });
    }

    setupHierarchy(bProvinceSelect, bDistrictSelect);
    setupHierarchy(sProvinceSelect, sDistrictSelect);

    // --- 4. Shipping & Total Calculation With Handling Fee Logic ---
    const baseSubtotal = <?php echo $initial_subtotal; ?>;
    let currentShippingCost = 0;
    const actionUrl = '/pages/checkout/checkout_action.php';

    function runShippingCalculation(districtName) {
        const msgContainer = document.getElementById('shipping_message');
        const methodsContainer = document.getElementById('shipping_methods_container');
        const hiddenZoneId = document.getElementById('hidden_zone_id'); 
        
        if (!districtName) {
            msgContainer.innerText = 'Please select a District to see shipping options.';
            msgContainer.classList.remove('hidden', 'text-red-600');
            msgContainer.classList.add('text-amber-600');
            methodsContainer.classList.add('hidden');
            hiddenZoneId.value = '';
            updateTotal(0);
            return;
        }

        msgContainer.innerText = 'Calculating shipping...';
        msgContainer.classList.remove('hidden', 'text-red-600');
        msgContainer.classList.add('text-amber-600');
        methodsContainer.classList.add('hidden');
        methodsContainer.innerHTML = '';
        hiddenZoneId.value = '';

        const formData = new FormData();
        formData.append('action', 'calculate_shipping');
        formData.append('district_name', districtName);

        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                msgContainer.classList.add('hidden');
                hiddenZoneId.value = data.zone_id; 
                
                if (data.methods.length === 0) {
                    msgContainer.innerText = 'No shipping options available for this area.';
                    msgContainer.classList.remove('hidden', 'text-amber-600');
                    msgContainer.classList.add('text-red-600');
                    updateTotal(0);
                    return;
                }

                let html = '';
                data.methods.forEach((m, index) => {
                    const isChecked = index === 0 ? 'checked' : ''; 
                    if(index === 0) currentShippingCost = m.cost;

                    html += `
                        <label class="flex items-center justify-between p-3 border border-gray-200 bg-white rounded-lg cursor-pointer hover:bg-blue-50 transition [&:has(input:checked)]:border-blue-500 [&:has(input:checked)]:bg-blue-50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="shipping_method" value="${m.id}" data-cost="${m.cost}" ${isChecked} class="shipping-radio w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <span class="text-sm font-bold text-gray-900">${m.name}</span>
                            </div>
                            <span class="text-sm font-bold text-gray-900">${m.cost === 0 ? '<span class="text-green-600">Free</span>' : 'Rs. ' + m.formatted_cost}</span>
                        </label>
                    `;
                });
                
                methodsContainer.innerHTML = html;
                methodsContainer.classList.remove('hidden');
                
                document.querySelectorAll('.shipping-radio').forEach(radio => {
                    radio.addEventListener('change', function() {
                        currentShippingCost = parseFloat(this.getAttribute('data-cost'));
                        updateTotal(currentShippingCost);
                    });
                });

                updateTotal(currentShippingCost);

            } else {
                msgContainer.innerText = data.message || 'Error calculating shipping.';
                msgContainer.classList.remove('text-amber-600');
                msgContainer.classList.add('text-red-600');
            }
        })
        .catch(err => {
            msgContainer.innerText = 'Network error. Try again.';
            msgContainer.classList.remove('text-amber-600');
            msgContainer.classList.add('text-red-600');
        });
    }

    bDistrictSelect.addEventListener('change', function() {
        if (!shipDifferentCheckbox.checked) runShippingCalculation(this.value);
    });
    sDistrictSelect.addEventListener('change', function() {
        if (shipDifferentCheckbox.checked) runShippingCalculation(this.value);
    });

    // ✨ MATHEMATICAL CALCULATION LOGIC FOR HANDLING FEES ✨
    function updateTotal(shippingCost) {
        let currentSubtotal = baseSubtotal + shippingCost;
        let handlingFee = 0;

        // Get selected payment radio attributes dynamically
        const selectedRadio = document.querySelector('.gateway-radio-input:checked');
        const feeRow = document.getElementById('handling_fee_summary_row');
        const feeBadge = document.getElementById('handling_fee_badge');
        const feeDisplay = document.getElementById('display_handling_fee');

        if (selectedRadio && selectedRadio.getAttribute('data-fee-enabled') == '1') {
            const percent = parseFloat(selectedRadio.getAttribute('data-fee-percent')) || 0;
            const fixed = parseFloat(selectedRadio.getAttribute('data-fee-fixed')) || 0;

            // Calculate compound fee total
            handlingFee = (currentSubtotal * percent / 100) + fixed;

            // Show Row and update badges
            feeBadge.innerText = percent > 0 ? `${percent}% + Rs.${fixed}` : `Rs.${fixed}`;
            feeDisplay.innerText = 'Rs. ' + handlingFee.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            feeRow.classList.remove('hidden');
        } else {
            feeRow.classList.add('hidden');
        }

        const finalGrandTotal = currentSubtotal + handlingFee;
        document.getElementById('display_total').innerText = 'Rs. ' + finalGrandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Initialize checking state on load
    document.addEventListener("DOMContentLoaded", function() {
        updateTotal(0);
    });

    // --- 5. Form Submission ---
    document.getElementById('checkout-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const btn = document.getElementById('place-order-btn');
        const btnText = btn.querySelector('.btn-text');
        const btnLoading = btn.querySelector('.btn-loading');
        const errorDiv = document.getElementById('form-error');
        
        const activeDistrict = shipDifferentCheckbox.checked ? sDistrictSelect.value : bDistrictSelect.value;
        if(!activeDistrict) {
            errorDiv.innerText = "Please select a valid Province and District.";
            errorDiv.classList.remove('hidden');
            return;
        }
        if(!document.getElementById('hidden_zone_id').value) {
            errorDiv.innerText = "Shipping Zone calculation unverified.";
            errorDiv.classList.remove('hidden');
            return;
        }
        if(!form.querySelector('input[name="shipping_method"]:checked')) {
            errorDiv.innerText = "Please select a valid shipping method.";
            errorDiv.classList.remove('hidden');
            return;
        }

        errorDiv.classList.add('hidden');
        btn.disabled = true;
        btnText.classList.add('hidden');
        btnLoading.classList.remove('hidden');
        btnLoading.classList.add('flex');

        const formData = new FormData(form);

        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    document.getElementById('modal-order-number').innerText = data.order_number;
                    const modal = document.getElementById('success-modal');
                    modal.classList.remove('hidden');
                    void modal.offsetWidth; 
                    modal.classList.remove('opacity-0');
                    modal.querySelector('div').classList.remove('scale-95');
                    modal.querySelector('div').classList.add('scale-100');
                    
                    <?php if(!$is_logged_in): ?>
                        document.getElementById('account-creation-msg').classList.remove('hidden');
                    <?php endif; ?>
                }
            } else {
                errorDiv.innerText = data.message || "An error occurred. Please try again.";
                errorDiv.classList.remove('hidden');
                
                btn.disabled = false;
                btnText.classList.remove('hidden');
                btnLoading.classList.add('hidden');
                btnLoading.classList.remove('flex');
            }
        })
        .catch(err => {
            errorDiv.innerText = "A network error occurred. Please check your connection.";
            errorDiv.classList.remove('hidden');
            
            btn.disabled = false;
            btnText.classList.remove('hidden');
            btnLoading.classList.add('hidden');
            btnLoading.classList.remove('flex');
        });
    });
</script>