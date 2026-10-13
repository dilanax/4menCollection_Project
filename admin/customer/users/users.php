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

require_once '../../../config/db.php'; 

$current_user_id = (int)$_SESSION['user_id'];

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
// FILTERING & DATA FETCHING
// ==========================================
$where_clauses = ["1=1"];
$params = [];

// 1. Search Filter
$search = trim(htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'));
if (!empty($search)) {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm);
}

// 2. Role Filter
$filter_role = htmlspecialchars($_GET['role'] ?? '', ENT_QUOTES, 'UTF-8');
if (!empty($filter_role) && in_array($filter_role, ['admin', 'shop_admin', 'customer'])) {
    $where_clauses[] = "user_role = ?";
    $params[] = $filter_role;
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch Users
$sql = "SELECT id, username, email, user_role, created_at FROM users WHERE $where_sql ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

// Role Badge Color Helper
function getRoleBadge($role) {
    switch ($role) {
        case 'admin': return 'bg-red-100 text-red-700 border-red-200';
        case 'shop_admin': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'customer': return 'bg-green-100 text-green-700 border-green-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
}
function getRoleName($role) {
    switch ($role) {
        case 'admin': return 'Super Admin';
        case 'shop_admin': return 'Shop Manager';
        case 'customer': return 'Customer';
        default: return ucfirst($role);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
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
    if(file_exists('../../../sidebar.php')){ require_once '../../../sidebar.php'; } 
    elseif(file_exists('../../includes/sidebar.php')) { require_once '../../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-open-header" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Manage Users & Roles</h1>
            </div>
            <button onclick="openModal('addUserModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition shadow-md flex items-center gap-2 whitespace-nowrap">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add User
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 md:p-6 mb-6 md:mb-8">
                <form action="users.php" method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <svg class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search Username or Email..." class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <select name="role" class="bg-gray-50 border border-gray-300 text-gray-700 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 w-full md:w-auto">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $filter_role == 'admin' ? 'selected' : ''; ?>>Super Admin</option>
                            <option value="shop_admin" <?php echo $filter_role == 'shop_admin' ? 'selected' : ''; ?>>Shop Manager</option>
                            <option value="customer" <?php echo $filter_role == 'customer' ? 'selected' : ''; ?>>Customer</option>
                        </select>
                        <button type="submit" class="bg-gray-900 hover:bg-black text-white border border-gray-900 px-5 py-2 rounded-lg text-sm font-bold shadow-sm transition w-full md:w-auto">Filter</button>
                        <?php if(!empty($_GET)): ?>
                            <a href="users.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 border border-gray-300 rounded-lg text-sm font-bold transition flex items-center justify-center text-center w-full md:w-auto">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden desktop-table">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                            <th class="px-6 py-4 font-bold w-16">ID</th>
                            <th class="px-6 py-4 font-bold">User Info</th>
                            <th class="px-6 py-4 font-bold">Role</th>
                            <th class="px-6 py-4 font-bold">Joined Date</th>
                            <th class="px-6 py-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php if(empty($usersList)): ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach($usersList as $u): ?>
                                <tr class="hover:bg-gray-50 transition group">
                                    <td class="px-6 py-4 font-bold text-gray-400">
                                        #<?php echo $u['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold border border-gray-200">
                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($u['username']); ?></p>
                                            <p class="text-xs text-gray-500 font-mono mt-0.5"><?php echo htmlspecialchars($u['email']); ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 border rounded-md text-[10px] font-bold uppercase tracking-wider <?php echo getRoleBadge($u['user_role']); ?>">
                                            <?php echo getRoleName($u['user_role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-500">
                                        <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <button onclick="openEditModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>', '<?php echo $u['user_role']; ?>')" class="inline-flex items-center justify-center bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-100 p-2 rounded-lg transition" title="Edit User">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        
                                        <button onclick="openResetModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')" class="inline-flex items-center justify-center bg-orange-50 text-orange-500 hover:bg-orange-100 border border-orange-100 p-2 rounded-lg transition" title="Reset Password">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                        </button>

                                        <?php if($u['id'] != $current_user_id): ?>
                                            <form id="deleteForm_<?php echo $u['id']; ?>" action="users_action.php?<?php echo http_build_query($_GET); ?>" method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="button" onclick="confirmDelete(<?php echo $u['id']; ?>)" class="inline-flex items-center justify-center bg-red-50 text-red-500 hover:bg-red-100 border border-red-100 p-2 rounded-lg transition" title="Delete User">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button disabled class="inline-flex items-center justify-center bg-gray-100 text-gray-400 border border-gray-200 p-2 rounded-lg cursor-not-allowed" title="Cannot delete yourself">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mobile-card-grid pb-10">
                <?php if(empty($usersList)): ?>
                    <div class="bg-white border border-gray-200 rounded-2xl p-8 text-center shadow-sm">
                        <p class="text-gray-500 font-medium">No users found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($usersList as $u): ?>
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm flex flex-col gap-4 relative">
                            
                            <div class="flex justify-between items-start border-b border-gray-100 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold border border-gray-200 shrink-0">
                                        <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 text-base leading-tight"><?php echo htmlspecialchars($u['username']); ?></p>
                                        <p class="text-[11px] text-gray-500 font-mono mt-0.5 truncate"><?php echo htmlspecialchars($u['email']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="px-2.5 py-1 border rounded-md text-[10px] font-bold uppercase tracking-wider <?php echo getRoleBadge($u['user_role']); ?>">
                                    <?php echo getRoleName($u['user_role']); ?>
                                </span>
                                <span class="text-[11px] font-bold text-gray-400">ID: #<?php echo $u['id']; ?></span>
                            </div>

                            <div class="flex gap-2 mt-1">
                                <button onclick="openEditModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>', '<?php echo $u['user_role']; ?>')" class="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-100 text-center py-2 rounded-lg text-sm font-bold transition flex items-center justify-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg> Edit
                                </button>
                                <button onclick="openResetModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')" class="flex-1 bg-orange-50 hover:bg-orange-100 text-orange-500 border border-orange-100 text-center py-2 rounded-lg text-sm font-bold transition flex items-center justify-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg> Reset
                                </button>
                                
                                <?php if($u['id'] != $current_user_id): ?>
                                    <form id="deleteFormMobile_<?php echo $u['id']; ?>" action="users_action.php?<?php echo http_build_query($_GET); ?>" method="POST" class="flex-1">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="button" onclick="confirmDeleteMobile(<?php echo $u['id']; ?>)" class="w-full bg-red-50 hover:bg-red-100 text-red-500 border border-red-100 text-center py-2 rounded-lg text-sm font-bold transition flex items-center justify-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg> Del
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <div id="addUserModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-900 text-lg">Add New User</h3>
                <button onclick="closeModal('addUserModal')" class="text-gray-400 hover:text-red-500 transition"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            <form action="users_action.php?<?php echo http_build_query($_GET); ?>" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_user">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="6" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">User Role <span class="text-red-500">*</span></label>
                    <select name="user_role" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        <option value="customer">Customer</option>
                        <option value="shop_admin">Shop Manager</option>
                        <option value="admin">Super Admin</option>
                    </select>
                </div>
                <div class="pt-4 flex gap-3 border-t border-gray-100">
                    <button type="button" onclick="closeModal('addUserModal')" class="flex-1 bg-gray-100 text-gray-700 border border-gray-300 px-4 py-2.5 rounded-lg font-bold hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-md">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-900 text-lg">Edit User Details</h3>
                <button onclick="closeModal('editUserModal')" class="text-gray-400 hover:text-red-500 transition"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            <form action="users_action.php?<?php echo http_build_query($_GET); ?>" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" id="edit_username" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="edit_email" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">User Role <span class="text-red-500">*</span></label>
                    <select name="user_role" id="edit_role" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        <option value="customer">Customer</option>
                        <option value="shop_admin">Shop Manager</option>
                        <option value="admin">Super Admin</option>
                    </select>
                </div>
                <div class="pt-4 flex gap-3 border-t border-gray-100">
                    <button type="button" onclick="closeModal('editUserModal')" class="flex-1 bg-gray-100 text-gray-700 border border-gray-300 px-4 py-2.5 rounded-lg font-bold hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-md">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="resetPassModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-900 text-lg">Reset Password</h3>
                <button onclick="closeModal('resetPassModal')" class="text-gray-400 hover:text-red-500 transition"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            <form action="users_action.php?<?php echo http_build_query($_GET); ?>" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="bg-orange-50 border border-orange-200 p-3 rounded-lg mb-4">
                    <p class="text-xs text-orange-600 font-bold">Resetting password for <strong id="reset_username" class="text-gray-900"></strong>. They will be logged out of current sessions.</p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">New Password <span class="text-red-500">*</span></label>
                    <input type="password" name="new_password" required minlength="6" placeholder="Enter at least 6 characters" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div class="pt-4 flex gap-3 border-t border-gray-100">
                    <button type="button" onclick="closeModal('resetPassModal')" class="flex-1 bg-gray-100 text-gray-700 border border-gray-300 px-4 py-2.5 rounded-lg font-bold hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-orange-500 text-white px-4 py-2.5 rounded-lg font-bold hover:bg-orange-600 transition shadow-md">Force Reset</button>
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
                title: 'Delete this user?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#4b5563',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm_' + id).submit();
                }
            })
        }
        
        function confirmDeleteMobile(id) {
            Swal.fire({
                title: 'Delete this user?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#4b5563',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteFormMobile_' + id).submit();
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

        // Modal Logic
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Populate Edit Modal Data
        function openEditModal(id, username, email, role) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            openModal('editUserModal');
        }

        // Populate Reset Password Modal Data
        function openResetModal(id, username) {
            document.getElementById('reset_user_id').value = id;
            document.getElementById('reset_username').innerText = username;
            openModal('resetPassModal');
        }
    </script>
</body>
</html>