<?php
// Include DB fallback
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    } elseif (file_exists('config/db.php')) {
        require_once 'config/db.php';
    }
}

// Fetch all authors from attribute_terms where attribute is 'author'
$authors = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sql = "SELECT t.id, t.name, t.slug,
                (
                    SELECT COUNT(DISTINCT p.id) 
                    FROM product_attributes pa 
                    JOIN products p ON pa.product_id = p.id
                    WHERE pa.attribute_id = t.attribute_id 
                    AND (pa.terms_json LIKE CONCAT('%\"', t.name, '\"%') OR pa.terms_json LIKE CONCAT('%', t.name, '%'))
                    AND (p.status IS NULL OR p.status = '' OR p.status IN ('published', 'publish', 'active', '1', 'enabled') OR p.status != 'draft')
                ) as book_count
                FROM attribute_terms t
                JOIN attributes a ON t.attribute_id = a.id
                WHERE a.slug = 'author' OR LOWER(a.name) = 'author'
                ORDER BY t.name ASC";
        
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $authors = $stmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    $authors = [];
}

$total_authors = count($authors);
?>

<script>
    document.title = "Authors & Writers Directory | ReadersHub";
</script>

<div class="w-full bg-[#f8fafc] text-slate-800 py-6 sm:py-10 min-h-screen" style="font-family: 'Poppins', sans-serif;">
    <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12">
        
        <!-- Header & Breadcrumbs (Full Width Aligned) -->
        <div class="mb-8 border-b border-slate-200 pb-5">
            <nav class="flex items-center text-xs font-semibold text-slate-400 mb-2 gap-2">
                <a href="/" class="hover:text-sky-700 transition-colors">Home</a>
                <span>/</span>
                <span class="text-sky-700 font-bold">Authors Directory</span>
            </nav>
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Literary Creators</span>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight mt-0.5">Explore Authors & Writers</h1>
                    <p class="text-slate-500 text-xs sm:text-sm mt-1">
                        Discover classic novelists, modern writers, translators, and academic authors in Sri Lanka.
                    </p>
                </div>
                <div class="text-xs font-bold text-slate-500 bg-white border border-slate-200/90 px-4 py-2 rounded-2xl shadow-xs self-start md:self-auto">
                    Total Authors: <span class="text-sky-700 font-black"><?= $total_authors; ?></span>
                </div>
            </div>
        </div>

        <!-- Search & Alphabetical Filter Bar -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-4 sm:p-6 mb-8 space-y-4">
            
            <!-- Quick Search Input -->
            <div class="relative max-w-xl">
                <svg class="w-4 h-4 text-slate-400 absolute left-4 top-3.5 pointer-events-none stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input 
                    type="text" 
                    id="author-search" 
                    placeholder="Search author by name..." 
                    class="w-full bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 rounded-2xl pl-11 pr-4 py-3 outline-none focus:bg-white focus:border-sky-700 transition-colors shadow-xs"
                >
            </div>

            <!-- Alphabetical Quick Filter (A - Z) -->
            <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5 flex-wrap">
                <button type="button" onclick="filterByLetter('ALL', this)" class="alpha-btn active px-3 py-1.5 text-xs font-bold rounded-xl bg-sky-700 text-white transition shadow-xs">
                    ALL
                </button>
                <?php 
                foreach (range('A', 'Z') as $letter) {
                    echo '<button type="button" onclick="filterByLetter(\'' . $letter . '\', this)" class="alpha-btn px-2.5 py-1 text-xs font-bold rounded-xl text-slate-600 hover:bg-slate-100 hover:text-sky-700 transition">';
                    echo $letter;
                    echo '</button>';
                }
                ?>
            </div>

        </div>

        <!-- Authors Grid (Desktop 6 Cols, Laptop 5 Cols, Tablet 3 Cols, Mobile 2 Cols) -->
        <?php if (!empty($authors)): ?>
            <div id="authors-grid" class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-6 gap-3.5 sm:gap-4 lg:gap-5">
                <?php foreach ($authors as $author): 
                    $author_name = htmlspecialchars($author['name'] ?? 'Author');
                    $book_count = (int)($author['book_count'] ?? 0);
                    $first_char = strtoupper(mb_substr(trim($author['name']), 0, 1));
                    $data_letter = preg_match('/[A-Z]/i', $first_char) ? $first_char : '#';
                ?>
                    <a 
                        href="/shop?authors[]=<?= urlencode($author['name']); ?>" 
                        class="author-card group bg-white p-5 rounded-2xl sm:rounded-3xl border border-slate-200/90 hover:border-sky-700 hover:shadow-xl hover:shadow-sky-700/10 transition-all duration-300 flex flex-col items-center text-center justify-between min-h-[190px]"
                        data-name="<?= strtolower($author_name); ?>"
                        data-letter="<?= $data_letter; ?>"
                    >
                        <!-- Top Avatar Stage -->
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-gradient-to-tr from-sky-100 via-sky-50 to-white text-sky-700 flex items-center justify-center border-2 border-sky-200 group-hover:border-sky-700 group-hover:scale-105 transition-all duration-300 shadow-xs relative overflow-hidden shrink-0 mt-1">
                            <span class="text-lg sm:text-2xl font-black uppercase text-sky-900 select-none">
                                <?= $first_char ?: 'A'; ?>
                            </span>

                            <!-- Quill Icon Badge -->
                            <div class="absolute bottom-0 right-0 w-5 h-5 bg-sky-700 text-white rounded-full flex items-center justify-center shadow-xs">
                                <svg class="w-3 h-3 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Author Name -->
                        <div class="my-2.5 w-full">
                            <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 group-hover:text-sky-700 transition-colors line-clamp-2 leading-snug">
                                <?= $author_name; ?>
                            </h3>
                        </div>

                        <!-- Book Count Badge -->
                        <div class="w-full">
                            <span class="inline-flex items-center text-[10px] font-bold bg-sky-50 text-sky-800 px-2.5 py-0.5 rounded-md border border-sky-100/80">
                                <?= number_format($book_count); ?> <?= $book_count === 1 ? 'Book' : 'Books'; ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- No search results fallback -->
            <div id="no-results" class="hidden text-center py-16 bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                <div class="w-14 h-14 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h4 class="text-sm font-extrabold text-slate-900">No Authors Found</h4>
                <p class="text-xs text-slate-400 mt-1">Try searching with a different name or spelling.</p>
            </div>

        <?php else: ?>
            <div class="text-center py-16 bg-white rounded-3xl border border-slate-200/90 text-slate-400 text-xs shadow-xs">
                No authors registered in the database catalog yet.
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
    let activeLetter = 'ALL';

    // Live Search Filter
    const searchInput = document.getElementById('author-search');
    const cards = document.querySelectorAll('.author-card');
    const noResults = document.getElementById('no-results');

    function applyFilters() {
        const query = (searchInput?.value || '').toLowerCase().trim();
        let visibleCount = 0;

        cards.forEach(card => {
            const name = card.getAttribute('data-name') || '';
            const letter = card.getAttribute('data-letter') || '';

            const matchesQuery = query.length === 0 || name.includes(query);
            const matchesLetter = activeLetter === 'ALL' || letter === activeLetter;

            if (matchesQuery && matchesLetter) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        if (noResults) {
            if (visibleCount === 0 && cards.length > 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }
    }

    searchInput?.addEventListener('input', applyFilters);

    // Alphabetical Filter Logic
    function filterByLetter(letter, btn) {
        activeLetter = letter;
        
        document.querySelectorAll('.alpha-btn').forEach(b => {
            b.classList.remove('bg-sky-700', 'text-white', 'shadow-xs');
            b.classList.add('text-slate-600', 'hover:bg-slate-100', 'hover:text-sky-700');
        });

        btn.classList.remove('text-slate-600', 'hover:bg-slate-100', 'hover:text-sky-700');
        btn.classList.add('bg-sky-700', 'text-white', 'shadow-xs');

        applyFilters();
    }
</script>