<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /login");
    exit;
}

require_once '../../config/db.php';

// Attribute ID Validation
$attr_id = $_GET['attr_id'] ?? null;
if (!$attr_id) {
    header("Location: add_attribute.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM attributes WHERE id = ?");
$stmt->execute([$attr_id]);
$attribute = $stmt->fetch();

if (!$attribute) {
    header("Location: add_attribute.php");
    exit;
}

// SweetAlert Session Messages
$message = '';
$messageType = '';
if (isset($_SESSION['swal_msg'])) {
    $message = $_SESSION['swal_msg'];
    $messageType = $_SESSION['swal_type'];
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
}

// Fetch Terms for this attribute
$stmt = $pdo->prepare("SELECT * FROM attribute_terms WHERE attribute_id = ? ORDER BY name ASC");
$stmt->execute([$attr_id]);
$terms = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms for <?php echo htmlspecialchars($attribute['name']); ?> - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; color: #1f2937; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        
        /* Color input styling */
        input[type="color"] { -webkit-appearance: none; border: none; padding: 0; border-radius: 8px; overflow: hidden; }
        input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        input[type="color"]::-webkit-color-swatch { border: none; border-radius: 8px; }

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
                <a href="add_attribute.php" class="text-gray-400 hover:text-blue-600 transition p-2 bg-gray-50 rounded-lg border border-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">
                    Terms: <span class="text-blue-600"><?php echo htmlspecialchars($attribute['name']); ?></span>
                </h1>
            </div>
            <button onclick="openModal('termModal', 'add')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition shadow-md flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Term
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="desktop-table">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="px-6 py-4 font-bold">Name</th>
                                <th class="px-6 py-4 font-bold">Slug</th>
                                <?php if($attribute['type'] == 'color'): ?>
                                    <th class="px-6 py-4 font-bold">Color</th>
                                <?php endif; ?>
                                <th class="px-6 py-4 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php if(!empty($terms)): ?>
                                <?php foreach($terms as $term): ?>
                                <tr class="hover:bg-gray-50 transition group">
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-gray-900 text-base">
                                            <?php echo htmlspecialchars($term['name']); ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 font-mono">
                                        <?php echo htmlspecialchars($term['slug']); ?>
                                    </td>
                                    <?php if($attribute['type'] == 'color'): ?>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full shadow-inner border border-gray-300" style="background-color: <?php echo htmlspecialchars($term['color_code']); ?>;"></div>
                                            <span class="text-xs text-gray-400 uppercase"><?php echo htmlspecialchars($term['color_code']); ?></span>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                    <td class="px-6 py-4 text-right">
                                        <button onclick='openModal("termModal", "edit", <?php echo json_encode($term, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="text-blue-600 hover:text-blue-800 font-bold text-sm mr-3 transition">Edit</button>
                                        <button onclick="confirmDelete(<?php echo $term['id']; ?>)" class="text-red-600 hover:text-red-800 font-bold text-sm transition">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($attribute['type'] == 'color') ? '4' : '3'; ?>" class="px-6 py-12 text-center text-gray-500">
                                        No terms found. Start adding terms for <?php echo htmlspecialchars($attribute['name']); ?>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mobile-card-grid p-4">
                    <?php if(!empty($terms)): ?>
                        <?php foreach($terms as $term): ?>
                        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <?php if($attribute['type'] == 'color'): ?>
                                    <div class="w-10 h-10 rounded-full shadow-inner border border-gray-300 shrink-0" style="background-color: <?php echo htmlspecialchars($term['color_code']); ?>;"></div>
                                <?php endif; ?>
                                <div>
                                    <p class="text-sm font-bold text-gray-900">
                                        <?php echo htmlspecialchars($term['name']); ?>
                                    </p>
                                    <p class="text-[10px] text-gray-500 font-mono mt-0.5"><?php echo htmlspecialchars($term['slug']); ?></p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick='openModal("termModal", "edit", <?php echo json_encode($term, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="bg-blue-50 text-blue-600 p-2 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                <button onclick="confirmDelete(<?php echo $term['id']; ?>)" class="bg-red-50 text-red-600 p-2 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">No terms found.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <form id="deleteForm" action="manage_terms_action.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_term">
        <input type="hidden" name="attr_id" value="<?php echo $attr_id; ?>">
        <input type="hidden" name="term_id" id="delete_id">
    </form>

    <div id="termModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
            <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 id="modalTitle" class="font-bold text-gray-900 text-lg">Add New Term</h3>
                <button onclick="closeModal('termModal')" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            
            <form action="manage_terms_action.php" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="action" value="save_term">
                <input type="hidden" name="attr_id" value="<?php echo $attr_id; ?>">
                <input type="hidden" name="term_id" id="term_id">

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="term_name" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" placeholder="e.g. Red, XL">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Slug</label>
                    <input type="text" name="slug" id="term_slug" class="w-full bg-gray-50 border border-gray-300 text-gray-600 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition font-mono text-sm">
                    <p class="text-[11px] text-gray-500 mt-1.5">Leave blank to auto-generate from name.</p>
                </div>

                <?php if($attribute['type'] == 'color'): ?>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Choose Color <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-4 bg-gray-50 p-2 rounded-lg border border-gray-200 w-max">
                        <input type="color" name="color_code" id="color_code" value="#000000" class="w-12 h-10 bg-transparent cursor-pointer border border-gray-300 rounded shadow-sm">
                        <span class="text-sm font-mono text-gray-600" id="color_hex_display">#000000</span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('termModal')" class="px-5 py-2.5 rounded-lg font-bold text-gray-600 hover:bg-gray-100 transition">Cancel</button>
                    <button type="submit" id="submitBtn" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-md">Save Term</button>
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
                text: "This term will be permanently deleted.",
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
                title.innerText = 'Edit Term';
                btn.innerText = 'Update Changes';
                document.getElementById('term_id').value = data.id;
                document.getElementById('term_name').value = data.name;
                document.getElementById('term_slug').value = data.slug;
                
                <?php if($attribute['type'] == 'color'): ?>
                if(data.color_code) {
                    document.getElementById('color_code').value = data.color_code;
                    document.getElementById('color_hex_display').innerText = data.color_code;
                }
                <?php endif; ?>
            } else {
                title.innerText = 'Add New Term';
                btn.innerText = 'Save Term';
                document.getElementById('term_id').value = '';
                document.getElementById('term_name').value = '';
                document.getElementById('term_slug').value = '';
                
                <?php if($attribute['type'] == 'color'): ?>
                document.getElementById('color_code').value = '#000000';
                document.getElementById('color_hex_display').innerText = '#000000';
                <?php endif; ?>
            }
            
            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Auto Slug
        document.getElementById('term_name').addEventListener('keyup', function() {
            let name = this.value;
            let slug = name.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            document.getElementById('term_slug').value = slug;
        });

        // Color Hex Display Update
        <?php if($attribute['type'] == 'color'): ?>
        document.getElementById('color_code').addEventListener('input', function() {
            document.getElementById('color_hex_display').innerText = this.value.toUpperCase();
        });
        <?php endif; ?>

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