<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) { 
    header("Location: /login"); 
    exit; 
}

require_once '../../config/db.php';

// SweetAlert Messages
$message = $_SESSION['swal_msg'] ?? '';
$messageType = $_SESSION['swal_type'] ?? '';
unset($_SESSION['swal_msg'], $_SESSION['swal_type']);

// Fetch All Categories
try {
    $categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM blog_post_categories WHERE category_id = c.id) as post_count FROM blog_categories c ORDER BY c.name ASC")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Function to build hierarchical tree
function buildCategoryTree($elements, $parentId = 0) {
    $branch = array();
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = buildCategoryTree($elements, $element['id']);
            if ($children) { $element['children'] = $children; }
            $branch[] = $element;
        }
    }
    return $branch;
}
$catTree = buildCategoryTree($categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Categories - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; color: #1f2937; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        
        @media (max-width: 1023px) {
            .mobile-card-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
            .desktop-table { display: none; }
        }
        @media (min-width: 1024px) {
            .mobile-card-grid { display: none; }
            .desktop-table { display: table; width: 100%; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-gray-100">

    <?php 
    if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-open-header" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Blog Categories</h1>
            </div>
            <button onclick="openModal('categoryModal', 'add')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition shadow-md flex items-center gap-2 whitespace-nowrap">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Category
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            <p class="text-sm text-gray-500 mb-6 font-medium">Organize your blog posts into hierarchical categories.</p>

            <!-- REMOVED: max-w-6xl mx-auto to make it full width -->
            <div class="w-full">
                
                <!-- Desktop Table View -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden desktop-table">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="px-6 py-4 font-bold w-[40%]">Category Name</th>
                                <th class="px-6 py-4 font-bold w-[30%]">Slug</th>
                                <th class="px-6 py-4 font-bold text-center w-[15%]">Linked Posts</th>
                                <th class="px-6 py-4 font-bold text-right w-[15%]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php
                            function renderTable($tree, $level = 0) {
                                foreach($tree as $cat) {
                                    $dash = str_repeat('<span class="text-gray-300 mr-1">—</span>', $level);
                                    $bgColor = $level === 0 ? 'bg-white' : 'bg-gray-50/50';
                                    
                                    // Escape data for JS function
                                    $cat_json = htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8');
                                    
                                    echo '<tr class="hover:bg-blue-50 transition group '.$bgColor.'">';
                                    echo '<td class="px-6 py-4 font-bold text-gray-900 flex items-center">'.$dash.htmlspecialchars($cat['name']).'</td>';
                                    echo '<td class="px-6 py-4 text-sm text-gray-500 font-mono">'.htmlspecialchars($cat['slug']).'</td>';
                                    echo '<td class="px-6 py-4 text-center"><span class="bg-blue-50 border border-blue-100 text-blue-600 px-3 py-1 rounded-full text-xs font-bold shadow-sm">'.$cat['post_count'].'</span></td>';
                                    echo '<td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2 items-center opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button type="button" onclick="openModal(\'categoryModal\', \'edit\', '.$cat_json.')" class="text-xs bg-gray-50 text-gray-600 hover:bg-gray-200 border border-gray-200 px-3 py-1.5 rounded font-bold transition">Edit</button>
                                                <button type="button" onclick="confirmDelete('.$cat['id'].')" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-100 px-3 py-1.5 rounded font-bold transition">Delete</button>
                                            </div>
                                          </td>';
                                    echo '</tr>';
                                    
                                    if(isset($cat['children'])) { 
                                        renderTable($cat['children'], $level + 1); 
                                    }
                                }
                            }

                            if(!empty($catTree)) {
                                renderTable($catTree);
                            } else {
                                echo '<tr><td colspan="4" class="px-6 py-12 text-center text-gray-500 font-medium">No blog categories found. Click "Add Category" to start.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-card-grid pb-10">
                    <?php if(empty($categories)): ?>
                        <div class="bg-white border border-gray-200 rounded-xl p-8 text-center shadow-sm text-gray-500 font-medium">No blog categories found.</div>
                    <?php else: ?>
                        <?php foreach($categories as $cat): ?>
                            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm flex flex-col gap-4">
                                <div class="flex justify-between items-start border-b border-gray-100 pb-3">
                                    <div>
                                        <h3 class="font-bold text-gray-900 text-base mb-0.5">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                            <?php if($cat['parent_id'] > 0): ?>
                                                <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded ml-2">Sub-category</span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-[10px] text-gray-400 font-mono"><?php echo htmlspecialchars($cat['slug']); ?></p>
                                    </div>
                                    <span class="bg-blue-50 border border-blue-100 text-blue-600 text-[10px] font-bold px-2 py-1 rounded shadow-sm">Posts: <?php echo $cat['post_count']; ?></span>
                                </div>

                                <div class="flex gap-2">
                                    <?php $cat_json_mobile = htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8'); ?>
                                    <button type="button" onclick="openModal('categoryModal', 'edit', <?php echo $cat_json_mobile; ?>)" class="flex-1 text-center text-xs bg-gray-50 text-gray-700 hover:bg-gray-200 border border-gray-300 px-3 py-2.5 rounded-lg font-bold transition">Edit</button>
                                    <button type="button" onclick="confirmDelete(<?php echo $cat['id']; ?>)" class="flex-1 text-center text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-3 py-2.5 rounded-lg font-bold transition">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <!-- Modal for Add/Edit Category -->
    <div id="categoryModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
                <h3 id="modalTitle" class="font-bold text-gray-900 text-lg flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    <span>Add New Category</span>
                </h3>
                <button onclick="closeModal('categoryModal')" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            
            <form action="blog_category_action.php" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="action" id="formAction" value="add_category">
                <input type="hidden" name="category_id" id="edit_category_id" value="">
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Category Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="cat_name" required placeholder="e.g. Technology" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Slug <span class="text-gray-400 font-normal">(Auto-generated)</span></label>
                    <input type="text" name="slug" id="cat_slug" placeholder="e.g. technology" class="w-full bg-gray-50 border border-gray-300 text-gray-600 font-mono text-sm rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Parent Category</label>
                    <select name="parent_id" id="cat_parent" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        <option value="0">None (Top Level)</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Description <span class="text-gray-400 font-normal">(Optional)</span></label>
                    <textarea name="description" id="cat_desc" rows="3" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-3 shrink-0">
                    <button type="button" onclick="closeModal('categoryModal')" class="px-5 py-2.5 rounded-lg font-bold text-gray-600 hover:bg-gray-200 border border-gray-300 bg-white transition shadow-sm w-1/3">Cancel</button>
                    <button type="submit" id="submitBtn" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-md w-2/3">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Form for Deletion -->
    <form id="deleteForm" action="blog_category_action.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_category">
        <input type="hidden" name="category_id" id="delete_id">
    </form>

    <!-- JS LOGIC -->
    <script>
        // SweetAlert 
        <?php if($message): ?>
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

        let isEditMode = false;

        // Modal Logic
        function openModal(modalId, mode, data = null) {
            const modal = document.getElementById(modalId);
            const title = document.querySelector('#' + modalId + ' #modalTitle span');
            const formAction = document.getElementById('formAction');
            const submitBtn = document.getElementById('submitBtn');
            const parentSelect = document.getElementById('cat_parent');

            if (mode === 'edit' && data) {
                isEditMode = true;
                title.innerText = 'Edit Category';
                formAction.value = 'update_category';
                submitBtn.innerText = 'Update Changes';
                
                document.getElementById('edit_category_id').value = data.id;
                document.getElementById('cat_name').value = data.name;
                document.getElementById('cat_slug').value = data.slug;
                document.getElementById('cat_desc').value = data.description;
                
                // Set parent and disable self as parent
                for (let i = 0; i < parentSelect.options.length; i++) {
                    parentSelect.options[i].disabled = false;
                    if (parentSelect.options[i].value == data.id) {
                        parentSelect.options[i].disabled = true; // Cannot be own parent
                    }
                }
                parentSelect.value = data.parent_id;

            } else {
                isEditMode = false;
                title.innerText = 'Add New Category';
                formAction.value = 'add_category';
                submitBtn.innerText = 'Save Category';
                
                document.getElementById('edit_category_id').value = '';
                document.getElementById('cat_name').value = '';
                document.getElementById('cat_slug').value = '';
                document.getElementById('cat_desc').value = '';
                
                for (let i = 0; i < parentSelect.options.length; i++) {
                    parentSelect.options[i].disabled = false;
                }
                parentSelect.value = '0';
            }
            
            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Auto Slug Generation
        document.getElementById('cat_name').addEventListener('keyup', function() {
            if(!isEditMode) {
                document.getElementById('cat_slug').value = this.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            }
        });

        // Delete Confirmation
        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Category?',
                text: "Posts in this category will not be deleted, but they will lose this category tag. Child categories will become top-level.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#4b5563',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            })
        }

        // Mobile Sidebar Toggle
        const headerOpenBtn = document.getElementById('mobile-menu-open-header');
        if(headerOpenBtn) {
            headerOpenBtn.addEventListener('click', function() {
                const actualOpenBtn = document.getElementById('mobile-menu-open');
                if(actualOpenBtn) actualOpenBtn.click();
            });
        }
    </script>
</body>
</html>