<?php
$footer_settings = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $ft_stmt = $pdo->query("SELECT business_name, phone_number, whatsapp_number, smtp_email FROM email_settings WHERE id = 1 LIMIT 1");
        if ($ft_stmt) {
            $footer_settings = $ft_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $footer_settings = [];
    }
}

$footer_biz_name  = !empty($footer_settings['business_name']) ? $footer_settings['business_name'] : '4MEN COLLECTION';
$footer_phone     = !empty($footer_settings['phone_number']) ? $footer_settings['phone_number'] : '+94 77 123 4567';
$footer_wa        = !empty($footer_settings['whatsapp_number']) ? $footer_settings['whatsapp_number'] : '0771234567';
$footer_email     = !empty($footer_settings['smtp_email']) ? $footer_settings['smtp_email'] : 'support@4mencollection.lk';
$footer_clean_wa = preg_replace('/[^0-9]/', '', $footer_wa);
if (str_starts_with($footer_clean_wa, '0')) {
    $footer_clean_wa = '94' . substr($footer_clean_wa, 1);
}
?>

</main>

<footer class="relative mt-20 w-full overflow-hidden bg-[#101113] pb-20 font-sans text-white md:pb-0">
    <div class="mx-auto max-w-7xl px-4 pt-12 sm:px-8 lg:px-10 lg:pt-16">
        <section class="relative overflow-hidden rounded-3xl bg-[#e7e9ed] px-6 py-8 text-[#17191d] shadow-xl sm:px-10 sm:py-10 lg:flex lg:items-center lg:justify-between lg:gap-10">
            <div class="pointer-events-none absolute -right-8 -top-16 h-48 w-48 rounded-full border-[28px] border-black/[0.04] sm:h-64 sm:w-64 sm:border-[38px]"></div>
            <div class="relative max-w-xl">
                <span class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.18em]">
                    <span class="h-2 w-2 rounded-full bg-neutral-900"></span>
                    The 4MEN edit
                </span>
                <h2 class="mt-3 text-3xl font-black leading-tight tracking-tight sm:text-4xl">A better fit for your inbox.</h2>
                <p class="mt-2 max-w-md text-sm leading-6 text-neutral-700">Be first to hear about new drops, fresh fits and members-only offers.</p>
            </div>
            <form onsubmit="event.preventDefault(); alert('Thank you for subscribing to 4MEN Collection!');" class="relative mt-6 flex w-full max-w-lg rounded-xl bg-white p-1.5 shadow-sm focus-within:ring-2 focus-within:ring-black/20 lg:mt-0">
                <label for="footer-newsletter-email" class="sr-only">Email address</label>
                <input id="footer-newsletter-email" type="email" placeholder="Enter your email address" required class="min-w-0 flex-1 rounded-lg bg-transparent px-3 py-3 text-sm text-neutral-900 outline-none placeholder:text-neutral-400">
                <button type="submit" class="shrink-0 rounded-lg bg-black px-4 py-3 text-xs font-bold text-white transition hover:bg-neutral-700 sm:px-5">Join the list <span aria-hidden="true">→</span></button>
            </form>
        </section>

        <div class="grid grid-cols-1 gap-10 py-12 sm:grid-cols-2 lg:grid-cols-12 lg:gap-8 lg:py-16">
            <section class="sm:col-span-2 lg:col-span-4">
                <a href="/" class="inline-flex items-center gap-3" aria-label="<?= htmlspecialchars($footer_biz_name, ENT_QUOTES, 'UTF-8'); ?> home">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-xs font-black tracking-tight text-black">4M</span>
                    <span class="text-sm font-extrabold uppercase tracking-[0.18em] text-white"><?= htmlspecialchars($footer_biz_name, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <p class="mt-5 max-w-sm text-sm leading-6 text-neutral-400">Everyday menswear, considered details and versatile essentials made to move with you.</p>
                <div class="mt-5 space-y-2 text-sm">
                    <a href="tel:<?= htmlspecialchars($footer_phone, ENT_QUOTES, 'UTF-8'); ?>" class="block w-fit text-neutral-300 transition hover:text-white"><?= htmlspecialchars($footer_phone, ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="mailto:<?= htmlspecialchars($footer_email, ENT_QUOTES, 'UTF-8'); ?>" class="block w-fit text-neutral-400 transition hover:text-white"><?= htmlspecialchars($footer_email, ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </section>

            <nav class="lg:col-span-2 lg:col-start-6" aria-label="Shop">
                <h2 class="text-xs font-bold text-white">Shop</h2>
                <ul class="mt-5 space-y-3 text-sm text-neutral-400">
                    <li><a href="/shop" class="transition hover:text-white">All clothing</a></li>
                    <li><a href="/shop?filter=new" class="transition hover:text-white">New arrivals</a></li>
                    <li><a href="/shop?search=Shirt" class="transition hover:text-white">Shirts</a></li>
                    <li><a href="/shop?search=Chino" class="transition hover:text-white">Trousers</a></li>
                    <li><a href="/shop?search=Blazer" class="transition hover:text-white">Blazers</a></li>
                </ul>
            </nav>

            <nav class="lg:col-span-2" aria-label="Help and information">
                <h2 class="text-xs font-bold text-white">Help & information</h2>
                <ul class="mt-5 space-y-3 text-sm text-neutral-400">
                    <li><a href="/account" class="transition hover:text-white">My account</a></li>
                    <li><a href="/contact" class="transition hover:text-white">Contact us</a></li>
                    <li><a href="https://wa.me/<?= htmlspecialchars($footer_clean_wa, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="transition hover:text-white">WhatsApp support</a></li>
                </ul>
            </nav>

            <section class="lg:col-span-2">
                <h2 class="text-xs font-bold text-white">Follow along</h2>
                <p class="mt-5 text-sm leading-6 text-neutral-400">Style notes, new arrivals and everyday inspiration.</p>
                <div class="mt-4 flex gap-2.5">
                    <a href="https://www.instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 text-neutral-300 transition hover:border-white/30 hover:bg-white/5 hover:text-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".75" fill="currentColor"/></svg>
                    </a>
                    <a href="https://www.facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 text-xs font-bold text-neutral-300 transition hover:border-white/30 hover:bg-white/5 hover:text-white">f</a>
                    <a href="https://www.tiktok.com" target="_blank" rel="noopener noreferrer" aria-label="TikTok" class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 text-sm font-bold text-neutral-300 transition hover:border-white/30 hover:bg-white/5 hover:text-white">♪</a>
                </div>
            </section>
        </div>

        <div class="flex flex-col items-center justify-between gap-3 border-t border-white/10 py-5 text-center text-[11px] text-neutral-500 sm:flex-row sm:text-left">
            <p>&copy; <?= date('Y'); ?> <?= htmlspecialchars($footer_biz_name, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
            <p>Cash on delivery <span class="mx-1 text-neutral-700">·</span> Visa <span class="mx-1 text-neutral-700">·</span> Mastercard <span class="mx-1 text-neutral-700">·</span> PayHere</p>
        </div>
    </div>
</footer>

<a
    href="https://wa.me/<?= htmlspecialchars($footer_clean_wa, ENT_QUOTES, 'UTF-8'); ?>?text=Hello%204MEN%20Collection,%20I%20would%20like%20to%20inquire%20about%20your%20apparel."
    target="_blank"
    rel="noopener noreferrer"
    aria-label="Chat on WhatsApp"
    class="group fixed bottom-20 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-2xl transition hover:scale-110 hover:bg-emerald-600 active:scale-95 md:bottom-7"
>
    <svg class="relative z-10 h-7 w-7 fill-current" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
    </svg>
    <span class="pointer-events-none absolute right-16 hidden whitespace-nowrap rounded bg-black px-3 py-2 text-xs font-bold text-white shadow-xl group-hover:block">Chat with us</span>
</a>

<?php
$current_path = $_SERVER['REQUEST_URI'] ?? '/';
$mob_cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $ci) {
        $mob_cart_count += (int)($ci['quantity'] ?? 1);
    }
}
?>
<nav class="fixed inset-x-0 bottom-0 z-50 flex items-center justify-around border-t border-white/10 bg-black/95 px-3 py-2.5 text-neutral-400 shadow-2xl backdrop-blur-2xl md:hidden" aria-label="Mobile navigation">
    <a href="/" class="flex flex-col items-center gap-1 text-[10px] font-bold uppercase tracking-wider transition <?= ($current_path === '/' || $current_path === '/home') ? 'text-white' : 'hover:text-white' ?>">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
        <span>Home</span>
    </a>
    <a href="/shop" class="flex flex-col items-center gap-1 text-[10px] font-bold uppercase tracking-wider transition <?= (strpos($current_path, '/shop') !== false) ? 'text-white' : 'hover:text-white' ?>">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 018.25 20.25H6A2.25 2.25 0 013.75 18zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 18z"/></svg>
        <span>Shop</span>
    </a>
    <a href="/cart" class="relative flex flex-col items-center gap-1 text-[10px] font-bold uppercase tracking-wider transition <?= (strpos($current_path, '/cart') !== false) ? 'text-white' : 'hover:text-white' ?>">
        <span class="relative">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>
            <span id="cart-badge-mobile" class="<?= $mob_cart_count > 0 ? 'flex' : 'hidden'; ?> absolute -right-2 -top-1.5 h-4 min-w-4 items-center justify-center rounded-full bg-white px-1 text-[9px] font-black text-black"><?= $mob_cart_count; ?></span>
        </span>
        <span>Bag</span>
    </a>
    <a href="<?= isset($_SESSION['user_id']) ? '/account' : '/login'; ?>" class="flex flex-col items-center gap-1 text-[10px] font-bold uppercase tracking-wider transition <?= (strpos($current_path, '/account') !== false || strpos($current_path, '/login') !== false) ? 'text-white' : 'hover:text-white' ?>">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
        <span>Account</span>
    </a>
</nav>

<script>
    document.addEventListener('cartUpdated', function (e) {
        const mobBadge = document.getElementById('cart-badge-mobile');
        const quantity = parseInt(e.detail?.quantity, 10);
        if (mobBadge && Number.isFinite(quantity) && quantity > 0) {
            const newCount = (parseInt(mobBadge.innerText, 10) || 0) + quantity;
            mobBadge.innerText = newCount;
            mobBadge.classList.remove('hidden');
            mobBadge.classList.add('flex');
        }
    });
</script>

<button
    type="button"
    id="theme-toggle-btn"
    onclick="toggleThemeMode()"
    aria-label="Toggle dark mode"
    class="fixed bottom-20 left-5 z-40 flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-black text-white shadow-2xl transition hover:scale-110 active:scale-95 md:bottom-7"
>
    <svg id="theme-sun-icon" class="hidden h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1z" clip-rule="evenodd"/></svg>
    <svg id="theme-moon-icon" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
</button>

<script>
    function updateThemeIcons() {
        const isDark = document.documentElement.classList.contains('dark');
        const sun = document.getElementById('theme-sun-icon');
        const moon = document.getElementById('theme-moon-icon');
        const button = document.getElementById('theme-toggle-btn');

        sun?.classList.toggle('hidden', !isDark);
        moon?.classList.toggle('hidden', isDark);
        button?.classList.toggle('bg-white', !isDark);
        button?.classList.toggle('text-black', !isDark);
        button?.classList.toggle('bg-black', isDark);
        button?.classList.toggle('text-white', isDark);
    }

    function toggleThemeMode() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeIcons();
    }

    updateThemeIcons();
</script>

</body>
</html>
