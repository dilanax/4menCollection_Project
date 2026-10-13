<?php
// Ensure database connection is loaded
require_once 'config/db.php'; // Adjust path based on how your router loads this file

// Fetch ONLY 'published' posts for the frontend
try {
    $sql = "SELECT p.*, u.username as author_name, 
            GROUP_CONCAT(c.name SEPARATOR ', ') as cat_names 
            FROM blog_posts p 
            LEFT JOIN users u ON p.author_id = u.id 
            LEFT JOIN blog_post_categories bpc ON p.id = bpc.post_id 
            LEFT JOIN blog_categories c ON bpc.category_id = c.id 
            WHERE p.status = 'published' AND p.published_at <= NOW()
            GROUP BY p.id 
            ORDER BY p.published_at DESC";
    
    $stmt = $pdo->query($sql);
    $published_posts = $stmt->fetchAll();
} catch (Exception $e) {
    $published_posts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Blog | E-Commerce Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- INSERT YOUR HEADER HERE (e.g., include 'header.php') -->

    <main class="flex-grow">
        
        <!-- Blog Header Section -->
        <section class="bg-white border-b border-gray-200 py-16 lg:py-20 text-center">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-4xl md:text-5xl font-black text-gray-900 tracking-tight mb-4">Latest News & Updates</h1>
                <p class="text-lg text-gray-500 max-w-2xl mx-auto font-medium">Discover our latest products, tech news, and shopping guides.</p>
            </div>
        </section>

        <!-- Blog Posts Grid -->
        <section class="py-12 lg:py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <?php if(empty($published_posts)): ?>
                    <div class="text-center py-20 bg-white rounded-2xl border border-gray-200 shadow-sm">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Posts Yet</h3>
                        <p class="text-gray-500 font-medium">Check back later for exciting updates and articles!</p>
                    </div>
                <?php else: ?>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php foreach($published_posts as $post): ?>
                            <!-- Include the reusable card component -->
                            <?php include 'card.php'; ?>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

            </div>
        </section>

    </main>

    <!-- INSERT YOUR FOOTER HERE (e.g., include 'footer.php') -->

</body>
</html>