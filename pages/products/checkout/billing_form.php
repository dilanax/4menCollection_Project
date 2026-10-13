<div class="bg-white p-5 sm:p-7 lg:p-8 rounded-3xl shadow-xs border border-slate-200/90 space-y-6">
    <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight pb-3.5 border-b border-slate-100 flex items-center justify-between">
        <span>Delivery & Billing Details</span>
        <span class="text-xs font-semibold text-slate-400 font-normal">* Required fields</span>
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- First Name -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">First Name *</label>
            <input type="text" name="first_name" required placeholder="John" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition">
        </div>

        <!-- Last Name -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Last Name *</label>
            <input type="text" name="last_name" required placeholder="Doe" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition">
        </div>

        <!-- Email -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Email Address *</label>
            <input type="email" name="email" required placeholder="john@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition">
        </div>

        <!-- Phone -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Phone Number *</label>
            <input type="tel" name="phone" required placeholder="0771234567" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition">
        </div>

        <!-- Street Address -->
        <div class="sm:col-span-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Street Address *</label>
            <input type="text" name="address" required placeholder="No. 123, Main Street, Temple Road" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition">
        </div>

        <!-- Province Dropdown -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Province *</label>
            <select name="province" id="checkout_province" required onchange="onProvinceChange(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition cursor-pointer">
                <option value="">-- Select Province --</option>
                <option value="Western Province">Western Province</option>
                <option value="Central Province">Central Province</option>
                <option value="Southern Province">Southern Province</option>
                <option value="North Western Province">North Western Province</option>
                <option value="Sabaragamuwa Province">Sabaragamuwa Province</option>
                <option value="Uva Province">Uva Province</option>
                <option value="North Central Province">North Central Province</option>
                <option value="Northern Province">Northern Province</option>
                <option value="Eastern Province">Eastern Province</option>
            </select>
        </div>

        <!-- District Dropdown -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">District *</label>
            <select name="district" id="checkout_district" required onchange="onDistrictChange(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition cursor-pointer">
                <option value="">-- Select Province First --</option>
            </select>
        </div>

        <!-- City / Town -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">City / Town *</label>
            <input type="text" name="city" required placeholder="e.g. Maharagama / Kandy" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition">
        </div>

        <!-- Postal / Zip Code -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Postal Code</label>
            <input type="text" name="zip_code" placeholder="e.g. 10280" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition">
        </div>

        <!-- Order Notes -->
        <div class="sm:col-span-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Order Notes (Optional)</label>
            <textarea name="order_notes" rows="2" placeholder="Special notes for delivery (e.g. Call before dispatch)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-sky-700 outline-none transition"></textarea>
        </div>
    </div>
</div>

<script>
    const slDistricts = {
        "Western Province": ["Colombo", "Gampaha", "Kalutara"],
        "Central Province": ["Kandy", "Matale", "Nuwara Eliya"],
        "Southern Province": ["Galle", "Matara", "Hambantota"],
        "North Western Province": ["Kurunegala", "Puttalam"],
        "Sabaragamuwa Province": ["Ratnapura", "Kegalle"],
        "Uva Province": ["Badulla", "Monaragala"],
        "North Central Province": ["Anuradhapura", "Polonnaruwa"],
        "Northern Province": ["Jaffna", "Kilinochchi", "Mannar", "Mullaitivu", "Vavuniya"],
        "Eastern Province": ["Ampara", "Batticaloa", "Trincomalee"]
    };

    function onProvinceChange(province) {
        const districtSelect = document.getElementById('checkout_district');
        districtSelect.innerHTML = '<option value="">-- Select District --</option>';

        if (province && slDistricts[province]) {
            slDistricts[province].forEach(function(district) {
                const opt = document.createElement('option');
                opt.value = district;
                opt.textContent = district + ' District';
                districtSelect.appendChild(opt);
            });
        }
    }

    function onDistrictChange(district) {
        const province = document.getElementById('checkout_province').value;
        if (!district) return;

        const formData = new FormData();
        formData.append('action', 'calculate_shipping');
        formData.append('district_name', district);
        formData.append('province_name', province);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.methods) {
                const container = document.getElementById('shipping_methods_container');
                const message = document.getElementById('shipping_message');
                if (message) message.classList.add('hidden');
                if (container) {
                    container.classList.remove('hidden');
                    let html = '';
                    data.methods.forEach((m, idx) => {
                        html += `
                            <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-white hover:border-sky-700 cursor-pointer text-xs">
                                <div class="flex items-center gap-2.5">
                                    <input type="radio" name="shipping_method" value="${m.id}" ${idx === 0 ? 'checked' : ''} onchange="updateShippingTotal(${m.cost})" class="text-sky-700">
                                    <span class="font-bold text-slate-800">${m.name}</span>
                                </div>
                                <span class="font-extrabold text-sky-700">${m.cost == 0 ? 'Free' : 'Rs. ' + m.formatted_cost}</span>
                            </label>
                        `;
                    });
                    container.innerHTML = html;
                    if (data.methods[0]) {
                        updateShippingTotal(data.methods[0].cost);
                    }
                }
            }
        })
        .catch(e => console.error(e));
    }

    // ACCURATE TOTAL CALCULATION (Fixes the 450.95 bug)
    function updateShippingTotal(shippingCost) {
        const subtotalEl = document.getElementById('display_subtotal');
        const totalEl = document.getElementById('display_total');
        
        if (subtotalEl && totalEl) {
            // Retrieve exact numeric subtotal from data attribute or clean string
            let rawSubtotal = parseFloat(subtotalEl.getAttribute('data-subtotal'));
            if (isNaN(rawSubtotal) || rawSubtotal <= 0) {
                let text = subtotalEl.innerText.replace(/Rs\.?/gi, '').replace(/,/g, '').trim();
                rawSubtotal = parseFloat(text) || 0;
            }

            const cost = parseFloat(shippingCost) || 0;
            const newTotal = rawSubtotal + cost;

            totalEl.innerText = 'Rs. ' + newTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
</script>