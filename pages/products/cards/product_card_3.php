<?php
$isOnSale = !empty($p['sale_price']) && (float)$p['sale_price'] > 0;
$currentPrice = $isOnSale ? (float)$p['sale_price'] : (float)$p['regular_price'];
$imageSrc = !empty($p['main_image']) ? '/' . ltrim(htmlspecialchars($p['main_image']), '/') : '/assets/placeholder.jpg';
$productUrl = "/product/" . htmlspecialchars($p['slug']);
?>

<div class="group h-full flex flex-col bg-white rounded-2xl p-3 border border-slate-200/80 hover:border-sky-300 hover:shadow-lg transition-all">
    <!-- Image Box with Quick View Overlay -->
    <div class="relative w-full aspect-[2/3] bg-slate-50 rounded-xl overflow-hidden mb-3 p-3 flex items-center justify-center border border-slate-100">
        <a href="<?= $productUrl; ?>" class="w-full h-full flex items-center justify-center">
            <img 
                src="<?= $imageSrc; ?>" 
                alt="<?= htmlspecialchars($p['name']); ?>" 
                class="max-w-full max-h-full object-contain drop-shadow-sm group-hover:scale-105 transition-transform duration-300"
            >
        </a>
        
        <?php if ($isOnSale): ?>
            <div class="absolute top-2 left-2 bg-rose-500 text-white text-[9px] font-black px-2 py-0.5 rounded-md shadow-xs uppercase tracking-wider">
                Sale
            </div>
        <?php endif; ?>

        <!-- Slide Up Quick CTA -->
        <div class="absolute inset-x-0 bottom-0 p-2 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
            <a href="<?= $productUrl; ?>" class="block w-full bg-slate-900/90 backdrop-blur-sm text-white text-center py-2 text-xs font-bold rounded-lg hover:bg-sky-600 transition shadow-sm">
                <?= $p['type'] == 'variable' ? 'Select Edition' : 'View Title'; ?>
            </a>
        </div>
    </div>
    
    <div class="flex flex-col flex-1 px-1">
        <h3 class="text-xs sm:text-sm font-bold text-slate-800 mb-1 leading-snug line-clamp-2">
            <a href="<?= $productUrl; ?>" class="hover:text-sky-600 transition">
                <?= htmlspecialchars($p['name']); ?>
            </a>
        </h3>
        
        <div class="text-xs sm:text-sm mt-auto pt-2 flex items-center gap-1.5">
            <?php if ($p['type'] == 'variable'): ?>
                <span class="font-black text-slate-900">Rs. <?= number_format($p['regular_price'] ?? 0, 2); ?>+</span>
            <?php else: ?>
                <?php if ($isOnSale): ?>
                    <span class="line-through text-slate-400 text-[11px] font-medium">Rs. <?= number_format($p['regular_price'], 2); ?></span>
                    <span class="text-rose-600 font-black">Rs. <?= number_format($p['sale_price'], 2); ?></span>
                <?php else: ?>
                    <span class="text-slate-900 font-black">Rs. <?= number_format($p['regular_price'] ?? 0, 2); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>