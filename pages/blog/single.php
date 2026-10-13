<?php
// Ensure database connection is loaded
require_once 'config/db.php'; 

// Get the slug from URL (Assuming your router passes it like ?slug=my-post-name)
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    // Redirect to blog home if no slug
    header("Location: /blog");
    exit;
}

try {
    // Fetch the single post
    $sql = "SELECT p.*, u.username as author_name, 
            GROUP_CONCAT(c.name SEPARATOR ', ') as cat_names 
            FROM blog_posts p 
            LEFT JOIN users u ON p.author_id = u.id 
            LEFT JOIN blog_post_categories bpc ON p.id = bpc.post_id 
            LEFT JOIN blog_categories c ON bpc.category_id = c.id 
            WHERE p.slug = ? AND p.status = 'published' AND p.published_at <= NOW()
            GROUP BY p.id";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$slug]);
    $post = $stmt->fetch();

    if (!$post) {
        // If post not found, show 404 or redirect
        header("Location: /404"); // Adjust to your 404 route
        exit;
    }

} catch (Exception $e) {
    die("Database Error");
}

// SEO Details
$page_title = !empty($post['seo_title']) ? $post['seo_title'] : $post['title'];
$meta_desc = !empty($post['seo_desc']) ? $post['seo_desc'] : $post['excerpt'];
$thumbnail = !empty($post['featured_image']) ? '/' . htmlspecialchars($post['featured_image']) : '/assets/images/placeholder-blog.png';
$date = date('F d, Y', strtotime($post['published_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Tags -->
    <title><?php echo htmlspecialchars($page_title); ?> | E-Commerce Store</title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_desc); ?>">
    
    <!-- Open Graph (For Social Media Sharing) -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_desc); ?>">
    <meta property="og:image" content="<?php echo $thumbnail; ?>">
    <meta property="og:type" content="article">

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; }
        
        /* Typography for Rich Text Content (from Quill/TinyMCE) */
        .blog-content h1 { font-size: 2.25rem; font-weight: 800; margin-top: 2rem; margin-bottom: 1rem; color: #111827; }
        .blog-content h2 { font-size: 1.875rem; font-weight: 800; margin-top: 2rem; margin-bottom: 1rem; color: #111827; }
        .blog-content h3 { font-size: 1.5rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.75rem; color: #111827; }
        .blog-content h4 { font-size: 1.25rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.75rem; color: #111827; }
        .blog-content p { margin-bottom: 1.25rem; color: #374151; line-height: 1.8; font-size: 1.05rem;}
        .blog-content a { color: #2563eb; text-decoration: underline; font-weight: 600; }
        .blog-content ul { list-style-type: disc; margin-left: 1.5rem; margin-bottom: 1.5rem; color: #374151; }
        .blog-content ol { list-style-type: decimal; margin-left: 1.5rem; margin-bottom: 1.5rem; color: #374151; }
        .blog-content li { margin-bottom: 0.5rem; }
        .blog-content blockquote { border-left: 4px solid #3b82f6; padding-left: 1rem; font-style: italic; color: #4b5563; margin-bottom: 1.5rem; background: #eff6ff; padding: 1rem; border-radius: 0 0.5rem 0.5rem 0;}
        .blog-content img { max-width: 100%; height: auto; border-radius: 0.75rem; margin: 2rem 0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .blog-content iframe { width: 100%; aspect-ratio: 16/9; border-radius: 0.75rem; margin: 2rem 0; }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- INSERT YOUR HEADER HERE (e.g., include 'header.php') -->

    <main class="flex-grow">
        
        <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20">
            
            <!-- Breadcrumbs -->
            <nav class="flex text-sm text-gray-500 font-medium mb-8">
                <a href="/" class="hover:text-blue-600 transition">Home</a>
                <span class="mx-2">/</span>
                <a href="/blog" class="hover:text-blue-600 transition">Blog</a>
                <span class="mx-2">/</span>
                <span class="text-gray-900 truncate"><?php echo htmlspecialchars($post['title']); ?></span>
            </nav>

            <!-- Post Header -->
            <header class="mb-10 text-center md:text-left">
                <?php if(!empty($post['cat_names'])): ?>
                    <div class="mb-4 flex flex-wrap gap-2 justify-center md:justify-start">
                        <?php 
                        $cats = explode(',', $post['cat_names']);
                        foreach($cats as $c): ?>
                            <span class="bg-blue-50 text-blue-700 text-xs font-bold px-2.5 py-1 rounded uppercase tracking-wider border border-blue-100">
                                <?php echo htmlspecialchars(trim($c)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-gray-900 tracking-tight leading-tight mb-6">
                    <?php echo htmlspecialchars($post['title']); ?>
                </h1>

                <div class="flex items-center justify-center md:justify-start gap-4 text-sm text-gray-500 font-medium">
                    <div class="flex items-center gap-2">
                        <!-- Author Avatar Placeholder -->
                        <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-bold border border-gray-300">
                            <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                        </div>
                        <span class="text-gray-900 font-bold"><?php echo htmlspecialchars($post['author_name']); ?></span>
                    </div>
                    <span>•</span>
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <time datetime="<?php echo date('Y-m-d', strtotime($post['published_at'])); ?>"><?php echo $date; ?></time>
                    </div>
                </div>
            </header>

            <!-- Featured Image -->
            <?php if(!empty($post['featured_image'])): ?>
                <div class="mb-12 rounded-2xl overflow-hidden shadow-lg border border-gray-200 bg-white">
                    <img src="<?php echo $thumbnail; ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-auto max-h-[500px] object-cover">
                </div>
            <?php endif; ?>

            <!-- Post Content (Rich Text) -->
            <div class="blog-content bg-white p-6 sm:p-10 rounded-2xl shadow-sm border border-gray-100">
                <?php 
                    // This outputs the raw HTML saved from Quill/TinyMCE
                    // We DO NOT use htmlspecialchars here so the HTML tags render properly
                    echo $post['content']; 
                ?>
            </div>

            <!-- Tags -->
            <?php if(!empty($post['tags'])): ?>
                <div class="mt-10 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-bold text-gray-900 mr-2">Tags:</span>
                    <?php 
                    $tags = explode(',', $post['tags']);
                    foreach($tags as $tag): ?>
                        <span class="bg-gray-100 text-gray-600 text-xs font-bold px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-200 transition cursor-pointer">
                            #<?php echo htmlspecialchars(trim($tag)); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </article>

    </main>

    <!-- INSERT YOUR FOOTER HERE (e.g., include 'footer.php') -->

</body>
</html>