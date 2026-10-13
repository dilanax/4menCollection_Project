<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) { header("Location: /login"); exit; }
require_once '../../config/db.php';

$message = $_SESSION['swal_msg'] ?? '';
$messageType = $_SESSION['swal_type'] ?? '';
unset($_SESSION['swal_msg'], $_SESSION['swal_type']);

$edit_mode = false;
$post = null;
$selected_cats = [];

if (isset($_GET['edit'])) {
    $edit_mode = true;
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $post = $stmt->fetch();
    
    $cat_stmt = $pdo->prepare("SELECT category_id FROM blog_post_categories WHERE post_id = ?");
    $cat_stmt->execute([$post['id']]);
    $selected_cats = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
}

$categories = $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC")->fetchAll();

function renderCatTree($elements, $parentId = 0, $selected, $level = 0) {
    foreach ($elements as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $margin = $level * 1.5; 
            $checked = in_array($cat['id'], $selected) ? 'checked' : '';
            echo '<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mt-1 font-medium" style="margin-left: '.$margin.'rem;">';
            echo '<input type="checkbox" name="categories[]" value="'.$cat['id'].'" '.$checked.' class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">';
            echo htmlspecialchars($cat['name']).'</label>';
            renderCatTree($elements, $cat['id'], $selected, $level + 1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? 'Edit Post' : 'Add New Post'; ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Quill.js Editor CDN (No API Key Required) -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        
        /* Customizing Quill to fit our theme */
        .ql-toolbar.ql-snow { border: 1px solid #e5e7eb; border-top-left-radius: 1rem; border-top-right-radius: 1rem; background-color: #f9fafb; padding: 12px; }
        .ql-container.ql-snow { border: 1px solid #e5e7eb; border-bottom-left-radius: 1rem; border-bottom-right-radius: 1rem; background-color: #ffffff; }
        .ql-editor { min-height: 400px; font-family: 'Poppins', sans-serif; font-size: 15px; color: #374151; line-height: 1.7; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">
    <?php if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } ?>
    
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">
        <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4 flex justify-between items-center z-10 sticky top-0">
            <h1 class="text-2xl font-bold text-gray-900"><?php echo $edit_mode ? 'Edit Post' : 'Add New Post'; ?></h1>
            <a href="blog_posts.php" class="text-sm font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg transition shadow-sm">Back to Posts</a>
        </header>

        <div class="flex-1 overflow-y-auto p-6">
            <form action="post_action.php" method="POST" id="postForm" enctype="multipart/form-data" class="flex flex-col lg:flex-row gap-6">
                <input type="hidden" name="action" value="save_post">
                <?php if($edit_mode): ?><input type="hidden" name="post_id" value="<?php echo $post['id']; ?>"><?php endif; ?>

                <!-- Left Column: Content -->
                <div class="w-full lg:w-8/12 space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <input type="text" name="title" id="post_title" required value="<?php echo $edit_mode ? htmlspecialchars($post['title']) : ''; ?>" placeholder="Add title" class="w-full bg-transparent text-3xl font-black text-gray-900 placeholder-gray-300 border-none focus:ring-0 px-0 py-2 mb-2 outline-none">
                        <div class="flex items-center text-sm text-gray-500 font-medium">
                            <span>Permalink: /blog/</span>
                            <input type="text" name="slug" id="post_slug" value="<?php echo $edit_mode ? htmlspecialchars($post['slug']) : ''; ?>" class="bg-transparent border-none focus:ring-0 text-blue-600 p-0 ml-1 w-full font-mono text-sm outline-none">
                        </div>
                    </div>

                    <!-- Quill Editor Container -->
                    <div class="bg-white rounded-2xl shadow-sm border border-transparent overflow-hidden">
                        <!-- Hidden textarea to store the actual HTML content for PHP -->
                        <textarea name="content" id="hidden_content" class="hidden"><?php echo $edit_mode ? htmlspecialchars($post['content']) : ''; ?></textarea>
                        
                        <!-- This div becomes the Quill Editor -->
                        <div id="quill_editor"><?php echo $edit_mode ? $post['content'] : ''; ?></div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <h3 class="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Excerpt</h3>
                        <textarea name="excerpt" rows="3" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Small summary of the post..."><?php echo $edit_mode ? htmlspecialchars($post['excerpt']) : ''; ?></textarea>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider">SEO Settings</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">SEO Title</label>
                                <input type="text" name="seo_title" value="<?php echo $edit_mode ? htmlspecialchars($post['seo_title']) : ''; ?>" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Meta Description</label>
                                <textarea name="seo_desc" rows="2" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 outline-none"><?php echo $edit_mode ? htmlspecialchars($post['seo_desc']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Settings -->
                <div class="w-full lg:w-4/12 space-y-6">
                    <!-- Removed sticky classes here -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Publish</h3>
                        <div class="mb-4">
                            <label class="text-sm font-bold text-gray-700 block mb-1">Publish Date</label>
                            <input type="datetime-local" name="published_at" value="<?php echo $edit_mode ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : date('Y-m-d\TH:i'); ?>" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none">
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="submit" name="save_draft" class="w-1/2 bg-white text-gray-700 font-bold py-2.5 border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition">Save Draft</button>
                            <button type="submit" name="publish" class="w-1/2 bg-blue-600 text-white font-bold py-2.5 rounded-lg shadow-md hover:bg-blue-700 transition">Publish</button>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <h3 class="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Categories</h3>
                        <div class="max-h-48 overflow-y-auto custom-scrollbar bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <?php renderCatTree($categories, 0, $selected_cats); ?>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <h3 class="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Tags</h3>
                        <input type="text" name="tags" value="<?php echo $edit_mode ? htmlspecialchars($post['tags']) : ''; ?>" placeholder="news, updates, tips" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none">
                        <p class="text-[10px] font-bold text-gray-500 mt-1.5">Separate with commas</p>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <h3 class="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider border-b border-gray-100 pb-2">Featured Image</h3>
                        <?php if($edit_mode && $post['featured_image']): ?>
                            <img src="/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-40 object-cover rounded-lg mb-4 border border-gray-200 shadow-sm">
                        <?php endif; ?>
                        <input type="hidden" name="existing_image" value="<?php echo $edit_mode ? htmlspecialchars($post['featured_image']) : ''; ?>">
                        <input type="file" name="featured_image" accept="image/jpeg, image/png, image/webp" class="w-full text-xs text-gray-600 file:mr-2 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 file:font-bold hover:file:bg-blue-100 cursor-pointer bg-gray-50 border border-gray-200 p-1 rounded-lg">
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        // SweetAlert 
        <?php if($message): ?> 
            Swal.fire({ toast: true, position: 'top-end', icon: '<?php echo $messageType; ?>', title: '<?php echo addslashes($message); ?>', showConfirmButton: false, timer: 3000 }); 
        <?php endif; ?>
        
        // Auto-generate Slug from Title
        document.getElementById('post_title').addEventListener('keyup', function() {
            if(!<?php echo $edit_mode ? 'true' : 'false'; ?>) {
                document.getElementById('post_slug').value = this.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            }
        });

        // Initialize Quill Editor
        var quill = new Quill('#quill_editor', {
            theme: 'snow',
            placeholder: 'Start writing your amazing post here...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            }
        });

        // Sync Quill HTML content to the hidden textarea before form submission
        var form = document.getElementById('postForm');
        form.onsubmit = function() {
            var hiddenContent = document.getElementById('hidden_content');
            // Populate hidden input with HTML content
            hiddenContent.value = quill.root.innerHTML;
            
            // Check if it's practically empty (Quill leaves <p><br></p> when empty)
            if (hiddenContent.value === '<p><br></p>') {
                hiddenContent.value = '';
            }
            return true;
        };
        
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