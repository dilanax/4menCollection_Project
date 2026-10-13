<?php
$isOnSale = !empty($p['sale_price']) && (float)$p['sale_price'] > 0;
$currentPrice = $isOnSale ? (float)$p['sale_price'] : (float)$p['regular_price'];
$imageSrc = !empty($p['main_image']) ? '/' . ltrim(htmlspecialchars($p['main_image']), '/') : '/assets/placeholder.jpg';
$productUrl = "/product/" . htmlspecialchars($p['slug']);
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-3 hover:border-sky-300 hover:shadow-md transition-all flex items-center gap-3.5 group h-full">
    <!-- Mini Book Thumbnail -->
    <a href="<?= $productUrl; ?>" class="block w-20 h-24 bg-slate-50 rounded-xl shrink-0 overflow-hidden relative border border-slate-100 p-1">
        <?php if ($isOnSale): ?>
            <span class="absolute top-1 left-1 bg-rose-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded uppercase z-10 shadow-xs">Sale</span>
        <?php endif; ?>
        <img src="<?= $imageSrc; ?>" alt="<?= htmlspecialchars($p['name']); ?>" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300 drop-shadow-sm">
    </a>
    
    <!-- Info -->
    <div class="flex-1 min-w-0 py-0.5">
        <a href="<?= $productUrl; ?>" class="font-bold text-slate-800 text-xs sm:text-sm mb-1 hover:text-sky-600 transition truncate block leading-snug">
            <?= htmlspecialchars($p['name']); ?>
        </a>
        <div class="mb-2">
            <?php if ($p['type'] == 'variable'): ?>
                <span class="text-xs sm:text-sm font-black text-slate-900">From Rs. <?= number_format($p['regular_price'] ?? 0, 2); ?></span>
            <?php else: ?>
                <?php if ($isOnSale): ?>
                    <span class="text-[11px] text-slate-400 line-through mr-1 font-medium">Rs. <?= number_format($p['regular_price'], 2); ?></span>
                    <span class="text-xs sm:text-sm font-black text-rose-600">Rs. <?= number_format($p['sale_price'], 2); ?></span>
                <?php else: ?>
                    <span class="text-xs sm:text-sm font-black text-slate-900">Rs. <?= number_format($p['regular_price'] ?? 0, 2); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if (($p['stock_status'] ?? '') === 'outofstock'): ?>
            <span class="text-[10px] font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-md">Sold Out</span>
        <?php else: ?>
            <a href="<?= $productUrl; ?>" class="inline-flex items-center gap-1 text-xs font-bold text-sky-600 hover:text-sky-700 group-hover:translate-x-0.5 transition-transform">
                View Book &rarr;
            </a>
        <?php endif; ?>
    </div>
</div>