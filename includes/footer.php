<?php
// Retrieve Dynamic Business Information & Logo from email_settings
$footer_settings = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $ft_stmt = $pdo->query("SELECT business_name, business_logo, phone_number, whatsapp_number, smtp_email, address, website_url FROM email_settings WHERE id = 1 LIMIT 1");
        if ($ft_stmt) {
            $footer_settings = $ft_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $footer_settings = [];
    }
}

$footer_biz_name  = !empty($footer_settings['business_name']) ? $footer_settings['business_name'] : 'ReadersHub';
$footer_phone     = !empty($footer_settings['phone_number']) ? $footer_settings['phone_number'] : '+94 75 188 3565';
$footer_wa        = !empty($footer_settings['whatsapp_number']) ? $footer_settings['whatsapp_number'] : '0751883565';
$footer_email     = !empty($footer_settings['smtp_email']) ? $footer_settings['smtp_email'] : 'support@readershub.lk';
$footer_logo_path = !empty($footer_settings['business_logo']) ? '/' . ltrim($footer_settings['business_logo'], '/') : '';

// Clean WhatsApp number for link
$footer_clean_wa = preg_replace('/[^0-9]/', '', $footer_wa);
if (str_starts_with($footer_clean_wa, '0')) {
    $footer_clean_wa = '94' . substr($footer_clean_wa, 1);
}
?>

    <!-- Google Fonts for Footer Branding -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- ==================== FOOTER SECTION ==================== -->
    <footer class="bg-[#0f172a] text-slate-300 mt-16 font-sans relative border-t-4 border-sky-600 shadow-2xl pb-20 md:pb-0 w-full" style="font-family: 'Poppins', sans-serif;">
        
        <!-- 1. Value-Proposition Features Banner (Full Width) -->
        <div class="bg-[#1e293b] border-b border-slate-800/80 py-10 w-full">
            <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 text-center md:text-left">
                    
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-4 p-4 rounded-2xl bg-slate-900/60 border border-slate-800 shadow-sm hover:border-sky-500/50 hover:shadow-lg transition group">
                        <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0C2.678 5.57 2.25 6.05 2.25 6.618v11.007c0 .568.422 1.048.987 1.106a48.54 48.54 0 005.013.297"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-extrabold text-white tracking-wide mb-1" style="font-family: 'Outfit', sans-serif;">Free Islandwide Delivery</h4>
                            <p class="text-xs text-slate-400 leading-relaxed">For orders above Rs. 6,500, fast delivery across Sri Lanka.</p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-center md:items-start gap-4 p-4 rounded-2xl bg-slate-900/60 border border-slate-800 shadow-sm hover:border-sky-500/50 hover:shadow-lg transition group">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-extrabold text-white tracking-wide mb-1" style="font-family: 'Outfit', sans-serif;">100% Authentic Books</h4>
                            <p class="text-xs text-slate-400 leading-relaxed">Directly sourced original publications from publishers.</p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-center md:items-start gap-4 p-4 rounded-2xl bg-slate-900/60 border border-slate-800 shadow-sm hover:border-sky-500/50 hover:shadow-lg transition group">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 002.25 19.5z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-extrabold text-white tracking-wide mb-1" style="font-family: 'Outfit', sans-serif;">COD & Online Payments</h4>
                            <p class="text-xs text-slate-400 leading-relaxed">Accepting Cash on Delivery, Visa, MasterCard & PayHere.</p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-center md:items-start gap-4 p-4 rounded-2xl bg-slate-900/60 border border-slate-800 shadow-sm hover:border-sky-500/50 hover:shadow-lg transition group">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-extrabold text-white tracking-wide mb-1" style="font-family: 'Outfit', sans-serif;">Same Day Dispatch</h4>
                            <p class="text-xs text-slate-400 leading-relaxed">Swift courier packing and prompt parcel handover.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- 2. Main Footer Navigation Grid (Full Width) -->
        <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 pt-14 pb-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 lg:gap-10">
                
                <!-- Column 1: Brand Info & Contact -->
                <div class="lg:col-span-4 space-y-4">
                    <!-- Brand Logo Section -->
                    <a href="/" class="inline-flex items-center space-x-3 group">
                        <?php if (!empty($footer_logo_path) && (file_exists($_SERVER['DOCUMENT_ROOT'] . $footer_logo_path) || file_exists(__DIR__ . '/..' . $footer_logo_path))): ?>
                            <img src="<?= htmlspecialchars($footer_logo_path); ?>" alt="<?= htmlspecialchars($footer_biz_name); ?>" class="h-10 sm:h-12 w-auto max-w-[200px] object-contain">
                        <?php elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/images/logo.png')): ?>
                            <img src="/assets/images/logo.png" alt="ReadersHub Logo" class="h-10 sm:h-12 w-auto max-w-[200px] object-contain">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-sky-500 text-white flex items-center justify-center font-black shadow-md shadow-sky-500/30 group-hover:scale-105 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <span class="text-2xl font-extrabold text-white tracking-tight" style="font-family: 'Outfit', sans-serif;">readers<span class="text-sky-400 font-black">hub</span><span class="w-2 h-2 rounded-full bg-sky-400 inline-block ml-0.5"></span></span>
                        <?php endif; ?>
                    </a>

                    <p class="text-xs text-slate-400 leading-relaxed max-w-sm">
                        <?= htmlspecialchars($footer_biz_name); ?> is Sri Lanka's trusted online bookstore. Supplying original books and publishing imprints islandwide with fast delivery.
                    </p>

                    <div class="space-y-2 pt-1 text-xs">
                        <div class="flex items-center gap-2.5 text-slate-300">
                            <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h32a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm0 6a2 2 0 012-2h14a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2zm0 6a2 2 0 012-2h14a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2z"/></svg>
                            <a href="tel:<?= htmlspecialchars($footer_phone); ?>" class="text-sky-400 font-bold hover:underline"><?= htmlspecialchars($footer_phone); ?></a>
                        </div>

                        <div class="flex items-center gap-2.5 text-slate-300">
                            <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <a href="mailto:<?= htmlspecialchars($footer_email); ?>" class="text-slate-300 hover:text-sky-400 transition"><?= htmlspecialchars($footer_email); ?></a>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-3">
                        <a href="#" class="w-9 h-9 rounded-xl bg-slate-800 text-slate-400 hover:bg-sky-500 hover:text-white flex items-center justify-center transition shadow-sm" aria-label="Facebook">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="#" class="w-9 h-9 rounded-xl bg-slate-800 text-slate-400 hover:bg-pink-600 hover:text-white flex items-center justify-center transition shadow-sm" aria-label="Instagram">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                        <a href="#" class="w-9 h-9 rounded-xl bg-slate-800 text-slate-400 hover:bg-rose-600 hover:text-white flex items-center justify-center transition shadow-sm" aria-label="YouTube">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                        <a href="https://wa.me/<?= htmlspecialchars($footer_clean_wa); ?>" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded-xl bg-slate-800 text-slate-400 hover:bg-emerald-600 hover:text-white flex items-center justify-center transition shadow-sm" aria-label="WhatsApp">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Column 2: About Store -->
                <div class="lg:col-span-2 space-y-3">
                    <h4 class="text-xs font-extrabold text-white tracking-wider uppercase" style="font-family: 'Outfit', sans-serif;">About Store</h4>
                    <ul class="space-y-2 text-xs text-slate-400">
                        <li><a href="/about-us" class="hover:text-sky-400 transition">• About Us</a></li>
                        <li><a href="/contact" class="hover:text-sky-400 transition">• Contact Us</a></li>
                        <li><a href="/about-us" class="hover:text-sky-400 transition">• Privacy Policy</a></li>
                        <li><a href="/about-us" class="hover:text-sky-400 transition">• Terms & Conditions</a></li>
                        <li><a href="/about-us" class="hover:text-sky-400 transition">• Returns & Refunds</a></li>
                        <li><a href="/shop" class="hover:text-sky-400 transition">• Store Locator</a></li>
                    </ul>
                </div>

                <!-- Column 3: Shopping & Categories -->
                <div class="lg:col-span-2 space-y-3">
                    <h4 class="text-xs font-extrabold text-white tracking-wider uppercase" style="font-family: 'Outfit', sans-serif;">Shopping</h4>
                    <ul class="space-y-2 text-xs text-slate-400">
                        <li><a href="/shop" class="hover:text-sky-400 transition">• All Books Shop</a></li>
                        <li><a href="/publishers" class="hover:text-sky-400 transition">• Publishers</a></li>
                        <li><a href="/category/novels" class="hover:text-sky-400 transition">• Novels & Fiction</a></li>
                        <li><a href="/category/translations" class="hover:text-sky-400 transition">• Translations</a></li>
                        <li><a href="/category/academic" class="hover:text-sky-400 transition">• Academic Guides</a></li>
                        <li><a href="/shop?stock=sale" class="hover:text-rose-400 transition">• Discounts & Sales</a></li>
                    </ul>
                </div>

                <!-- Column 4: Join Our Newsletter -->
                <div class="lg:col-span-4 space-y-3">
                    <h4 class="text-xs font-extrabold text-white tracking-wider uppercase" style="font-family: 'Outfit', sans-serif;">Join Our Newsletter</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Get exclusive offers, author updates, and weekly discounts across Sri Lanka. You may unsubscribe anytime.
                    </p>

                    <form onsubmit="event.preventDefault(); alert('Thank you for subscribing to ReadersHub newsletter!');" class="space-y-2 pt-1">
                        <div class="flex items-center gap-2">
                            <input 
                                type="email" 
                                placeholder="Enter your email address..." 
                                required 
                                class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition"
                            >
                            <button 
                                type="submit" 
                                class="bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md shadow-sky-500/20 transition shrink-0"
                                style="font-family: 'Outfit', sans-serif;"
                            >
                                Subscribe
                            </button>
                        </div>
                    </form>

                    <div class="pt-4">
                        <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2" style="font-family: 'Outfit', sans-serif;">We Accept Payment Via</span>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="bg-slate-900 px-2.5 py-1 rounded-lg text-[10px] text-slate-300 font-bold border border-slate-800">Cash On Delivery</span>
                            <span class="bg-slate-900 px-2.5 py-1 rounded-lg text-[10px] text-sky-400 font-bold border border-slate-800">VISA</span>
                            <span class="bg-slate-900 px-2.5 py-1 rounded-lg text-[10px] text-amber-400 font-bold border border-slate-800">MasterCard</span>
                            <span class="bg-slate-900 px-2.5 py-1 rounded-lg text-[10px] text-indigo-400 font-bold border border-slate-800">PayHere</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 3. Bottom Contrast Copyright Bar with Loopz Global Credit (Full Width) -->
        <div class="bg-[#090d16] text-slate-400 py-5 border-t border-slate-800 w-full">
            <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
                <p>Copyright <?= date('Y') ?> &copy; <strong class="text-white font-bold"><?= htmlspecialchars($footer_biz_name); ?></strong>. All rights reserved.</p>
                <p class="text-slate-400">
                    Developed by 
                    <a href="https://loopzglobal.com" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:text-sky-300 font-bold transition inline-flex items-center gap-1">
                        Loopz Global
                    </a>
                </p>
            </div>
        </div>

    </footer>

    <!-- ==================== 4. FLOATING WHATSAPP BUTTON (DESKTOP & MOBILE) ==================== -->
    <a 
        href="https://wa.me/<?= htmlspecialchars($footer_clean_wa); ?>?text=Hello%20ReadersHub,%20I%20would%20like%20to%20inquire%20about%20a%20book." 
        target="_blank" 
        rel="noopener noreferrer"
        aria-label="Chat on WhatsApp"
        class="fixed bottom-20 md:bottom-7 right-5 z-40 w-14 h-14 bg-emerald-500 hover:bg-emerald-600 text-white rounded-full flex items-center justify-center shadow-2xl hover:scale-110 active:scale-95 transition-all duration-300 group"
    >
        <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
        <svg class="w-7 h-7 fill-current relative z-10" viewBox="0 0 24 24">
            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
        </svg>
        <span class="hidden md:group-hover:inline-block absolute right-16 bg-slate-900 text-white text-xs font-semibold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-lg transition-opacity duration-200 pointer-events-none">
            Chat with us
        </span>
    </a>

    <!-- ==================== 5. FIXED MOBILE BOTTOM NAVIGATION MENU ==================== -->
    <?php
    $current_path = $_SERVER['REQUEST_URI'] ?? '/';
    $mob_cart_count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $ci) {
            $mob_cart_count += (int)($ci['quantity'] ?? 1);
        }
    }
    ?>
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-200 shadow-2xl z-50 py-2 px-3 flex items-center justify-around" style="font-family: 'Poppins', sans-serif;">
        <a href="/" class="flex flex-col items-center gap-1 text-[10px] font-bold transition <?= ($current_path === '/' || $current_path === '/home') ? 'text-sky-700' : 'text-slate-500 hover:text-sky-700' ?>">
            <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
            <span>Home</span>
        </a>

        <a href="/shop" class="flex flex-col items-center gap-1 text-[10px] font-bold transition <?= (strpos($current_path, '/shop') !== false) ? 'text-sky-700' : 'text-slate-500 hover:text-sky-700' ?>">
            <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            <span>Shop</span>
        </a>

        <a href="/cart" class="flex flex-col items-center gap-1 text-[10px] font-bold transition relative <?= (strpos($current_path, '/cart') !== false) ? 'text-sky-700' : 'text-slate-500 hover:text-sky-700' ?>">
            <div class="relative">
                <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                <span id="cart-badge-mobile" class="<?= $mob_cart_count > 0 ? 'flex' : 'hidden'; ?> absolute -top-1.5 -right-2 bg-rose-600 text-white text-[9px] font-black w-4 h-4 rounded-full items-center justify-center shadow-xs">
                    <?= $mob_cart_count; ?>
                </span>
            </div>
            <span>Cart</span>
        </a>

        <a href="<?= isset($_SESSION['user_id']) ? '/account' : '/login'; ?>" class="flex flex-col items-center gap-1 text-[10px] font-bold transition <?= (strpos($current_path, '/account') !== false || strpos($current_path, '/login') !== false) ? 'text-sky-700' : 'text-slate-500 hover:text-sky-700' ?>">
            <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
            <span>Account</span>
        </a>
    </nav>

    <!-- Global Cart Event Listener for Mobile Badge -->
    <script>
        document.addEventListener('cartUpdated', function(e) {
            const mobBadge = document.getElementById('cart-badge-mobile');
            if (mobBadge && e.detail && e.detail.quantity) {
                let current = parseInt(mobBadge.innerText) || 0;
                mobBadge.innerText = current + parseInt(e.detail.quantity);
                mobBadge.classList.remove('hidden');
                mobBadge.classList.add('flex');
            }
        });
    </script>
    
    <!-- Floating Dark/Light Toggle Button -->
    <button 
        type="button" 
        id="theme-toggle-btn"
        onclick="toggleThemeMode()"
        aria-label="Toggle Dark Mode"
        class="fixed bottom-20 md:bottom-7 left-5 z-40 w-12 h-12 rounded-full flex items-center justify-center shadow-2xl hover:scale-110 active:scale-95 transition-all duration-300 border"
        style="background-color: #ffffff; border-color: #cbd5e1;"
    >
        <!-- Sun Icon (Dark Mode එකේදී පෙන්වයි) -->
        <svg id="theme-sun-icon" class="w-5 h-5 text-amber-400 fill-current hidden" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
        </svg>

        <!-- Moon Icon (White/Light Mode එකේදී පැහැදිලිව පෙන්වයි) -->
        <svg id="theme-moon-icon" class="w-5 h-5 text-slate-800 fill-current block" viewBox="0 0 20 20">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
        </svg>
    </button>

    <script>
        function updateThemeIcons() {
            const isDark = document.documentElement.classList.contains('dark');
            const sun = document.getElementById('theme-sun-icon');
            const moon = document.getElementById('theme-moon-icon');
            const btn = document.getElementById('theme-toggle-btn');
            
            if (isDark) {
                sun?.classList.remove('hidden');
                sun?.classList.add('block');
                moon?.classList.remove('block');
                moon?.classList.add('hidden');
                if (btn) {
                    btn.style.backgroundColor = '#1e293b';
                    btn.style.borderColor = '#334155';
                }
            } else {
                sun?.classList.remove('block');
                sun?.classList.add('hidden');
                moon?.classList.remove('hidden');
                moon?.classList.add('block');
                if (btn) {
                    btn.style.backgroundColor = '#ffffff';
                    btn.style.borderColor = '#cbd5e1';
                }
            }
        }

        function toggleThemeMode() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
            updateThemeIcons();
        }

        // Run immediately without waiting to prevent missing icons
        updateThemeIcons();
        document.addEventListener('DOMContentLoaded', updateThemeIcons);
    </script>

</body>
</html>