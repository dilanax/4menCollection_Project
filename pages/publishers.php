<?php
// Include DB fallback connection
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    } elseif (file_exists('config/db.php')) {
        require_once 'config/db.php';
    }
}

// Fetch all publishers (brands) with book counts
$publishers = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql = "SELECT b.id, b.name, b.slug, b.image, 
                       COUNT(p.id) as book_count 
                FROM brands b 
                LEFT JOIN products p ON b.id = p.brand_id 
                     AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                GROUP BY b.id 
                ORDER BY b.name ASC";
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $publishers = $stmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    $publishers = [];
}
?>

<div class="w-full bg-[#f8fafc] text-slate-800 py-6 sm:py-10 min-h-screen" style="font-family: 'Poppins', sans-serif;">
    <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12">
        
        <!-- Breadcrumbs & Heading -->
        <div class="mb-6">
            <nav class="flex items-center text-xs font-semibold text-slate-400 mb-2 gap-2">
                <a href="/" class="hover:text-sky-700 transition">Home</a>
                <span>/</span>
                <span class="text-sky-700 font-bold">Publishers</span>
            </nav>
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">Book Publishers & Imprints</h1>
                    <p class="text-slate-500 text-xs sm:text-sm mt-1">Browse and filter Sri Lanka's leading publication houses and authorized distributors</p>
                </div>
                <div class="text-xs font-bold text-slate-500 bg-white px-3.5 py-1.5 rounded-xl border border-slate-200 self-start md:self-auto shadow-xs">
                    Total: <span class="text-sky-700 font-black"><?= count($publishers); ?></span> Publishers
                </div>
            </div>
        </div>

        <!-- ==================== TOP FILTER BAR: SEARCH & A-Z FILTER ==================== -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-xs mb-8 space-y-4">
            
            <!-- 1. Search Box -->
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <div class="relative w-full sm:max-w-md">
                    <input 
                        type="text" 
                        id="publisher-search" 
                        placeholder="Search publisher by name..." 
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-sky-700 outline-none transition shadow-xs"
                    >
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 pointer-events-none stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                
                <span class="text-xs text-slate-400 hidden sm:inline">Filter instantly by name or initial letter below:</span>
            </div>

            <!-- 2. A-Z Alphabetical Buttons Strip -->
            <div class="flex items-center gap-1 sm:gap-1.5 overflow-x-auto pb-1 custom-scrollbar">
                <button 
                    type="button" 
                    onclick="filterByLetter('ALL', this)" 
                    class="letter-btn active bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shrink-0 transition shadow-xs"
                >
                    ALL
                </button>
                
                <?php foreach (range('A', 'Z') as $char): ?>
                    <button 
                        type="button" 
                        onclick="filterByLetter('<?= $char ?>', this)" 
                        class="letter-btn bg-slate-50 hover:bg-sky-50 text-slate-700 hover:text-sky-700 border border-slate-200/80 px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold shrink-0 transition"
                    >
                        <?= $char ?>
                    </button>
                <?php endforeach; ?>

                <button 
                    type="button" 
                    onclick="filterByLetter('#', this)" 
                    class="letter-btn bg-slate-50 hover:bg-sky-50 text-slate-700 hover:text-sky-700 border border-slate-200/80 px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold shrink-0 transition"
                >
                    0-9
                </button>
            </div>

        </div>

        <!-- ==================== PUBLISHERS GRID ==================== -->
        <?php if (!empty($publishers)): ?>
            <div id="publishers-grid" class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3.5 sm:gap-4">
                <?php foreach ($publishers as $pub): 
                    $pub_id = (int)$pub['id'];
                    $pub_name = htmlspecialchars($pub['name'] ?? 'Publisher');
                    $first_char = strtoupper(mb_substr(trim($pub['name']), 0, 1));
                    if (!preg_match('/[A-Z]/', $first_char)) {
                        $first_char = '#';
                    }
                    $book_count = (int)($pub['book_count'] ?? 0);
                    
                    $pub_img = null;
                    if (!empty($pub['image'])) {
                        $pub_img = (str_starts_with($pub['image'], 'http://') || str_starts_with($pub['image'], 'https://'))
                            ? $pub['image']
                            : '/' . ltrim(htmlspecialchars($pub['image']), '/');
                    }
                ?>
                    <a 
                        href="/shop?brands[]=<?= $pub_id ?>" 
                        data-name="<?= strtolower($pub_name); ?>" 
                        data-letter="<?= $first_char; ?>"
                        class="publisher-card group bg-white rounded-2xl border border-slate-200/90 hover:border-sky-700 hover:shadow-xl hover:shadow-sky-700/10 transition-all duration-300 flex flex-col justify-center items-center text-center p-4 sm:p-5"
                    >
                        <?php /* ==================== IMAGE CONTAINER (COMMENTED OUT) ====================
                        <div class="relative w-full aspect-square bg-slate-100 overflow-hidden border-b border-slate-100 rounded-t-2xl mb-3">
                            if ($pub_img) {
                                echo '<img src="' . $pub_img . '" alt="' . $pub_name . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out" loading="lazy">';
                            } else {
                                echo '<div class="w-full h-full flex items-center justify-center bg-slate-50 text-sky-700/80">
                                    <svg class="w-10 h-10 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>';
                            }
                        </div>
                        ============================================================================= */ ?>

                        <!-- Details Container -->
                        <div class="flex flex-col items-center justify-center w-full">
                            <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 group-hover:text-sky-700 transition-colors truncate max-w-full leading-snug">
                                <?= $pub_name; ?>
                            </h3>

                            <div class="mt-2">
                                <span class="inline-flex items-center text-[10px] font-bold bg-sky-50 text-sky-800 px-2.5 py-0.5 rounded-md border border-sky-100/80">
                                    <?= number_format($book_count); ?> <?= $book_count === 1 ? 'Title' : 'Titles'; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Empty Search Results Notification -->
            <div id="no-results" class="hidden text-center py-16 bg-white rounded-3xl border border-slate-200 text-slate-400 text-xs">
                No publishers matched your filter criteria.
            </div>

        <?php else: ?>
            <div class="text-center py-16 bg-white rounded-3xl border border-slate-200 text-slate-400 text-xs">
                No publishers found in the catalog.
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<!-- Client-side Fast Filtering Script -->
<script>
    let activeLetter = 'ALL';

    function applyFilters() {
        const query = (document.getElementById('publisher-search')?.value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('.publisher-card');
        const noResults = document.getElementById('no-results');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = card.getAttribute('data-name') || '';
            const letter = card.getAttribute('data-letter') || '';

            const matchesQuery = (query === '' || name.includes(query));
            const matchesLetter = (activeLetter === 'ALL' || letter === activeLetter);

            if (matchesQuery && matchesLetter) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        if (noResults) {
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }
    }

    function filterByLetter(letter, btn) {
        activeLetter = letter;
        document.querySelectorAll('.letter-btn').forEach(b => {
            b.classList.remove('bg-sky-700', 'text-white', 'active');
            b.classList.add('bg-slate-50', 'text-slate-700');
        });

        btn.classList.add('bg-sky-700', 'text-white', 'active');
        btn.classList.remove('bg-slate-50', 'text-slate-700');

        applyFilters();
    }

    document.getElementById('publisher-search')?.addEventListener('input', applyFilters);
</script>