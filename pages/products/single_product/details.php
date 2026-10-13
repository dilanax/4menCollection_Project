<!-- Right: Book Specifications & Buying Actions -->
<div class="w-full lg:w-7/12 p-5 sm:p-8 lg:p-10 flex flex-col justify-between" style="font-family: 'Poppins', sans-serif;">
    <div>
        <!-- Publisher Badge & Stock Status -->
        <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
            <?php if (!empty($product['brand_name'])): ?>
                <a href="/shop?brands[]=<?= (int)$product['brand_id']; ?>" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-800 hover:bg-sky-100 border border-sky-200/80 transition-colors">
                    <svg class="w-3.5 h-3.5 text-sky-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="truncate max-w-[180px] sm:max-w-xs"><?= htmlspecialchars($product['brand_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php else: ?>
                <span class="text-[11px] font-extrabold text-sky-800 uppercase tracking-widest">ReadersHub Edition</span>
            <?php endif; ?>

            <!-- Stock Status Pill (Real-Time Stock Counter) -->
            <div id="stock-status-display" class="shrink-0">
                <?php if ($product['type'] == 'simple'): ?>
                    <?php 
                    $current_qty = (int)($product['stock_quantity'] ?? 0);
                    $is_in_stock = ($product['manage_stock'] == 1) ? ($current_qty > 0) : ($product['stock_status'] === 'instock');
                    ?>

                    <?php if ($is_in_stock): ?>
                        <span class="inline-flex items-center text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-emerald-600 mr-1.5 animate-pulse"></span>
                            In Stock <?= ($product['manage_stock'] == 1) ? "({$current_qty} left)" : ''; ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 px-3 py-1 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-rose-600 mr-1.5"></span>
                            Out of Stock
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Book Title -->
        <h1 class="text-xl sm:text-2xl lg:text-3xl xl:text-4xl font-black text-slate-900 tracking-tight mb-3 leading-snug">
            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>
        </h1>

        <!-- 5-Star Rating Row -->
        <div class="flex items-center gap-2 mb-5">
            <div class="flex items-center gap-0.5">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php if ($avg_rating >= $i): ?>
                        <svg class="w-4 h-4 text-amber-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php elseif ($avg_rating >= ($i - 0.5)): ?>
                        <svg class="w-4 h-4 text-amber-400 fill-current" viewBox="0 0 20 20"><defs><linearGradient id="single-star-grad"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="#e2e8f0"/></linearGradient></defs><path fill="url(#single-star-grad)" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php else: ?>
                        <svg class="w-4 h-4 text-slate-200 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>

            <?php if ($total_reviews > 0): ?>
                <span class="text-xs font-bold text-slate-800"><?= number_format($avg_rating, 1); ?></span>
                <span class="text-slate-300">|</span>
                <a href="#reviews-section" onclick="switchTab('reviews')" class="text-xs font-semibold text-sky-700 hover:underline">
                    <?= $total_reviews; ?> <?= $total_reviews === 1 ? 'Review' : 'Reviews'; ?>
                </a>
            <?php else: ?>
                <span class="text-xs font-medium text-slate-400">No reviews yet</span>
            <?php endif; ?>
        </div>

        <!-- Pricing Card -->
        <div class="bg-gradient-to-r from-slate-50 to-sky-50/40 border border-slate-200/90 rounded-2xl p-4 sm:p-5 mb-5 flex flex-wrap items-baseline gap-3" id="price-container">
            <?php if ($product['type'] == 'variable'): ?>
                <span class="text-sm sm:text-base font-bold text-slate-800">Select Edition / Format Below</span>
            <?php else: ?>
                <?php if ($isOnSale): ?>
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-rose-600 tracking-tight">Rs. <?= number_format($currentPrice, 2); ?></span>
                    <span class="text-sm sm:text-base text-slate-400 line-through font-semibold">Rs. <?= number_format($product['regular_price'], 2); ?></span>
                    <span class="text-[11px] font-black uppercase text-rose-700 bg-rose-100 px-2 py-0.5 rounded-md ml-auto">Save <?= $discount_percentage; ?>%</span>
                <?php else: ?>
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">Rs. <?= number_format($currentPrice, 2); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Synopsis / Short Description -->
        <?php if (!empty($product['short_description'])): ?>
            <div class="text-slate-600 text-xs sm:text-sm leading-relaxed mb-6">
                <?= nl2br(htmlspecialchars($product['short_description'], ENT_QUOTES, 'UTF-8')); ?>
            </div>
        <?php endif; ?>

        <!-- Purchase Actions Form -->
        <form id="product-action-form" class="space-y-5">
            <input type="hidden" name="product_id" value="<?= $product_id; ?>">
            <input type="hidden" name="variation_id" id="selected_variation_id" value="">

            <!-- Variations Picker -->
            <?php if ($product['type'] == 'variable' && !empty($attributes)): ?>
                <div class="space-y-4 bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs">
                    <?php foreach ($attributes as $attr_name => $terms): ?>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-2">
                                Select <?= htmlspecialchars($attr_name, ENT_QUOTES, 'UTF-8'); ?> <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($terms as $term): ?>
                                    <label class="cursor-pointer">
                                        <input 
                                            type="radio" 
                                            name="attribute_<?= md5($attr_name); ?>" 
                                            value="<?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8'); ?>" 
                                            class="peer sr-only variation-selector" 
                                            data-attr-name="<?= htmlspecialchars($attr_name, ENT_QUOTES, 'UTF-8'); ?>" 
                                            required
                                        >
                                        <div class="px-3.5 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 peer-checked:border-sky-700 peer-checked:bg-sky-700 peer-checked:text-white transition shadow-xs hover:border-slate-400 select-none">
                                            <?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p id="variation-notice" class="text-xs font-bold text-rose-600 hidden">Please select all required formats before purchasing.</p>
                </div>
            <?php endif; ?>

            <?php 
            // In-stock state check for Simple Product
            $is_simple_out = ($product['type'] == 'simple' && !$is_in_stock);
            ?>

            <!-- Normal In-Stock Buying Controls -->
            <div id="in-stock-actions" class="space-y-3 pt-1 <?= $is_simple_out ? 'hidden' : ''; ?>">
                
                <!-- Row 1: Quantity Stepper + Add to Basket -->
                <div class="flex flex-row items-stretch gap-2.5 sm:gap-3 w-full">
                    <div class="flex items-center border-2 border-slate-200 rounded-xl bg-slate-50 h-12 sm:h-14 w-32 shrink-0 overflow-hidden shadow-xs focus-within:border-sky-700 transition-colors">
                        <button type="button" onclick="changeQty(-1)" class="w-10 h-full flex items-center justify-center text-slate-600 hover:bg-sky-100 hover:text-sky-800 font-black text-base transition focus:outline-none select-none">−</button>
                        <input 
                            type="number" 
                            id="qty-input" 
                            name="quantity" 
                            value="<?= max(1, (int)$product['min_purchase_qty']); ?>" 
                            min="<?= max(1, (int)$product['min_purchase_qty']); ?>" 
                            max="<?= $product['max_purchase_qty'] ? (int)$product['max_purchase_qty'] : ($product['manage_stock'] ? (int)$product['stock_quantity'] : 999); ?>" 
                            class="w-12 h-full text-center font-black text-slate-900 border-none p-0 focus:ring-0 bg-transparent text-sm select-none"
                            readonly
                        >
                        <button type="button" onclick="changeQty(1)" class="w-10 h-full flex items-center justify-center text-slate-600 hover:bg-sky-100 hover:text-sky-800 font-black text-base transition focus:outline-none select-none">+</button>
                    </div>

                    <button 
                        type="button" 
                        id="add-to-cart-btn" 
                        class="flex-1 h-12 sm:h-14 bg-slate-900 hover:bg-slate-800 active:bg-slate-950 text-white font-extrabold text-xs sm:text-sm uppercase tracking-wider rounded-xl transition flex items-center justify-center gap-2 shadow-xs active:scale-98"
                    >
                        <span class="btn-text flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 stroke-[2.2] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <span>Add to Basket</span>
                        </span>
                        <span class="btn-loading hidden items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </span>
                    </button>
                </div>

                <!-- Row 2: Full Width Instant Buy Now -->
                <button 
                    type="button" 
                    id="buy-now-btn" 
                    class="w-full h-12 sm:h-14 bg-sky-700 hover:bg-sky-800 active:bg-sky-900 text-white font-black text-xs sm:text-sm uppercase tracking-wider rounded-xl transition flex items-center justify-center gap-2 shadow-md shadow-sky-700/25 active:scale-98"
                >
                    <span class="btn-text flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Buy It Now (Fast Checkout)</span>
                    </span>
                    <span class="btn-loading hidden items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </span>
                </button>

            </div>
        </form>

        <!-- Pre-Order Form Box (Auto-revealed when stock reaches 0) -->
        <div id="preorder-container" class="<?= $is_simple_out ? 'block' : 'hidden'; ?> mt-5 p-5 sm:p-6 bg-gradient-to-br from-amber-50 via-orange-50/40 to-white border-2 border-dashed border-amber-300 rounded-3xl shadow-xs">
            <div class="flex items-center gap-2 mb-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                </span>
                <span class="text-[11px] font-black uppercase tracking-wider text-rose-700 bg-rose-50 border border-rose-200/80 px-2.5 py-0.5 rounded-md">Out of Stock</span>
            </div>

            <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">Pre-Order / Get Restock Alert</h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Provide your contact details below. We'll send you an instant notification email as soon as fresh copies arrive at our warehouse!
            </p>

            <form id="product-preorder-form" class="mt-4 space-y-3">
                <input type="hidden" name="product_id" value="<?= $product_id; ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Your Name *</label>
                        <input type="text" name="name" required placeholder="John Doe" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 outline-none focus:border-sky-700 transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Email Address *</label>
                        <input type="email" name="email" required placeholder="you@example.com" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 outline-none focus:border-sky-700 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Phone Number (Optional)</label>
                    <input type="tel" name="phone" placeholder="+94 7X XXX XXXX" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 outline-none focus:border-sky-700 transition">
                </div>

                <div id="preorder-msg" class="hidden p-3 rounded-xl text-xs font-bold"></div>

                <button type="submit" id="preorder-submit-btn" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-3.5 px-5 rounded-xl text-xs uppercase tracking-wider transition shadow-md active:scale-98 flex items-center justify-center gap-2">
                    <span>Pre-Order & Notify Me &rarr;</span>
                </button>
            </form>
        </div>

        <!-- Trust Ribbon -->
        <div class="grid grid-cols-3 gap-2 sm:gap-3 pt-6 mt-6 border-t border-slate-100 text-center">
            <div class="p-2.5 sm:p-3 rounded-xl bg-slate-50/90 border border-slate-200/80">
                <span class="text-[11px] sm:text-xs font-bold text-slate-800 block truncate">Islandwide Post</span>
                <span class="text-[9px] sm:text-[10px] text-slate-400 font-medium block">2-4 business days</span>
            </div>
            <div class="p-2.5 sm:p-3 rounded-xl bg-slate-50/90 border border-slate-200/80">
                <span class="text-[11px] sm:text-xs font-bold text-slate-800 block truncate">Cash on Delivery</span>
                <span class="text-[9px] sm:text-[10px] text-slate-400 font-medium block">Doorstep payment</span>
            </div>
            <div class="p-2.5 sm:p-3 rounded-xl bg-slate-50/90 border border-slate-200/80">
                <span class="text-[11px] sm:text-xs font-bold text-slate-800 block truncate">100% Genuine</span>
                <span class="text-[9px] sm:text-[10px] text-slate-400 font-medium block">Original publishers</span>
            </div>
        </div>
    </div>

    <!-- Book Metadata -->
    <div class="mt-6 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1.5">
        <p><span class="font-bold text-slate-700">ISBN / SKU:</span> <span id="meta-sku" class="font-mono"><?= htmlspecialchars($product['sku'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></span></p>
        <p><span class="font-bold text-slate-700">Categories:</span> 
            <?php foreach ($categories as $index => $cat): ?>
                <a href="/category/<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="text-sky-700 font-semibold hover:underline">
                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?><?= ($index < count($categories) - 1) ? ',' : ''; ?>
                </a>
            <?php endforeach; ?>
        </p>
        <?php if (!empty($product['tags'])): ?>
            <p><span class="font-bold text-slate-700">Tags:</span> <?= htmlspecialchars($product['tags'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
</div>