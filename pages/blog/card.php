<?php
// Make sure $post variable is available when this file is included
if (!isset($post)) return;

$thumbnail = !empty($post['featured_image']) ? '/' . htmlspecialchars($post['featured_image']) : '/assets/images/placeholder-blog.png';
$title = htmlspecialchars($post['title']);
$date = date('M d, Y', strtotime($post['published_at']));
$slug = htmlspecialchars($post['slug']);
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col h-full group">
    
    <!-- Thumbnail Image -->
    <a href="/blog/<?php echo $slug; ?>" class="block relative overflow-hidden aspect-video">
        <img src="<?php echo $thumbnail; ?>" alt="<?php echo $title; ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
        
        <!-- Optional Category Badge overlaid on image -->
        <?php if(!empty($post['cat_names'])): ?>
            <span class="absolute top-3 left-3 bg-blue-600/90 backdrop-blur text-white text-[10px] font-bold px-2.5 py-1 rounded uppercase tracking-wider">
                <?php 
                $cats = explode(',', $post['cat_names']); 
                echo htmlspecialchars(trim($cats[0])); // Show primary category
                ?>
            </span>
        <?php endif; ?>
    </a>

    <!-- Card Content -->
    <div class="p-5 flex flex-col flex-1">
        
        <div class="flex items-center text-xs text-gray-400 font-medium mb-3 gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <?php echo $date; ?>
        </div>
        
        <h3 class="text-lg font-bold text-gray-900 leading-tight mb-2 line-clamp-2">
            <a href="/blog/<?php echo $slug; ?>" class="hover:text-blue-600 transition">
                <?php echo $title; ?>
            </a>
        </h3>

        <!-- Excerpt (Optional, but good for SEO/UX) -->
        <?php if(!empty($post['excerpt'])): ?>
            <p class="text-sm text-gray-600 line-clamp-2 mb-4 flex-1">
                <?php echo htmlspecialchars($post['excerpt']); ?>
            </p>
        <?php else: ?>
            <div class="flex-1"></div> <!-- Spacer to keep buttons aligned -->
        <?php endif; ?>

        <!-- Read More Button -->
        <div class="mt-auto pt-4 border-t border-gray-100">
            <a href="/blog/<?php echo $slug; ?>" class="inline-flex items-center text-sm font-bold text-blue-600 hover:text-blue-800 transition group-hover:underline">
                Read Article
                <svg class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </a>
        </div>
        
    </div>
</div>