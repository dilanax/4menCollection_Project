<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. STRICT SECURITY: Admin Only Access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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
$limit = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

// Fetch Attributes with Terms Count
$sql = "
    SELECT a.*, 
           (SELECT COUNT(*) FROM attribute_terms t WHERE t.attribute_id = a.id) as terms_count
    FROM attributes a
    ORDER BY a.name ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->query($sql);
$attributes = $stmt->fetchAll();

// Total count
$total_stmt = $pdo->query("SELECT COUNT(*) FROM attributes");
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attributes - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; color: #1f2937; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        
        /* Mobile Card View Display */
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
        
        <header class="bg-white border-b border-gray-200 px-4 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-open-header" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Product Attributes</h1>
            </div>
            <button onclick="openModal('attributeModal', 'add')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition shadow-md flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Attribute
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
                <div class="desktop-table">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="px-6 py-4 font-bold">Name</th>
                                <th class="px-6 py-4 font-bold">Slug</th>
                                <th class="px-6 py-4 font-bold">Type</th>
                                <th class="px-6 py-4 font-bold">Terms</th>
                                <th class="px-6 py-4 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php if(!empty($attributes)): ?>
                                <?php foreach($attributes as $attr): ?>
                                <tr class="hover:bg-gray-50 transition group">
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-gray-900 text-base">
                                            <?php echo htmlspecialchars($attr['name']); ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 font-mono">
                                        <?php echo htmlspecialchars($attr['slug']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 bg-gray-100 border border-gray-200 text-gray-600 rounded font-bold text-[10px] uppercase tracking-wider">
                                            <?php echo htmlspecialchars($attr['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="manage_terms.php?attr_id=<?php echo $attr['id']; ?>" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 font-bold text-sm bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg border border-blue-100 transition">
                                            Configure terms
                                            <span class="bg-blue-200 text-blue-800 px-1.5 py-0.5 rounded text-[10px]"><?php echo $attr['terms_count']; ?></span>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button onclick='openModal("attributeModal", "edit", <?php echo json_encode($attr, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="text-gray-500 hover:text-blue-600 font-bold text-sm mr-3 transition">Edit</button>
                                        <button onclick="confirmDelete(<?php echo $attr['id']; ?>)" class="text-gray-500 hover:text-red-600 font-bold text-sm transition">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No attributes found. Create one to get started.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mobile-card-grid p-4">
                    <?php if(!empty($attributes)): ?>
                        <?php foreach($attributes as $attr): ?>
                        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex flex-col gap-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-base font-bold text-gray-900 mb-0.5">
                                        <?php echo htmlspecialchars($attr['name']); ?>
                                    </p>
                                    <p class="text-[11px] text-gray-500 font-mono mb-2"><?php echo htmlspecialchars($attr['slug']); ?></p>
                                    <span class="px-2 py-0.5 bg-gray-100 border border-gray-200 text-gray-600 rounded font-bold text-[9px] uppercase tracking-wider">
                                        <?php echo htmlspecialchars($attr['type']); ?>
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick='openModal("attributeModal", "edit", <?php echo json_encode($attr, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="bg-gray-50 text-gray-500 hover:text-blue-600 p-2 rounded-lg border border-gray-200 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                    <button onclick="confirmDelete(<?php echo $attr['id']; ?>)" class="bg-gray-50 text-gray-500 hover:text-red-600 p-2 rounded-lg border border-gray-200 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                </div>
                            </div>
                            <div class="pt-3 border-t border-gray-100">
                                <a href="manage_terms.php?attr_id=<?php echo $attr['id']; ?>" class="w-full flex items-center justify-center gap-2 text-blue-600 font-bold text-sm bg-blue-50 py-2 rounded-lg border border-blue-100">
                                    Configure terms
                                    <span class="bg-blue-200 text-blue-800 px-1.5 py-0.5 rounded text-[10px]"><?php echo $attr['terms_count']; ?></span>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">No attributes found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($total_pages > 1): ?>
            <div class="flex justify-center mt-6 mb-10">
                <nav class="flex gap-2">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <a href="?p=<?php echo $i; ?>" class="w-10 h-10 flex items-center justify-center rounded-lg font-bold transition <?php echo $i == $page ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <form id="deleteForm" action="add_attribute_action.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_attribute">
        <input type="hidden" name="attribute_id" id="delete_id">
    </form>

    <div id="attributeModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
            <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 id="modalTitle" class="font-bold text-gray-900 text-lg">Add New Attribute</h3>
                <button onclick="closeModal('attributeModal')" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            
            <form action="add_attribute_action.php" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="action" value="save_attribute">
                <input type="hidden" name="attribute_id" id="attr_id">

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="attr_name" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" placeholder="e.g. Color, Size">
                    <p class="text-[11px] text-gray-500 mt-1.5">Name for the attribute (shown on the front-end).</p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Slug</label>
                    <input type="text" name="slug" id="attr_slug" class="w-full bg-gray-50 border border-gray-300 text-gray-600 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition font-mono text-sm">
                    <p class="text-[11px] text-gray-500 mt-1.5">Leave blank to auto-generate from name.</p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                    <select name="type" id="attr_type" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        <option value="select">Select (Dropdown)</option>
                        <option value="color">Color Swatch</option>
                        <option value="button">Button / Label</option>
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1.5">How this attribute should be displayed to the user.</p>
                </div>

                <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('attributeModal')" class="px-5 py-2.5 rounded-lg font-bold text-gray-600 hover:bg-gray-100 transition">Cancel</button>
                    <button type="submit" id="submitBtn" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-md">Save Attribute</button>
                </div>
            </form>
        </div>
    </div>

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

        // Safe Delete Confirmation
        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "If this attribute has configured terms, deletion will be blocked.",
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

        // Modal Logic
        function openModal(modalId, mode, data = null) {
            const modal = document.getElementById(modalId);
            const title = document.getElementById('modalTitle');
            const btn = document.getElementById('submitBtn');
            
            if (mode === 'edit' && data) {
                title.innerText = 'Edit Attribute';
                btn.innerText = 'Update Changes';
                document.getElementById('attr_id').value = data.id;
                document.getElementById('attr_name').value = data.name;
                document.getElementById('attr_slug').value = data.slug;
                document.getElementById('attr_type').value = data.type;
            } else {
                title.innerText = 'Add New Attribute';
                btn.innerText = 'Save Attribute';
                document.getElementById('attr_id').value = '';
                document.getElementById('attr_name').value = '';
                document.getElementById('attr_slug').value = '';
                document.getElementById('attr_type').value = 'select';
            }
            
            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Auto Slug
        document.getElementById('attr_name').addEventListener('keyup', function() {
            let name = this.value;
            let slug = name.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            document.getElementById('attr_slug').value = slug;
        });

        // Mobile Menu
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