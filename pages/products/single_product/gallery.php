<!-- Left: Book Gallery Column -->
<div class="w-full lg:w-5/12 p-6 sm:p-10 border-b lg:border-b-0 lg:border-r border-slate-100 flex flex-col items-center justify-start bg-gradient-to-b from-slate-50/60 to-white">
    
    <!-- Main Image Stage (Aspect Ratio 2:3) -->
    <div class="relative w-full max-w-[320px] aspect-[2/3] bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 shadow-sm mb-6 flex items-center justify-center group">
        <?php if ($isOnSale): ?>
            <span class="absolute top-3 left-3 bg-rose-600 text-white text-[10px] font-black px-2.5 py-1 rounded-md uppercase tracking-wider shadow-sm z-10">
                -<?= $discount_percentage; ?>% OFF
            </span>
        <?php endif; ?>

        <img 
            id="main-product-image" 
            src="/<?= htmlspecialchars(ltrim($product['main_image'] ?? 'assets/placeholder.jpg', '/'), ENT_QUOTES, 'UTF-8'); ?>" 
            alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
        >
    </div>

    <!-- Gallery Thumbnails -->
    <?php if (count($gallery_images) > 1): ?>
        <div class="flex gap-2.5 overflow-x-auto max-w-full pb-2 custom-scrollbar">
            <?php foreach ($gallery_images as $img): ?>
                <button 
                    type="button"
                    onclick="changeMainImage('/<?= htmlspecialchars(ltrim($img, '/'), ENT_QUOTES, 'UTF-8'); ?>')" 
                    class="w-14 h-18 sm:w-16 sm:h-20 bg-slate-100 rounded-xl overflow-hidden border-2 border-slate-200 hover:border-sky-700 transition shrink-0 focus:outline-none focus:border-sky-700 shadow-xs"
                >
                    <img src="/<?= htmlspecialchars(ltrim($img, '/'), ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover">
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>