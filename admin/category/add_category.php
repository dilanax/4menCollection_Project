<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    header("Location: /login");
    exit;
}

require_once '../../config/db.php';

// SweetAlert Session Messages
$message = '';
$messageType = '';
if (isset($_SESSION['swal_msg'])) {
    $message = $_SESSION['swal_msg'];
    $messageType = $_SESSION['swal_type'];
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
}

// ==========================================
// PAGINATION & DATA FETCHING
// ==========================================
$limit = 12;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

// Fetch Main Categories for Dropdown
$main_cats_stmt = $pdo->query("SELECT id, name FROM categories WHERE parent_id = 0 ORDER BY name ASC");
$main_categories = $main_cats_stmt->fetchAll();

// Fetch Hierarchy AND Product Count using 'product_categories' table
$sql = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id) as product_count,
           p_cat.name as parent_name
    FROM categories c
    LEFT JOIN categories p_cat ON c.parent_id = p_cat.id
    ORDER BY COALESCE(NULLIF(c.parent_id, 0), c.id), c.parent_id, c.name
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll();

// Total count
$total_stmt = $pdo->query("SELECT COUNT(*) FROM categories");
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Book Categories | ReadersHub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, input, textarea, select, button { font-family: 'Poppins', sans-serif !important; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-slate-800 antialiased">

    <?php 
    if (file_exists('../../sidebar.php')) { require_once '../../sidebar.php'; } 
    elseif (file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">
        
        <!-- Sticky Top Header -->
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3.5 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-open-header" type="button" class="md:hidden p-2 text-slate-600 hover:text-sky-700 focus:outline-none rounded-xl hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Organization</span>
                    <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 tracking-tight">Book Categories</h1>
                </div>
            </div>

            <button type="button" onclick="openModal('categoryModal', 'add')" class="bg-sky-700 hover:bg-sky-800 text-white px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-md shadow-sky-700/20 flex items-center gap-1.5 active:scale-98">
                <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Category</span>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">

            <!-- Desktop Table View (Displays on screen >= 1024px) -->
            <div class="hidden lg:block bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden mb-6">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-400 text-[10px] uppercase tracking-widest font-extrabold">
                            <th class="px-6 py-4 w-20">Image</th>
                            <th class="px-6 py-4">Category Name & Hierarchy</th>
                            <th class="px-6 py-4">Slug</th>
                            <th class="px-6 py-4 text-center">Books</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-slate-50/60 transition group">
                                <td class="px-6 py-3.5">
                                    <?php if ($cat['image']): ?>
                                        <img src="/<?php echo htmlspecialchars($cat['image']); ?>" class="w-12 h-12 rounded-xl object-cover border border-slate-200 shadow-xs">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center border border-slate-200 text-slate-400">
                                            <svg class="w-5 h-5 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-3.5">
                                    <p class="<?php echo $cat['parent_id'] == 0 ? 'font-extrabold text-slate-900 text-sm' : 'font-semibold text-slate-700 ml-4'; ?>">
                                        <?php echo $cat['parent_id'] != 0 ? '<span class="text-slate-300 font-bold mr-1.5">—</span> ' : ''; ?>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </p>
                                    <?php if ($cat['parent_id'] != 0 && !empty($cat['parent_name'])): ?>
                                        <span class="text-[10px] text-slate-400 block ml-4">Parent: <?php echo htmlspecialchars($cat['parent_name']); ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-3.5 text-slate-500 font-mono text-[11px]">
                                    /category/<?php echo htmlspecialchars($cat['slug']); ?>
                                </td>

                                <td class="px-6 py-3.5 text-center">
                                    <span class="inline-flex items-center justify-center px-2.5 py-0.5 text-[10px] font-black rounded-md border <?php echo $cat['product_count'] > 0 ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-slate-100 text-slate-500 border-slate-200'; ?>">
                                        <?php echo $cat['product_count']; ?> Titles
                                    </span>
                                </td>

                                <td class="px-6 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" onclick='openModal("categoryModal", "edit", <?php echo json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="p-1.5 text-sky-700 hover:text-sky-900 rounded-lg hover:bg-sky-50 transition" title="Edit Category">
                                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button type="button" onclick="confirmDelete(<?php echo $cat['id']; ?>)" class="p-1.5 text-rose-500 hover:text-rose-700 rounded-lg hover:bg-rose-50 transition" title="Delete Category">
                                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs">No categories found in the catalog.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Responsive Card Grid View (Mobile: 1 Column | Tablet sm/md: 2 Columns) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:hidden gap-3 sm:gap-4 mb-6">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col justify-between hover:border-sky-300 transition-all duration-200 group">
                        
                        <div class="flex items-start gap-3.5">
                            <!-- Category Thumbnail Stage -->
                            <div class="relative shrink-0">
                                <?php if ($cat['image']): ?>
                                    <img src="/<?php echo htmlspecialchars($cat['image']); ?>" class="w-14 h-14 rounded-2xl object-cover border border-slate-200 shadow-xs">
                                <?php else: ?>
                                    <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center border border-slate-200 text-slate-400">
                                        <svg class="w-6 h-6 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Name, Parent & Slug Information -->
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-extrabold text-slate-900 leading-snug truncate" title="<?php echo htmlspecialchars($cat['name']); ?>">
                                    <?php echo $cat['parent_id'] != 0 ? '<span class="text-slate-400 font-bold mr-1">—</span>' : ''; ?>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </h3>
                                
                                <?php if ($cat['parent_id'] != 0 && !empty($cat['parent_name'])): ?>
                                    <p class="text-[10px] text-slate-400 font-medium truncate mt-0.5">
                                        Parent: <span class="text-slate-600 font-semibold"><?php echo htmlspecialchars($cat['parent_name']); ?></span>
                                    </p>
                                <?php endif; ?>

                                <p class="text-[10px] text-slate-400 font-mono truncate mt-0.5">
                                    /category/<?php echo htmlspecialchars($cat['slug']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Card Footer: Counts & Action Buttons -->
                        <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 text-[10px] font-black rounded-md border <?php echo $cat['product_count'] > 0 ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-slate-100 text-slate-500 border-slate-200'; ?>">
                                <?php echo $cat['product_count']; ?> Titles
                            </span>

                            <div class="flex items-center gap-1">
                                <button type="button" onclick='openModal("categoryModal", "edit", <?php echo json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="px-2.5 py-1.5 text-xs font-bold text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-xl transition flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <span>Edit</span>
                                </button>
                                <button type="button" onclick="confirmDelete(<?php echo $cat['id']; ?>)" class="p-1.5 text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition" title="Delete Category">
                                    <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>

                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-8 text-center text-slate-400 bg-white rounded-3xl border border-slate-200 text-xs">
                        No categories found.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-between bg-white px-4 py-3 sm:px-6 rounded-2xl border border-slate-200/90 shadow-xs mb-8 flex-wrap gap-3">
                    <div class="text-xs text-slate-500">
                        Page <strong class="text-slate-900"><?php echo $page; ?></strong> of <strong class="text-slate-900"><?php echo $total_pages; ?></strong>
                    </div>

                    <div class="inline-flex items-center gap-1.5">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?p=<?php echo $i; ?>" class="px-3 py-1.5 border rounded-xl text-xs font-bold transition <?php echo $i == $page ? 'bg-sky-700 text-white border-sky-700 shadow-xs' : 'border-slate-200 text-slate-700 hover:bg-slate-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Hidden Form for Safe Deletion -->
    <form id="deleteForm" action="add_category_action.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_category">
        <input type="hidden" name="category_id" id="delete_id">
    </form>

    <!-- Category Modal (Add / Edit) with Image Remove Feature -->
    <div id="categoryModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-100 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            
            <div class="flex justify-between items-center p-5 border-b border-slate-100 bg-slate-50/70">
                <h3 id="modalTitle" class="font-extrabold text-slate-900 text-base">Add New Category</h3>
                <button type="button" onclick="closeModal('categoryModal')" class="text-slate-400 hover:text-rose-600 p-1.5 rounded-full hover:bg-rose-50 transition">
                    <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form action="add_category_action.php" method="POST" enctype="multipart/form-data" class="p-5 sm:p-6 space-y-4">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="category_id" id="cat_id">
                <input type="hidden" name="existing_image" id="existing_image">
                <input type="hidden" name="remove_image" id="remove_image" value="0">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Category Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="cat_name" required placeholder="e.g. Science Fiction" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl px-4 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">URL Slug</label>
                    <input type="text" name="slug" id="cat_slug" placeholder="auto-generated-from-name" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-2.5 focus:bg-white focus:border-sky-700 outline-none transition-colors font-mono text-xs">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Parent Category</label>
                    <select name="parent_id" id="parent_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none transition-colors cursor-pointer">
                        <option value="0">None (Main Top-Level Category)</option>
                        <?php foreach ($main_categories as $m_cat): ?>
                            <option value="<?php echo $m_cat['id']; ?>"><?php echo htmlspecialchars($m_cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Category Image Upload & Removable Preview -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Category Image</label>
                    
                    <div id="cat_image_dropzone" class="relative border-2 border-dashed border-slate-200 hover:border-sky-700 rounded-2xl p-4 text-center bg-slate-50/50 hover:bg-sky-50/30 transition-all cursor-pointer">
                        <input type="file" id="cat_image_input" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <div class="flex flex-col items-center justify-center py-1">
                            <svg class="w-7 h-7 text-sky-700 mb-1.5 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span class="text-xs font-bold text-slate-700">Choose Category Image</span>
                            <span class="text-[10px] text-slate-400 mt-0.5">JPEG, PNG, or WEBP (1:1 square recommended)</span>
                        </div>
                    </div>

                    <div id="img_preview_container" class="hidden mt-2.5 relative text-center bg-slate-50 p-3 rounded-2xl border border-slate-200">
                        <div class="relative inline-block">
                            <img id="img_preview" src="" class="w-20 h-20 object-cover rounded-xl border border-slate-300 shadow-xs">
                            <button type="button" id="remove_image_btn" class="absolute -top-2 -right-2 bg-rose-600 hover:bg-rose-700 text-white rounded-full p-1 shadow-md transition-transform active:scale-95" title="Remove image">
                                <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <span id="img_name_preview" class="text-[11px] font-bold text-slate-500 mt-1.5 block truncate max-w-xs mx-auto"></span>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end gap-2.5">
                    <button type="button" onclick="closeModal('categoryModal')" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">Cancel</button>
                    <button type="submit" id="submitBtn" class="bg-sky-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-sky-800 transition shadow-md shadow-sky-700/20 active:scale-98">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // SweetAlert Flash Notifications
        <?php if ($message): ?>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?php echo $messageType; ?>',
                title: '<?php echo addslashes($message); ?>',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        <?php endif; ?>

        // Safe Delete Confirmation
        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete this category?',
                text: "If books are assigned to this category, deletion will be blocked.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // Image Preview & Removal Logic
        const catImageInput = document.getElementById('cat_image_input');
        const catDropzone = document.getElementById('cat_image_dropzone');
        const imgPreviewContainer = document.getElementById('img_preview_container');
        const imgPreview = document.getElementById('img_preview');
        const imgNamePreview = document.getElementById('img_name_preview');
        const existingImage = document.getElementById('existing_image');
        const removeImageInput = document.getElementById('remove_image');
        const removeImageBtn = document.getElementById('remove_image_btn');

        catImageInput?.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    imgPreview.src = e.target.result;
                    imgNamePreview.innerText = file.name;
                    catDropzone.classList.add('hidden');
                    imgPreviewContainer.classList.remove('hidden');
                    removeImageInput.value = '0';
                };
                reader.readAsDataURL(file);
            }
        });

        removeImageBtn?.addEventListener('click', function(e) {
            e.stopPropagation();
            catImageInput.value = '';
            imgPreview.src = '';
            imgNamePreview.innerText = '';
            existingImage.value = '';
            removeImageInput.value = '1';
            imgPreviewContainer.classList.add('hidden');
            catDropzone.classList.remove('hidden');
        });

        // Modal Open / Close Logic
        function openModal(modalId, mode, data = null) {
            const modal = document.getElementById(modalId);
            const title = document.getElementById('modalTitle');
            const btn = document.getElementById('submitBtn');
            
            removeImageInput.value = '0';
            catImageInput.value = '';
            
            if (mode === 'edit' && data) {
                title.innerText = 'Edit Category';
                btn.innerText = 'Update Changes';
                document.getElementById('cat_id').value = data.id;
                document.getElementById('cat_name').value = data.name;
                document.getElementById('cat_slug').value = data.slug;
                document.getElementById('parent_id').value = data.parent_id;
                
                if (data.image) {
                    existingImage.value = data.image;
                    imgPreview.src = '/' + data.image.replace(/^\//, '');
                    imgNamePreview.innerText = data.image.split('/').pop();
                    imgPreviewContainer.classList.remove('hidden');
                    catDropzone.classList.add('hidden');
                } else {
                    existingImage.value = '';
                    imgPreviewContainer.classList.add('hidden');
                    catDropzone.classList.remove('hidden');
                }
            } else {
                title.innerText = 'Add New Category';
                btn.innerText = 'Save Category';
                document.getElementById('cat_id').value = '';
                document.getElementById('cat_name').value = '';
                document.getElementById('cat_slug').value = '';
                document.getElementById('parent_id').value = '0';
                existingImage.value = '';
                imgPreviewContainer.classList.add('hidden');
                catDropzone.classList.remove('hidden');
            }
            
            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Auto Slug from Name
        document.getElementById('cat_name')?.addEventListener('keyup', function() {
            let name = this.value;
            let slug = name.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            document.getElementById('cat_slug').value = slug;
        });

        const headerOpenBtn = document.getElementById('mobile-menu-open-header');
        if (headerOpenBtn) {
            headerOpenBtn.addEventListener('click', function() {
                const actualOpenBtn = document.getElementById('mobile-menu-open');
                if (actualOpenBtn) actualOpenBtn.click();
            });
        }
    </script>
</body>
</html>