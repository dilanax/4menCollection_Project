<style>
    .custom-scrollbar::-webkit-scrollbar { height: 4px; width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    .star-rating-wrap input[type="radio"]:checked ~ label {
        color: #fbbf24;
    }
</style>

<script>
    function switchTab(tab) {
        const descTab = document.getElementById('tab-desc');
        const reviewsTab = document.getElementById('tab-reviews');
        const btnDesc = document.getElementById('tab-btn-desc');
        const btnReviews = document.getElementById('tab-btn-reviews');

        if (tab === 'desc') {
            descTab.classList.remove('hidden');
            reviewsTab.classList.add('hidden');
            btnDesc.classList.replace('text-slate-400', 'text-sky-700');
            btnDesc.classList.add('border-b-2', 'border-sky-700');
            btnReviews.classList.replace('text-sky-700', 'text-slate-400');
            btnReviews.classList.remove('border-b-2', 'border-sky-700');
        } else {
            reviewsTab.classList.remove('hidden');
            descTab.classList.add('hidden');
            btnReviews.classList.replace('text-slate-400', 'text-sky-700');
            btnReviews.classList.add('border-b-2', 'border-sky-700');
            btnDesc.classList.replace('text-sky-700', 'text-slate-400');
            btnDesc.classList.remove('border-b-2', 'border-sky-700');
        }
    }

    function changeQty(change) {
        const input = document.getElementById('qty-input');
        if (!input) return;
        let current = parseInt(input.value) || 1;
        let min = parseInt(input.getAttribute('min')) || 1;
        let max = parseInt(input.getAttribute('max')) || 999;
        
        let newVal = current + change;
        if (newVal >= min && newVal <= max) {
            input.value = newVal;
        }
    }

    function changeMainImage(src) {
        document.getElementById('main-product-image').src = src;
    }

    <?php if ($product['type'] == 'variable'): ?>
        const variations = <?= json_encode($variations); ?>;
        const selectors = document.querySelectorAll('.variation-selector');
        const priceContainer = document.getElementById('price-container');
        const stockDisplay = document.getElementById('stock-status-display');
        const inStockActions = document.getElementById('in-stock-actions');
        const preorderBox = document.getElementById('preorder-container');
        const varIdInput = document.getElementById('selected_variation_id');
        const mainImage = document.getElementById('main-product-image');
        const originalImageSrc = mainImage.src;
        const skuDisplay = document.getElementById('meta-sku');
        const originalSku = "<?= htmlspecialchars($product['sku'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?>";
        const qtyInput = document.getElementById('qty-input');

        function checkVariations() {
            let selectedAttributes = [];
            selectors.forEach(sel => {
                if (sel.checked) selectedAttributes.push(sel.value);
            });

            if (selectedAttributes.length < <?= count($attributes); ?>) return;

            let targetName = selectedAttributes.join(' - ');
            let matchedVariation = variations.find(v => v.variation_name === targetName);

            if (matchedVariation) {
                let priceHTML = '';
                if (matchedVariation.sale_price && matchedVariation.sale_price > 0) {
                    priceHTML = `<span class="text-3xl sm:text-4xl font-black text-rose-600 tracking-tight">Rs. ${parseFloat(matchedVariation.sale_price).toFixed(2)}</span>
                                 <span class="text-base sm:text-lg text-slate-400 line-through font-semibold">Rs. ${parseFloat(matchedVariation.regular_price).toFixed(2)}</span>`;
                } else {
                    priceHTML = `<span class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Rs. ${parseFloat(matchedVariation.regular_price).toFixed(2)}</span>`;
                }
                priceContainer.innerHTML = priceHTML;

                if (matchedVariation.image) mainImage.src = '/' + matchedVariation.image;
                else mainImage.src = originalImageSrc;

                skuDisplay.innerText = matchedVariation.sku ? matchedVariation.sku : originalSku;
                varIdInput.value = matchedVariation.id;
                
                let vStock = parseInt(matchedVariation.stock_quantity);
                if (vStock > 0 || isNaN(vStock)) {
                    stockDisplay.innerHTML = `<span class="inline-flex items-center text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full"><span class="w-2 h-2 rounded-full bg-emerald-600 mr-1.5 animate-pulse"></span> In Stock ${!isNaN(vStock) ? '('+vStock+' left)' : ''}</span>`;
                    if (inStockActions) inStockActions.classList.remove('hidden');
                    if (preorderBox) preorderBox.classList.add('hidden');
                    if (!isNaN(vStock)) qtyInput.setAttribute('max', vStock);
                } else {
                    stockDisplay.innerHTML = `<span class="inline-flex items-center text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 px-3 py-1 rounded-full"><span class="w-2 h-2 rounded-full bg-rose-600 mr-1.5"></span> Out of Stock</span>`;
                    if (inStockActions) inStockActions.classList.add('hidden');
                    if (preorderBox) preorderBox.classList.remove('hidden');
                }
            } else {
                priceContainer.innerHTML = `<span class="text-xs font-bold text-rose-600">Selected format is currently unavailable</span>`;
                if (inStockActions) inStockActions.classList.add('hidden');
                if (preorderBox) preorderBox.classList.remove('hidden');
                stockDisplay.innerHTML = '';
            }
        }

        selectors.forEach(sel => {
            sel.addEventListener('change', checkVariations);
        });
    <?php endif; ?>

    function submitProductAction(actionType, buttonElement) {
        <?php if ($product['type'] == 'variable'): ?>
            let selectedCount = document.querySelectorAll('.variation-selector:checked').length;
            if (selectedCount < <?= count($attributes); ?>) {
                document.getElementById('variation-notice').classList.remove('hidden');
                return;
            }
        <?php endif; ?>

        const form = document.getElementById('product-action-form');
        const formData = new FormData(form);
        const qtyValue = parseInt(document.getElementById('qty-input').value) || 1;
        
        formData.append('action', actionType);
        formData.set('quantity', qtyValue); 

        const btnText = buttonElement.querySelector('.btn-text');
        const btnLoading = buttonElement.querySelector('.btn-loading');
        buttonElement.disabled = true;
        btnText.classList.add('hidden');
        btnLoading.classList.remove('hidden');
        btnLoading.classList.add('flex');

        fetch(window.location.href, { 
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (actionType === 'buy_now') {
                    window.location.href = data.redirect;
                } else {
                    document.dispatchEvent(new CustomEvent('cartUpdated', { 
                        detail: { 
                            quantity: qtyValue,
                            message: data.message || 'Book added to your basket!' 
                        } 
                    }));

                    const toast = document.getElementById('toast-container');
                    if (toast) {
                        const msgEl = document.getElementById('toast-msg');
                        if (msgEl) msgEl.innerText = data.message || 'Book added to your basket!';
                        toast.classList.remove('hidden');
                        setTimeout(() => toast.classList.remove('translate-x-full', 'opacity-0'), 10);
                        setTimeout(() => {
                            toast.classList.add('translate-x-full', 'opacity-0');
                            setTimeout(() => toast.classList.add('hidden'), 300);
                        }, 3500);
                    }
                    
                    buttonElement.disabled = false;
                    btnText.classList.remove('hidden');
                    btnLoading.classList.add('hidden');
                    btnLoading.classList.remove('flex');
                }
            } else {
                alert(data.message || 'An error occurred while processing.');
                buttonElement.disabled = false;
                btnText.classList.remove('hidden');
                btnLoading.classList.add('hidden');
            }
        })
        .catch(err => {
            console.error(err);
            alert('A network connection error occurred.');
            buttonElement.disabled = false;
            btnText.classList.remove('hidden');
            btnLoading.classList.add('hidden');
        });
    }

    document.getElementById('add-to-cart-btn')?.addEventListener('click', function() {
        submitProductAction('add', this);
    });

    document.getElementById('buy-now-btn')?.addEventListener('click', function() {
        submitProductAction('buy_now', this);
    });

    // Handle Pre-Order AJAX Form Submission (Routed safely via current page ajax_handler)
    document.getElementById('product-preorder-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = document.getElementById('preorder-submit-btn');
        const feedback = document.getElementById('preorder-msg');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerText = "Submitting Pre-Order...";
        feedback.classList.add('hidden');

        const formData = new FormData(form);
        formData.append('action', 'preorder_notify');

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async res => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error("Server returned invalid response.");
            }
        })
        .then(data => {
            feedback.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-700', 'border-emerald-200', 'bg-rose-50', 'text-rose-700', 'border-rose-200');
            if (data.status === 'success') {
                feedback.classList.add('bg-emerald-50', 'text-emerald-700', 'border', 'border-emerald-200');
                feedback.innerText = data.message;
                form.reset();
                btn.innerText = "Pre-Order Registered!";
            } else {
                feedback.classList.add('bg-rose-50', 'text-rose-700', 'border', 'border-rose-200');
                feedback.innerText = data.message || "Failed to submit request.";
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            console.error(err);
            feedback.classList.remove('hidden');
            feedback.classList.add('bg-rose-50', 'text-rose-700', 'border', 'border-rose-200');
            feedback.innerText = err.message || "A network error occurred. Please try again.";
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });

    // Review submission handler
    const reviewForm = document.getElementById('review-form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submit-review-btn');
            const msg = document.getElementById('review-msg');
            btn.disabled = true;
            btn.innerText = 'Submitting...';

            fetch(window.location.href, {
                method: 'POST',
                body: new FormData(this),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                msg.classList.remove('hidden', 'text-emerald-600', 'text-rose-600');
                if (data.status === 'success') {
                    msg.classList.add('text-emerald-600');
                    msg.innerText = data.message;
                    reviewForm.reset();
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    msg.classList.add('text-rose-600');
                    msg.innerText = data.message;
                    btn.disabled = false;
                    btn.innerText = 'Submit Review';
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerText = 'Submit Review';
                alert('A network error occurred.');
            });
        });
    }
</script>