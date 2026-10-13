<?php
// $p (product array) required
$isOnSale = !empty($p['sale_price']) && (float)$p['sale_price'] > 0;
$currentPrice = $isOnSale ? (float)$p['sale_price'] : (float)$p['regular_price'];
$imageSrc = !empty($p['main_image']) ? '/' . ltrim(htmlspecialchars($p['main_image']), '/') : '/assets/placeholder.jpg';
$brandName = !empty($p['brand_name']) ? htmlspecialchars($p['brand_name']) : '';
$productUrl = "/product/" . htmlspecialchars($p['slug']);
$isOutOfStock = (($p['stock_status'] ?? '') === 'outofstock');

$discountPct = 0;
if ($isOnSale && (float)$p['regular_price'] > 0) {
    $discountPct = round((((float)$p['regular_price'] - $currentPrice) / (float)$p['regular_price']) * 100);
}

// 5 Star Rating Logic (No review = 0 rating, all gray stars)
$avgRating = 0.0;
$totalReviews = 0;

if (isset($p['avg_rating']) && $p['avg_rating'] !== null) {
    $avgRating = round((float)$p['avg_rating'], 1);
    $totalReviews = (int)($p['review_count'] ?? 0);
} elseif (isset($pdo) && $pdo instanceof PDO && !empty($p['id'])) {
    try {
        $stmt_r = $pdo->prepare("SELECT AVG(rating) as avg_r, COUNT(id) as count_r FROM product_reviews WHERE product_id = ?");
        $stmt_r->execute([(int)$p['id']]);
        $res_r = $stmt_r->fetch();
        if ($res_r && $res_r['count_r'] > 0) {
            $avgRating = round((float)$res_r['avg_r'], 1);
            $totalReviews = (int)$res_r['count_r'];
        }
    } catch (Throwable $e) {
        $avgRating = 0.0;
        $totalReviews = 0;
    }
}
?>

