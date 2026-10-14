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

// Fetch Brands with Product Count
$sql = "
    SELECT b.*, 
           (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) as product_count
    FROM brands b
    ORDER BY b.name ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->query($sql);
$brands = $stmt->fetchAll();

// Total count
$total_stmt = $pdo->query("SELECT COUNT(*) FROM brands");
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Brands | 4MEN COLLECTION Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, input, textarea, select, button { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-neutral-800 antialiased">

    <?php 
    if (file_exists('../../sidebar.php')) { require_once '../../sidebar.php'; } 
    elseif (file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#f8fafc]">
        
        <!-- Sticky Top Header -->
        <header class="bg-white/95 backdrop-blur-md border-b border-neutral-200 px-4 sm:px-6 lg:px-8 py-3.5 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-open-header" type="button" class="md:hidden p-2 text-neutral-600 hover:text-black focus:outline-none rounded-xl hover:bg-neutral-100 transition">
                    <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">4MEN CATALOG</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-neutral-300"></span>
                        <span class="text-[10px] font-semibold text-neutral-500"><?php echo number_format($total_rows); ?> brands</span>
                    </div>
                    <h1 class="text-base sm:text-lg lg:text-xl font-extrabold text-neutral-900 tracking-tight">Brands</h1>
                </div>
            </div>

            <button type="button" onclick="openModal('brandModal', 'add')" class="bg-neutral-900 hover:bg-black text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-sm hover:shadow flex items-center gap-1.5 active:scale-98">
                <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Brand</span>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">

            <!-- Desktop Table View (Displays on screen >= 1024px) -->
            <div class="hidden lg:block bg-white rounded-2xl border border-neutral-200 shadow-xs overflow-hidden mb-6">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-neutral-400 text-[10px] uppercase tracking-wider font-extrabold">
                            <th class="px-6 py-4 w-24">Logo</th>
                            <th class="px-6 py-4">Brand Name</th>
                            <th class="px-6 py-4">Slug</th>
                            <th class="px-6 py-4 text-center">Products Linked</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 text-xs">
                        <?php if (!empty($brands)): ?>
                            <?php foreach ($brands as $brand): 
                                $b_id = (int)($brand['id'] ?? 0);
                                $b_name = (string)($brand['name'] ?? '');
                                $b_slug = (string)($brand['slug'] ?? '');
                                $b_image = !empty($brand['image']) ? $brand['image'] : (!empty($brand['logo']) ? $brand['logo'] : '');
                                $b_count = (int)($brand['product_count'] ?? 0);
                            ?>
                            <tr class="hover:bg-neutral-50/70 transition group">
                                <td class="px-6 py-3.5">
                                    <?php if ($b_image): ?>
                                        <img src="/<?php echo htmlspecialchars($b_image); ?>" class="w-14 h-9 rounded-lg object-contain bg-white p-1 border border-neutral-200 shadow-xs" alt="<?php echo htmlspecialchars($b_name); ?>">
                                    <?php else: ?>
                                        <div class="w-14 h-9 rounded-lg bg-neutral-100 flex items-center justify-center border border-neutral-200 text-neutral-400">
                                            <span class="text-[9px] font-bold uppercase tracking-wider">No Logo</span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-3.5">
                                    <p class="font-extrabold text-neutral-900 text-sm">
                                        <?php echo htmlspecialchars($b_name); ?>
                                    </p>
                                </td>

                                <td class="px-6 py-3.5 text-neutral-500 font-mono text-[11px]">
                                    <?php echo htmlspecialchars($b_slug); ?>
                                </td>

                                <td class="px-6 py-3.5 text-center">
                                    <span class="inline-flex items-center justify-center px-2.5 py-0.5 text-[10px] font-bold rounded-md border <?php echo $b_count > 0 ? 'bg-neutral-100 text-neutral-800 border-neutral-200' : 'bg-neutral-50 text-neutral-400 border-neutral-200'; ?>">
                                        <?php echo $b_count; ?> Products
                                    </span>
                                </td>

                                <td class="px-6 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" onclick='openModal("brandModal", "edit", <?php echo json_encode($brand, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="p-1.5 text-neutral-700 hover:text-black rounded-lg hover:bg-neutral-100 transition" title="Edit Brand">
                                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button type="button" onclick="confirmDelete(<?php echo $b_id; ?>, '<?php echo addslashes(htmlspecialchars($b_name)); ?>')" class="p-1.5 text-rose-500 hover:text-rose-700 rounded-lg hover:bg-rose-50 transition" title="Delete Brand">
                                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-neutral-400 text-xs">No brands found in the catalog.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Responsive Card Grid View (Mobile: 1 Column | Tablet sm/md: 2 Columns) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:hidden gap-3 sm:gap-4 mb-6">
                <?php if (!empty($brands)): ?>
                    <?php foreach ($brands as $brand): 
                        $b_id = (int)($brand['id'] ?? 0);
                        $b_name = (string)($brand['name'] ?? '');
                        $b_slug = (string)($brand['slug'] ?? '');
                        $b_image = !empty($brand['image']) ? $brand['image'] : (!empty($brand['logo']) ? $brand['logo'] : '');
                        $b_count = (int)($brand['product_count'] ?? 0);
                    ?>
                    <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs flex flex-col justify-between hover:border-neutral-400 transition-all duration-200 group">
                        
                        <div class="flex items-start gap-3.5">
                            <!-- Logo Stage -->
                            <div class="relative shrink-0">
                                <?php if ($b_image): ?>
                                    <img src="/<?php echo htmlspecialchars($b_image); ?>" class="w-14 h-11 rounded-xl object-contain bg-white p-1 border border-neutral-200 shadow-xs" alt="<?php echo htmlspecialchars($b_name); ?>">
                                <?php else: ?>
                                    <div class="w-14 h-11 rounded-xl bg-neutral-100 flex items-center justify-center border border-neutral-200 text-neutral-400">
                                        <span class="text-[8px] font-bold uppercase tracking-wider">No Logo</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Brand Name & Slug -->
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-extrabold text-neutral-900 leading-snug truncate" title="<?php echo htmlspecialchars($b_name); ?>">
                                    <?php echo htmlspecialchars($b_name); ?>
                                </h3>
                                <p class="text-[10px] text-neutral-400 font-mono truncate mt-0.5">
                                    <?php echo htmlspecialchars($b_slug); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="pt-3 mt-3 border-t border-neutral-100 flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 text-[10px] font-bold rounded-md border <?php echo $b_count > 0 ? 'bg-neutral-100 text-neutral-800 border-neutral-200' : 'bg-neutral-50 text-neutral-400 border-neutral-200'; ?>">
                                <?php echo $b_count; ?> Products
                            </span>

                            <div class="flex items-center gap-1">
                                <button type="button" onclick='openModal("brandModal", "edit", <?php echo json_encode($brand, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="px-2.5 py-1.5 text-xs font-bold text-neutral-700 bg-neutral-100 hover:bg-neutral-200 rounded-xl transition flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <span>Edit</span>
                                </button>
                                <button type="button" onclick="confirmDelete(<?php echo $b_id; ?>, '<?php echo addslashes(htmlspecialchars($b_name)); ?>')" class="p-1.5 text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition" title="Delete Brand">
                                    <svg class="w-3.5 h-3.5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>

                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-8 text-center text-neutral-400 bg-white rounded-2xl border border-neutral-200 text-xs">
                        No brands found in the catalog.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-between bg-white px-4 py-3 sm:px-6 rounded-2xl border border-neutral-200 shadow-xs mb-8 flex-wrap gap-3">
                    <div class="text-xs text-neutral-500">
                        Page <strong class="text-neutral-900"><?php echo $page; ?></strong> of <strong class="text-neutral-900"><?php echo $total_pages; ?></strong>
                    </div>

                    <div class="inline-flex items-center gap-1.5">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?p=<?php echo $i; ?>" class="px-3 py-1.5 border rounded-xl text-xs font-bold transition <?php echo $i == $page ? 'bg-neutral-900 text-white border-neutral-900 shadow-xs' : 'border-neutral-200 text-neutral-700 hover:bg-neutral-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Hidden Form for Deletion -->
    <form id="deleteForm" action="add_brand_action.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_brand">
        <input type="hidden" name="brand_id" id="delete_id">
    </form>

    <!-- Brand Modal (Add / Edit) with Image Remove Feature -->
    <div id="brandModal" class="fixed inset-0 bg-neutral-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white border border-neutral-100 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            
            <div class="flex justify-between items-center p-5 border-b border-neutral-100 bg-neutral-50/70">
                <h3 id="modalTitle" class="font-extrabold text-neutral-900 text-base">Add New Brand</h3>
                <button type="button" onclick="closeModal('brandModal')" class="text-neutral-400 hover:text-rose-600 p-1.5 rounded-full hover:bg-rose-50 transition">
                    <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form action="add_brand_action.php" method="POST" enctype="multipart/form-data" class="p-5 sm:p-6 space-y-4">
                <input type="hidden" name="action" value="save_brand">
                <input type="hidden" name="brand_id" id="brand_id">
                <input type="hidden" name="existing_image" id="existing_image">
                <input type="hidden" name="remove_image" id="remove_image" value="0">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-1.5">Brand Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="brand_name" required placeholder="e.g. Ralph Lauren, Zara Men, Calvin Klein" class="w-full bg-neutral-50 border border-neutral-200 text-neutral-900 rounded-xl px-4 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-neutral-900 outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-1.5">URL Slug</label>
                    <input type="text" name="slug" id="brand_slug" placeholder="auto-generated-from-name" class="w-full bg-neutral-50 border border-neutral-200 text-neutral-800 rounded-xl px-4 py-2.5 focus:bg-white focus:border-neutral-900 outline-none transition-colors font-mono text-xs">
                </div>

                <!-- Brand Logo Upload & Removable Preview -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-1.5">Brand Logo</label>
                    
                    <!-- Dropzone (Shown when no image or after removal) -->
                    <div id="brand_image_dropzone" class="relative border-2 border-dashed border-neutral-200 hover:border-neutral-900 rounded-2xl p-4 text-center bg-neutral-50/50 hover:bg-neutral-100/50 transition-all cursor-pointer">
                        <input type="file" id="brand_image_input" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <div class="flex flex-col items-center justify-center py-1">
                            <svg class="w-7 h-7 text-neutral-800 mb-1.5 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span class="text-xs font-bold text-neutral-800">Choose Brand Logo</span>
                            <span class="text-[10px] text-neutral-400 mt-0.5">JPEG, PNG, or WEBP</span>
                        </div>
                    </div>

                    <!-- Removable Image Preview Container -->
                    <div id="img_preview_container" class="hidden mt-2.5 relative text-center bg-neutral-50 p-3 rounded-2xl border border-neutral-200">
                        <div class="relative inline-block">
                            <img id="img_preview" src="" class="h-16 w-24 object-contain bg-white rounded-xl p-1 border border-neutral-300 shadow-xs" alt="Logo Preview">
                            <button type="button" id="remove_image_btn" class="absolute -top-2 -right-2 bg-rose-600 hover:bg-rose-700 text-white rounded-full p-1 shadow-md transition-transform active:scale-95" title="Remove logo">
                                <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <span id="img_name_preview" class="text-[11px] font-bold text-neutral-500 mt-1.5 block truncate max-w-xs mx-auto"></span>
                    </div>
                </div>

                <div class="pt-4 border-t border-neutral-100 flex justify-end gap-2.5">
                    <button type="button" onclick="closeModal('brandModal')" class="px-4 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition">Cancel</button>
                    <button type="submit" id="submitBtn" class="bg-neutral-900 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-black transition shadow-sm active:scale-98">Save Brand</button>
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
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'Delete this brand?',
                text: name ? `Are you sure you want to delete "${name}"? If products are linked to this brand, deletion will be blocked.` : "If products are assigned to this brand, deletion will be blocked.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#000000',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete brand',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // ==========================================
        // 📸 LOGO PREVIEW & REMOVAL LOGIC
        // ==========================================
        const brandImageInput = document.getElementById('brand_image_input');
        const brandDropzone = document.getElementById('brand_image_dropzone');
        const imgPreviewContainer = document.getElementById('img_preview_container');
        const imgPreview = document.getElementById('img_preview');
        const imgNamePreview = document.getElementById('img_name_preview');
        const existingImage = document.getElementById('existing_image');
        const removeImageInput = document.getElementById('remove_image');
        const removeImageBtn = document.getElementById('remove_image_btn');

        brandImageInput?.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    imgPreview.src = e.target.result;
                    imgNamePreview.innerText = file.name;
                    brandDropzone.classList.add('hidden');
                    imgPreviewContainer.classList.remove('hidden');
                    removeImageInput.value = '0';
                };
                reader.readAsDataURL(file);
            }
        });

        removeImageBtn?.addEventListener('click', function(e) {
            e.stopPropagation();
            brandImageInput.value = ''; // Clear file input
            imgPreview.src = '';
            imgNamePreview.innerText = '';
            existingImage.value = ''; // Clear existing DB image
            removeImageInput.value = '1'; // Signal backend to delete
            imgPreviewContainer.classList.add('hidden');
            brandDropzone.classList.remove('hidden');
        });

        // Modal Open / Close Logic
        function openModal(modalId, mode, data = null) {
            const modal = document.getElementById(modalId);
            const title = document.getElementById('modalTitle');
            const btn = document.getElementById('submitBtn');
            
            // Reset image states
            removeImageInput.value = '0';
            brandImageInput.value = '';
            
            if (mode === 'edit' && data) {
                title.innerText = 'Edit Brand';
                btn.innerText = 'Update Brand';
                document.getElementById('brand_id').value = data.id;
                document.getElementById('brand_name').value = data.name;
                document.getElementById('brand_slug').value = data.slug;
                
                if (data.image) {
                    existingImage.value = data.image;
                    imgPreview.src = '/' + data.image.replace(/^\//, '');
                    imgNamePreview.innerText = data.image.split('/').pop();
                    imgPreviewContainer.classList.remove('hidden');
                    brandDropzone.classList.add('hidden');
                } else {
                    existingImage.value = '';
                    imgPreviewContainer.classList.add('hidden');
                    brandDropzone.classList.remove('hidden');
                }
            } else {
                title.innerText = 'Add New Brand';
                btn.innerText = 'Save Brand';
                document.getElementById('brand_id').value = '';
                document.getElementById('brand_name').value = '';
                document.getElementById('brand_slug').value = '';
                existingImage.value = '';
                imgPreviewContainer.classList.add('hidden');
                brandDropzone.classList.remove('hidden');
            }
            
            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Auto Slug from Name
        document.getElementById('brand_name')?.addEventListener('keyup', function() {
            let name = this.value;
            let slug = name.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            document.getElementById('brand_slug').value = slug;
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