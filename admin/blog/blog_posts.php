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

// SweetAlert Session Messages
$message = '';
$messageType = '';
if (isset($_SESSION['swal_msg'])) {
    $message = $_SESSION['swal_msg'];
    $messageType = $_SESSION['swal_type'];
    unset($_SESSION['swal_msg'], $_SESSION['swal_type']);
}

// Fetch all posts with author and categories using a JOIN and GROUP_CONCAT
try {
    $sql = "SELECT p.*, u.username as author_name, 
            GROUP_CONCAT(c.name SEPARATOR ', ') as cat_names 
            FROM blog_posts p 
            LEFT JOIN users u ON p.author_id = u.id 
            LEFT JOIN blog_post_categories bpc ON p.id = bpc.post_id 
            LEFT JOIN blog_categories c ON bpc.category_id = c.id 
            GROUP BY p.id 
            ORDER BY p.created_at DESC";
    $posts = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $posts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Blog Posts - Admin Panel</title>
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
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Blog Posts</h1>
            </div>
            <a href="add_post.php" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition shadow-md flex items-center gap-2 whitespace-nowrap">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Write New Post
            </a>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">

            <?php if($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType == 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?> font-bold shadow-sm">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Desktop Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden desktop-table">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                            <th class="px-6 py-4 font-bold w-20">Image</th>
                            <th class="px-6 py-4 font-bold">Title</th>
                            <th class="px-6 py-4 font-bold">Categories</th>
                            <th class="px-6 py-4 font-bold text-center">Status</th>
                            <th class="px-6 py-4 font-bold">Date</th>
                            <th class="px-6 py-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php if(empty($posts)): ?>
                            <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 font-medium">No blog posts found. Click "Write New Post" to start.</td></tr>
                        <?php else: ?>
                            <?php foreach($posts as $p): ?>
                                <tr class="hover:bg-gray-50 transition group">
                                    <td class="px-6 py-4">
                                        <?php if($p['featured_image']): ?>
                                            <img src="/<?php echo htmlspecialchars($p['featured_image']); ?>" class="w-14 h-14 object-cover rounded-lg border border-gray-200 shadow-sm">
                                        <?php else: ?>
                                            <div class="w-14 h-14 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-200 text-gray-400">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="add_post.php?edit=<?php echo $p['id']; ?>" class="font-bold text-gray-900 text-base hover:text-blue-600 transition block line-clamp-1 mb-0.5">
                                            <?php echo htmlspecialchars($p['title']); ?>
                                        </a>
                                        <p class="text-[11px] text-gray-400 font-mono">/blog/<?php echo htmlspecialchars($p['slug']); ?></p>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-blue-600 line-clamp-2">
                                        <?php echo $p['cat_names'] ? htmlspecialchars($p['cat_names']) : '<span class="text-gray-400 italic">Uncategorized</span>'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if($p['status'] == 'published'): ?>
                                            <span class="px-2.5 py-1 border rounded-md text-[10px] font-bold uppercase tracking-wider bg-green-50 text-green-600 border-green-200">Published</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 border rounded-md text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600 border-gray-300">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-xs font-bold text-gray-900"><?php echo date('M d, Y', strtotime($p['published_at'])); ?></p>
                                        <p class="text-[10px] text-gray-500 font-medium">By <?php echo htmlspecialchars($p['author_name']); ?></p>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2 items-center opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a href="add_post.php?edit=<?php echo $p['id']; ?>" class="text-xs bg-gray-50 text-gray-600 hover:bg-gray-200 border border-gray-200 px-3 py-1.5 rounded font-bold transition">Edit</a>
                                            <form action="post_action.php" method="POST" class="inline" id="deleteForm_<?php echo $p['id']; ?>">
                                                <input type="hidden" name="action" value="delete_post">
                                                <input type="hidden" name="post_id" value="<?php echo $p['id']; ?>">
                                                <button type="button" onclick="confirmDelete(<?php echo $p['id']; ?>)" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-100 px-3 py-1.5 rounded font-bold transition">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="mobile-card-grid pb-10">
                <?php if(empty($posts)): ?>
                    <div class="bg-white border border-gray-200 rounded-xl p-8 text-center shadow-sm text-gray-500 font-medium">No blog posts found.</div>
                <?php else: ?>
                    <?php foreach($posts as $p): ?>
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm flex flex-col gap-4">
                            <div class="flex items-center gap-4 border-b border-gray-100 pb-4">
                                <?php if($p['featured_image']): ?>
                                    <img src="/<?php echo htmlspecialchars($p['featured_image']); ?>" class="w-16 h-16 object-cover rounded-xl border border-gray-200 shadow-sm shrink-0">
                                <?php else: ?>
                                    <div class="w-16 h-16 bg-gray-50 rounded-xl flex items-center justify-center border border-gray-200 text-gray-400 shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-sm line-clamp-2 leading-tight mb-1"><?php echo htmlspecialchars($p['title']); ?></h3>
                                    <?php if($p['status'] == 'published'): ?>
                                        <span class="px-2 py-0.5 border rounded text-[9px] font-bold uppercase tracking-wider bg-green-50 text-green-600 border-green-200">Published</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 border rounded text-[9px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600 border-gray-300">Draft</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-medium text-blue-600 truncate mr-2"><?php echo $p['cat_names'] ?: 'Uncategorized'; ?></span>
                                <span class="text-gray-500 font-bold whitespace-nowrap"><?php echo date('M d', strtotime($p['published_at'])); ?></span>
                            </div>

                            <div class="flex gap-2 pt-2 border-t border-gray-100">
                                <a href="add_post.php?edit=<?php echo $p['id']; ?>" class="flex-1 text-center text-xs bg-gray-50 text-gray-700 hover:bg-gray-200 border border-gray-300 px-3 py-2.5 rounded-lg font-bold transition">Edit</a>
                                <form action="post_action.php" method="POST" id="deleteFormMobile_<?php echo $p['id']; ?>" class="flex-1">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?php echo $p['id']; ?>">
                                    <button type="button" onclick="confirmDeleteMobile(<?php echo $p['id']; ?>)" class="w-full text-center text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-3 py-2.5 rounded-lg font-bold transition">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        // SweetAlert Delete Confirmation
        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete this post?',
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
                title: 'Delete this post?',
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
    </script>
</body>
</html>