<!-- Modern Book Card with Rounded Corners & Deep Blue Theme -->
<div class="group relative bg-white border border-slate-200/90 hover:border-sky-700 hover:shadow-xl rounded-2xl transition-all duration-300 flex flex-col h-full overflow-hidden" style="font-family: 'Poppins', sans-serif;">
    
    <!-- 1. Book Cover Stage (Aspect Ratio 2:3 with full edge-to-edge cover) -->
    <div class="relative w-full aspect-[2/3] bg-slate-100 overflow-hidden border-b border-slate-100 rounded-t-2xl">
        
        <!-- Top-Left Discount / Stock Badge -->
        <?php if ($isOnSale): ?>
            <span class="absolute top-2.5 left-2.5 z-20 bg-rose-600 text-white text-[10px] font-black px-2 py-0.5 rounded-md shadow-sm">
                -<?= $discountPct; ?>%
            </span>
        <?php elseif ($isOutOfStock): ?>
            <span class="absolute top-2.5 left-2.5 z-20 bg-slate-900 text-white text-[10px] font-bold px-2 py-0.5 rounded-md shadow-sm">
                Out of Stock
            </span>
        <?php endif; ?>

        <!-- Wishlist Icon (Top-Right) -->
        <button type="button" class="absolute top-2.5 right-2.5 z-20 w-8 h-8 bg-white/95 text-slate-400 hover:text-rose-600 rounded-full flex items-center justify-center shadow-sm transition-colors" title="Add to Wishlist">
            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </button>

        <!-- Book Image with Full Cover Fit -->
        <a href="<?= $productUrl; ?>" class="block w-full h-full">
            <img 
                src="<?= $imageSrc; ?>" 
                alt="<?= htmlspecialchars($p['name']); ?>" 
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out"
                loading="lazy"
            >
        </a>

        <!-- Slide-Up Action Bar on Hover (Add to Cart & Quick View) -->
        <div class="absolute inset-x-0 bottom-0 p-2.5 z-20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out bg-gradient-to-t from-slate-950/80 via-slate-950/40 to-transparent flex items-center gap-1.5">
            
            <?php if ($p['type'] === 'variable'): ?>
                <a href="<?= $productUrl; ?>" class="flex-1 py-2 bg-sky-700 hover:bg-sky-800 text-white text-[11px] font-bold uppercase tracking-wider flex items-center justify-center gap-1 transition-colors shadow-sm rounded-lg">
                    <span>Options</span>
                    <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            <?php else: ?>
                <?php if (!$isOutOfStock): ?>
                    <button 
                        type="button" 
                        onclick="quickAddToCart(this, <?= (int)$p['id']; ?>)" 
                        class="flex-1 py-2 bg-sky-700 hover:bg-sky-800 text-white text-[11px] font-bold uppercase tracking-wider flex items-center justify-center gap-1.5 transition-colors shadow-sm rounded-lg group/btn active:scale-95" 
                        title="Add to Cart"
                    >
                        <!-- Default Cart Text & Icon -->
                        <span class="flex items-center gap-1.5 group-[.is-loading]:hidden group-[.is-success]:hidden">
                            <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span>Add to Cart</span>
                        </span>

                        <!-- Loading Spinner -->
                        <span class="hidden group-[.is-loading]:flex items-center justify-center">
                            <svg class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>

                        <!-- Success Check -->
                        <span class="hidden group-[.is-success]:flex items-center gap-1 text-white">
                            <svg class="w-3.5 h-3.5 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Added</span>
                        </span>
                    </button>
                <?php else: ?>
                    <span class="flex-1 py-2 bg-slate-800 text-slate-300 text-[11px] font-bold uppercase text-center rounded-lg cursor-not-allowed">
                        Sold Out
                    </span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Quick View Button -->
            <a href="<?= $productUrl; ?>" class="w-8 h-8 bg-white/90 hover:bg-white text-slate-800 rounded-lg flex items-center justify-center shrink-0 transition-colors shadow-sm" title="Quick View">
                <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </a>

        </div>

    </div>

    <!-- 2. Typography & Details -->
    <div class="p-3.5 sm:p-4 flex flex-col flex-1 bg-white rounded-b-2xl">
        
        <!-- Author / Publisher Name -->
        <span class="text-[10px] sm:text-[11px] font-bold text-sky-800 uppercase tracking-widest truncate mb-1">
            <?= $brandName ?: 'ReadersHub'; ?>
        </span>

        <!-- Book Title -->
        <h3 class="mb-1.5 leading-snug">
            <a href="<?= $productUrl; ?>" class="font-bold text-slate-900 text-xs sm:text-sm hover:text-sky-700 line-clamp-2 transition-colors">
                <?= htmlspecialchars($p['name']); ?>
            </a>
        </h3>

        <!-- 5-Star Rating Row -->
        <div class="flex items-center gap-1.5 mb-2.5">
            <div class="flex items-center gap-0.5">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php if ($avgRating >= $i): ?>
                        <!-- Full Amber Star -->
                        <svg class="w-3.5 h-3.5 text-amber-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php elseif ($avgRating >= ($i - 0.5)): ?>
                        <!-- Half Amber Star -->
                        <svg class="w-3.5 h-3.5 text-amber-400 fill-current" viewBox="0 0 20 20"><defs><linearGradient id="half-star-<?= (int)$p['id'] ?>"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="#e2e8f0"/></linearGradient></defs><path fill="url(#half-star-<?= (int)$p['id'] ?>)" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php else: ?>
                        <!-- Completely Gray / Colorless Empty Star -->
                        <svg class="w-3.5 h-3.5 text-slate-200 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            
            <?php if ($totalReviews > 0): ?>
                <span class="text-[10px] font-bold text-slate-600">
                    <?= number_format($avgRating, 1); ?>
                    <span class="text-slate-400 font-normal">(<?= $totalReviews; ?>)</span>
                </span>
            <?php else: ?>
                <span class="text-[10px] text-slate-400 font-medium">(0)</span>
            <?php endif; ?>
        </div>

        <!-- Pricing Section -->
        <div class="mt-auto pt-2 border-t border-slate-100 flex items-baseline gap-2">
            <?php if ($p['type'] === 'variable'): ?>
                <span class="text-[10px] font-bold text-slate-400 uppercase">From</span>
                <span class="text-sm sm:text-base font-black text-slate-900 tracking-tight">
                    Rs. <?= number_format($p['regular_price'] ?? 0, 2); ?>
                </span>
            <?php else: ?>
                <?php if ($isOnSale): ?>
                    <span class="text-sm sm:text-base font-black text-rose-600 tracking-tight">
                        Rs. <?= number_format($p['sale_price'], 2); ?>
                    </span>
                    <span class="text-xs font-semibold text-slate-400 line-through">
                        Rs. <?= number_format($p['regular_price'], 2); ?>
                    </span>
                <?php else: ?>
                    <span class="text-sm sm:text-base font-black text-slate-900 tracking-tight">
                        Rs. <?= number_format($p['regular_price'] ?? 0, 2); ?>
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php 
if (!defined('QUICK_CART_SCRIPT_LOADED')): 
define('QUICK_CART_SCRIPT_LOADED', true); 
?>
<script>
    // Universal Toast Notification Handler
    function triggerUniversalToast(message = 'Book added to your cart!') {
        let toast = document.getElementById('toast-container');
        
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-container';
            toast.className = 'fixed top-24 right-5 z-[9999] transform translate-x-full transition-all duration-300 opacity-0 pointer-events-none';
            toast.innerHTML = `
                <div class="bg-white border-l-4 border-sky-700 shadow-2xl p-4 flex items-center gap-3.5 min-w-[300px] max-w-sm rounded-xl border border-slate-200 pointer-events-auto" style="font-family: 'Poppins', sans-serif;">
                    <div class="w-10 h-10 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center shrink-0 border border-sky-200">
                        <svg class="w-5 h-5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Added to Basket!</h4>
                        <p id="toast-msg" class="text-xs text-slate-500 font-medium truncate mt-0.5">${message}</p>
                    </div>
                    <a href="/cart" class="text-xs font-bold text-sky-700 hover:text-sky-800 bg-sky-50 px-2.5 py-1.5 rounded-lg transition shrink-0">View Cart</a>
                </div>
            `;
            document.body.appendChild(toast);
        } else {
            const msgEl = toast.querySelector('#toast-msg') || document.getElementById('toast-msg');
            if (msgEl) msgEl.innerText = message;
        }

        toast.classList.remove('hidden', 'translate-x-full', 'opacity-0', 'pointer-events-none');
        toast.classList.add('translate-x-0', 'opacity-100');

        if (window.cardToastTimer) clearTimeout(window.cardToastTimer);
        window.cardToastTimer = setTimeout(() => {
            toast.classList.remove('translate-x-0', 'opacity-100');
            toast.classList.add('translate-x-full', 'opacity-0', 'pointer-events-none');
        }, 3000);
    }

    function quickAddToCart(btn, productId) {
        if (!btn || btn.classList.contains('is-loading')) return;

        btn.classList.add('is-loading');
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', 1); 
        formData.append('variation_id', 0); 

        fetch('/ajax_cart', { 
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async response => {
            const textResponse = await response.text();
            try {
                const data = JSON.parse(textResponse);
                if (response.ok && data.status === 'success') {
                    return data; 
                } else {
                    throw new Error(data.message || 'Server error occurred.');
                }
            } catch (e) {
                throw new Error("Invalid response format.");
            }
        })
        .then(data => {
            btn.classList.remove('is-loading');
            btn.classList.add('is-success');

            const alertMsg = data.message || 'Book added to your cart!';

            document.dispatchEvent(new CustomEvent('cartUpdated', { 
                detail: { 
                    quantity: 1, 
                    message: alertMsg 
                } 
            }));

            triggerUniversalToast(alertMsg);

            setTimeout(() => {
                btn.classList.remove('is-success');
                btn.disabled = false;
            }, 1800);
        })
        .catch(error => {
            console.error('Cart Error:', error);
            btn.classList.remove('is-loading');
            btn.disabled = false;
            alert('Failed to add book to cart. Please try again.');
        });
    }
</script>
<?php endif; ?>