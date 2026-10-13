<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<script>
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

    const provinceSelect = document.getElementById('province_select');
    const districtSelect = document.getElementById('district_select');

    for (let province in locations) {
        provinceSelect.innerHTML += `<option value="${province}">${province}</option>`;
    }

    provinceSelect.addEventListener('change', function() {
        const selectedProv = this.value;
        districtSelect.innerHTML = '<option value="">Select District...</option>';
        
        if (selectedProv && locations[selectedProv]) {
            locations[selectedProv].forEach(dist => {
                districtSelect.innerHTML += `<option value="${dist}">${dist}</option>`;
            });
            districtSelect.disabled = false;
            districtSelect.classList.remove('bg-slate-100', 'text-slate-400', 'cursor-not-allowed');
            districtSelect.classList.add('bg-slate-50', 'text-slate-800');
        } else {
            districtSelect.disabled = true;
            districtSelect.classList.add('bg-slate-100', 'text-slate-400', 'cursor-not-allowed');
            districtSelect.classList.remove('bg-slate-50', 'text-slate-800');
            districtSelect.innerHTML = '<option value="">Select Province first...</option>';
        }
        
        districtSelect.dispatchEvent(new Event('change'));
    });

    let activeSubtotal = <?= (float)$initial_subtotal; ?>;
    let currentShippingCost = 0;

    districtSelect.addEventListener('change', function() {
        const districtName = this.value;
        const msgContainer = document.getElementById('shipping_message');
        const methodsContainer = document.getElementById('shipping_methods_container');
        const hiddenZoneId = document.getElementById('hidden_zone_id'); 
        
        if (!districtName) {
            msgContainer.innerText = 'Please select your District to see available shipping options.';
            msgContainer.classList.remove('hidden', 'bg-rose-50', 'text-rose-700', 'border-rose-200');
            msgContainer.classList.add('bg-amber-50', 'text-amber-700', 'border-amber-200');
            methodsContainer.classList.add('hidden');
            hiddenZoneId.value = '';
            updateTotal(0);
            return;
        }

        msgContainer.innerText = 'Calculating courier rates...';
        msgContainer.classList.remove('hidden', 'bg-rose-50', 'text-rose-700', 'border-rose-200');
        msgContainer.classList.add('bg-sky-50', 'text-sky-700', 'border-sky-200');
        methodsContainer.classList.add('hidden');
        methodsContainer.innerHTML = '';
        hiddenZoneId.value = '';

        const formData = new FormData();
        formData.append('action', 'calculate_shipping');
        formData.append('district_name', districtName);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                msgContainer.classList.add('hidden');
                hiddenZoneId.value = data.zone_id; 
                
                if (data.subtotal) {
                    activeSubtotal = parseFloat(data.subtotal);
                }

                if (data.methods.length === 0) {
                    msgContainer.innerText = 'No shipping methods configured for this area.';
                    msgContainer.classList.remove('bg-sky-50', 'text-sky-700', 'border-sky-200');
                    msgContainer.classList.add('bg-rose-50', 'text-rose-700', 'border-rose-200');
                    updateTotal(0);
                    return;
                }

                let html = '';
                data.methods.forEach((m, index) => {
                    const isChecked = index === 0 ? 'checked' : ''; 
                    if (index === 0) currentShippingCost = parseFloat(m.cost);

                    html += `
                        <label class="flex items-center justify-between p-3.5 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition [&:has(input:checked)]:border-sky-700 [&:has(input:checked)]:bg-sky-50/50">
                            <div class="flex items-center gap-2.5">
                                <input type="radio" name="shipping_method" value="${m.id}" data-cost="${m.cost}" ${isChecked} class="shipping-radio w-4 h-4 text-sky-700 focus:ring-sky-700">
                                <span class="text-xs sm:text-sm font-bold text-slate-800">${m.name}</span>
                            </div>
                            <span class="text-xs font-black text-slate-900">${m.cost === 0 ? 'Free Delivery' : 'Rs. ' + m.formatted_cost}</span>
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
                msgContainer.classList.remove('bg-sky-50', 'text-sky-700', 'border-sky-200');
                msgContainer.classList.add('bg-rose-50', 'text-rose-700', 'border-rose-200');
            }
        })
        .catch(err => {
            console.error(err);
            msgContainer.innerText = 'Network error occurred while calculating delivery rates.';
            msgContainer.classList.remove('bg-sky-50', 'text-sky-700', 'border-sky-200');
            msgContainer.classList.add('bg-rose-50', 'text-rose-700', 'border-rose-200');
        });
    });

    function updateTotal(shippingCost) {
        const total = activeSubtotal + shippingCost;
        const formattedTotal = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('display_total').innerText = 'Rs. ' + formattedTotal;
    }

    document.getElementById('checkout-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const btn = document.getElementById('place-order-btn');
        const btnText = btn.querySelector('.btn-text');
        const btnLoading = btn.querySelector('.btn-loading');
        const errorDiv = document.getElementById('form-error');
        
        if (!document.getElementById('hidden_zone_id').value) {
            errorDiv.innerText = "Please select a valid Province and District to calculate delivery rates.";
            errorDiv.classList.remove('hidden');
            return;
        }

        if (!form.querySelector('input[name="shipping_method"]:checked')) {
            errorDiv.innerText = "Please choose a shipping method.";
            errorDiv.classList.remove('hidden');
            return;
        }

        errorDiv.classList.add('hidden');
        btn.disabled = true;
        btnText.classList.add('hidden');
        btnLoading.classList.remove('hidden');
        btnLoading.classList.add('flex');

        const formData = new FormData(form);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                
                // 1. PayHere Auto-Redirect Flow
                if (data.payment_type === 'payhere' && data.action_url && data.payhere_data) {
                    btnText.innerText = "Redirecting to Payment Gateway...";
                    btnText.classList.remove('hidden');
                    btnLoading.classList.add('hidden');
                    btnLoading.classList.remove('flex');

                    const payhereForm = document.createElement('form');
                    payhereForm.method = 'POST';
                    payhereForm.action = data.action_url;

                    for (const key in data.payhere_data) {
                        if (data.payhere_data.hasOwnProperty(key)) {
                            const hiddenField = document.createElement('input');
                            hiddenField.type = 'hidden';
                            hiddenField.name = key;
                            hiddenField.value = data.payhere_data[key];
                            payhereForm.appendChild(hiddenField);
                        }
                    }

                    document.body.appendChild(payhereForm);
                    payhereForm.submit();
                    return;
                }

                // 2. Standard COD Confirmation Modal Flow
                document.getElementById('modal-order-number').innerText = data.order_number;
                
                const accNotice = document.getElementById('account-creation-msg');
                if (accNotice) {
                    if (data.account_created) {
                        accNotice.classList.remove('hidden');
                    } else {
                        accNotice.classList.add('hidden');
                    }
                }

                const modal = document.getElementById('success-modal');
                modal.classList.remove('hidden');
                void modal.offsetWidth; 
                modal.classList.remove('opacity-0');
                const modalBox = modal.querySelector('div');
                modalBox.classList.remove('scale-95');
                modalBox.classList.add('scale-100');

            } else {
                errorDiv.innerText = data.message || "An error occurred while placing order.";
                errorDiv.classList.remove('hidden');
                btn.disabled = false;
                btnText.classList.remove('hidden');
                btnLoading.classList.add('hidden');
                btnLoading.classList.remove('flex');
            }
        })
        .catch(err => {
            console.error(err);
            errorDiv.innerText = "A connection error occurred. Please verify your internet connection.";
            errorDiv.classList.remove('hidden');
            btn.disabled = false;
            btnText.classList.remove('hidden');
            btnLoading.classList.add('hidden');
            btnLoading.classList.remove('flex');
        });
    });
</script>