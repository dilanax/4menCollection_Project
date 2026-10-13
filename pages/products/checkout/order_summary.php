<?php
if (!isset($active_gateways) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $gw_fetch = $pdo->query("SELECT * FROM payment_gateways WHERE is_active = 1 ORDER BY id ASC");
        $active_gateways = $gw_fetch ? $gw_fetch->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        $active_gateways = [];
    }
}

if (!isset($active_bank_accounts) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $b_fetch = $pdo->query("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC");
        $active_bank_accounts = $b_fetch ? $b_fetch->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        $active_bank_accounts = [];
    }
}
?>
<div class="bg-white p-5 sm:p-7 lg:p-8 rounded-3xl shadow-xs border border-slate-200/90 lg:sticky lg:top-24 space-y-6">
    <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight pb-3.5 border-b border-slate-100">
        Order Summary
    </h2>
    
    <!-- Cart Items Preview -->
    <div class="space-y-3.5 max-h-60 overflow-y-auto pr-1.5 custom-scrollbar">
        <?php foreach ($cart_items as $item): ?>
            <div class="flex justify-between items-start text-xs sm:text-sm gap-3">
                <div class="min-w-0 pr-2">
                    <span class="font-extrabold text-slate-900 block truncate"><?= htmlspecialchars($item['name']); ?></span>
                    <span class="text-slate-400 text-xs font-medium">Quantity: <?= $item['qty']; ?></span>
                    <?php if (!empty($item['variation_name'])): ?>
                        <p class="text-[11px] text-sky-700 font-semibold mt-0.5"><?= htmlspecialchars($item['variation_name']); ?></p>
                    <?php endif; ?>
                </div>
                <span class="font-bold text-slate-800 whitespace-nowrap">Rs. <?= number_format($item['total'], 2); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Calculations -->
    <div class="space-y-3.5 border-t border-slate-100 pt-4 text-xs sm:text-sm">
        <div class="flex justify-between text-slate-600">
            <span>Subtotal</span>
            <span class="font-bold text-slate-900" id="display_subtotal" data-subtotal="<?= (float)$initial_subtotal; ?>">Rs. <?= number_format($initial_subtotal, 2); ?></span>
        </div>
        
        <!-- Dynamic Shipping Box -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-2.5">Shipping Method</h4>
            
            <div id="shipping_message" class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 p-3 rounded-xl">
                Please select your Province & District to calculate shipping.
            </div>
            
            <div id="shipping_methods_container" class="space-y-2 hidden"></div>
        </div>
    </div>

    <!-- Grand Total -->
    <div class="border-t border-slate-100 pt-4">
        <div class="flex justify-between items-baseline">
            <span class="text-sm font-bold text-slate-900">Total Due</span>
            <span class="text-2xl sm:text-3xl font-black text-sky-700" id="display_total">Rs. <?= number_format($initial_subtotal, 2); ?></span>
        </div>
    </div>

    <!-- Payment Methods Selection -->
    <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-2.5">Payment Method *</h4>
        
        <?php if (empty($active_gateways)): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold rounded-2xl">
                No payment gateways are currently enabled in the Admin Panel.
            </div>
        <?php else: ?>
            <div class="space-y-3" id="payment_methods_list">
                
                <?php 
                $first_selected = false;
                foreach ($active_gateways as $gw): 
                    $gid = strtolower(trim($gw['gateway_id']));
                    $is_checked = !$first_selected;
                    if ($is_checked) $first_selected = true;
                ?>

                    <!-- Option A: Direct Bank Transfer -->
                    <?php if ($gid === 'bank_transfer'): ?>
                        <div class="border-2 <?= $is_checked ? 'border-sky-700 bg-sky-50/40' : 'border-slate-200 bg-white'; ?> rounded-2xl p-4 transition-all payment-box cursor-pointer" id="box_bank_transfer" onclick="selectPaymentMethod('bank_transfer')">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="payment_method" id="radio_bank_transfer" value="bank_transfer" <?= $is_checked ? 'checked' : ''; ?> onchange="selectPaymentMethod('bank_transfer')" class="w-4 h-4 text-sky-700 focus:ring-sky-700 cursor-pointer">
                                    <div>
                                        <span class="font-extrabold text-xs sm:text-sm text-slate-900 block"><?= htmlspecialchars($gw['display_name'] ?: 'Direct Bank Transfer / Deposit'); ?></span>
                                        <span class="text-[11px] text-slate-500 block mt-0.5">Pay via Online Banking, CDM deposit or Bank Slip.</span>
                                    </div>
                                </div>
                                <div class="w-8 h-8 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5M4.5 21V10.5M21 21H3"/></svg>
                                </div>
                            </label>

                            <!-- Bank Details Box (From bank_accounts Table) -->
                            <div id="bank_details_preview" class="mt-3.5 pt-3.5 border-t border-sky-200 space-y-3 <?= $is_checked ? 'block' : 'hidden'; ?>">
                                <?php if (!empty($active_bank_accounts)): ?>
                                    <?php foreach ($active_bank_accounts as $acc): ?>
                                        <div class="bg-white p-4 rounded-xl border border-sky-200 text-xs shadow-xs space-y-1.5">
                                            <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                                                <span class="text-slate-500 text-[11px]">Bank:</span>
                                                <span class="font-extrabold text-slate-900"><?= htmlspecialchars($acc['bank_name']); ?></span>
                                            </div>
                                            <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                                                <span class="text-slate-500 text-[11px]">Branch:</span>
                                                <span class="font-bold text-slate-800"><?= htmlspecialchars($acc['branch_name']); ?></span>
                                            </div>
                                            <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                                                <span class="text-slate-500 text-[11px]">Account Number:</span>
                                                <span class="font-mono font-black text-sky-700 text-sm tracking-wide select-all"><?= htmlspecialchars($acc['account_number']); ?></span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-slate-500 text-[11px]">Account Name:</span>
                                                <span class="font-bold text-slate-800"><?= htmlspecialchars($acc['account_name']); ?></span>
                                            </div>
                                            <?php if (!empty($acc['instructions'])): ?>
                                                <div class="pt-1.5 text-[10px] text-slate-500 italic">
                                                    <?= htmlspecialchars($acc['instructions']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- WhatsApp CTA Button -->
                                <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-emerald-900 text-[11px] leading-relaxed space-y-2">
                                    <p class="font-medium text-emerald-950">
                                        📌 <strong>උපදෙස්:</strong> මුදල් තැන්පත් කළ පසු Payment Slip එක Order ID එක සමඟ පහත WhatsApp අංකයට එවන්න:
                                    </p>
                                    <a 
                                        href="https://wa.me/94703472727?text=Hello%20ReadersHub,%20here%20is%20my%20bank%20deposit%20slip%20for%20my%20order." 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3.5 py-2 rounded-xl text-xs transition shadow-sm active:scale-98"
                                    >
                                        <span>WhatsApp: 070 347 27 27 &rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                    <!-- Option B: Cash on Delivery (COD) -->
                    <?php elseif ($gid === 'cod' || $gid === 'cash_on_delivery'): ?>
                        <div class="border <?= $is_checked ? 'border-2 border-sky-700 bg-sky-50/40' : 'border-slate-200 bg-white'; ?> rounded-2xl p-4 transition-all payment-box hover:border-slate-300 cursor-pointer" id="box_cod" onclick="selectPaymentMethod('cod')">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="payment_method" id="radio_cod" value="COD" <?= $is_checked ? 'checked' : ''; ?> onchange="selectPaymentMethod('cod')" class="w-4 h-4 text-sky-700 focus:ring-sky-700 cursor-pointer">
                                    <div class="flex-1 min-w-0">
                                        <span class="font-bold text-xs sm:text-sm text-slate-900 block truncate">
                                            <?= htmlspecialchars($gw['display_name'] ?: 'Cash on Delivery (COD)'); ?>
                                        </span>
                                        <span class="text-[11px] text-slate-400 block mt-0.5">Pay upon parcel arrival at your doorstep.</span>
                                    </div>
                                </div>
                                <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0C2.678 5.57 2.25 6.05 2.25 6.618v11.007c0 .568.422 1.048.987 1.106a48.54 48.54 0 005.013.297"/></svg>
                                </div>
                            </label>
                        </div>

                    <!-- Option C: PayHere Online Gateway -->
                    <?php elseif ($gid === 'payhere'): ?>
                        <div class="border <?= $is_checked ? 'border-2 border-sky-700 bg-sky-50/40' : 'border-slate-200 bg-white'; ?> rounded-2xl p-4 transition-all payment-box hover:border-slate-300 cursor-pointer" id="box_payhere" onclick="selectPaymentMethod('payhere')">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="payment_method" id="radio_payhere" value="payhere" <?= $is_checked ? 'checked' : ''; ?> onchange="selectPaymentMethod('payhere')" class="w-4 h-4 text-sky-700 focus:ring-sky-700 cursor-pointer">
                                    <div class="flex-1 min-w-0">
                                        <span class="font-bold text-xs sm:text-sm text-slate-900 block truncate">
                                            <?= htmlspecialchars($gw['display_name'] ?: 'Pay Online (Card / PayHere)'); ?>
                                        </span>
                                        <span class="text-[11px] text-slate-400 block mt-0.5">Visa, Mastercard, Amex, Frimi, Genie</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <span class="px-2 py-0.5 bg-slate-100 rounded text-[9px] font-bold text-slate-700 border border-slate-200">VISA</span>
                                    <span class="px-2 py-0.5 bg-slate-100 rounded text-[9px] font-bold text-slate-700 border border-slate-200">MasterCard</span>
                                </div>
                            </label>
                        </div>
                    <?php endif; ?>

                <?php endforeach; ?>

            </div>
        <?php endif; ?>

    </div>

    <div id="form-error" class="hidden p-3 bg-rose-50 text-rose-700 text-xs font-bold rounded-xl border border-rose-200"></div>

    <!-- Place Order Button -->
    <button type="submit" id="place-order-btn" class="w-full bg-sky-700 hover:bg-sky-800 text-white text-center px-6 py-4 rounded-xl text-xs sm:text-sm font-bold uppercase tracking-wider transition shadow-md shadow-sky-700/20 flex justify-center items-center active:scale-98">
        <span class="btn-text">Confirm & Place Order</span>
        <span class="btn-loading hidden items-center gap-2">
            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            Processing...
        </span>
    </button>
</div>

<script>
    // Universal Alert Helper (Guaranteed not to crash even if SweetAlert fails)
    function showAlert(icon, title, text, callback) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                confirmButtonColor: '#0369a1'
            }).then(() => {
                if (callback) callback();
            });
        } else {
            alert(title + ": " + text);
            if (callback) callback();
        }
    }

    function selectPaymentMethod(method) {
        const bankBox = document.getElementById('bank_details_preview');
        const boxes = document.querySelectorAll('.payment-box');
        
        boxes.forEach(b => {
            b.classList.remove('border-2', 'border-sky-700', 'bg-sky-50/40');
            b.classList.add('border-slate-200', 'bg-white');
        });

        const activeBox = document.getElementById('box_' + method.toLowerCase());
        const activeRadio = document.getElementById('radio_' + method.toLowerCase());
        
        if (activeRadio) activeRadio.checked = true;
        if (activeBox) {
            activeBox.classList.add('border-2', 'border-sky-700', 'bg-sky-50/40');
            activeBox.classList.remove('border-slate-200', 'bg-white');
        }

        if (bankBox) {
            if (method.toLowerCase() === 'bank_transfer') {
                bankBox.classList.remove('hidden');
                bankBox.style.display = 'block';
            } else {
                bankBox.classList.add('hidden');
                bankBox.style.display = 'none';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const checkoutForm = document.getElementById('checkout-form');
        const placeOrderBtn = document.getElementById('place-order-btn');
        const btnText = placeOrderBtn?.querySelector('.btn-text');
        const btnLoading = placeOrderBtn?.querySelector('.btn-loading');

        function resetButton() {
            if (placeOrderBtn) placeOrderBtn.disabled = false;
            btnText?.classList.remove('hidden');
            btnLoading?.classList.add('hidden');
            btnLoading?.classList.remove('flex');
        }

        checkoutForm?.addEventListener('submit', function(e) {
            e.preventDefault();

            const shippingSelected = document.querySelector('input[name="shipping_method"]:checked');
            if (!shippingSelected) {
                showAlert('warning', 'Shipping Method Required', 'Please select a Province and District to choose your delivery option.');
                return;
            }

            if (placeOrderBtn) placeOrderBtn.disabled = true;
            btnText?.classList.add('hidden');
            btnLoading?.classList.remove('hidden');
            btnLoading?.classList.add('flex');

            const formData = new FormData(checkoutForm);
            formData.set('action', 'place_order');

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.payment_type === 'payhere' && data.payhere_data) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = data.action_url;
                        for (const key in data.payhere_data) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = data.payhere_data[key];
                            form.appendChild(input);
                        }
                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        showAlert('success', 'Order Placed Successfully!', 'Your Order #' + data.order_number + ' has been registered.', function() {
                            window.location.href = '/account';
                        });
                    }
                } else {
                    resetButton();
                    showAlert('error', 'Order Failed', data.message || 'Something went wrong while placing your order.');
                }
            })
            .catch(err => {
                console.error(err);
                resetButton();
                showAlert('error', 'Server Error', 'Unable to process order. Please try again.');
            });
        });
    });
</script>