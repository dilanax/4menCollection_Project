<?php
// දැනට ඉන්න පිටුව කුමක්දැයි හඳුනා ගැනීම (Highlight කිරීම සඳහා)
$current_path = $_SERVER['REQUEST_URI'];

// Pending Pre-orders count එක dynamic ලෙස ලබා ගැනීම
$pending_preorder_count = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $po_cnt_stmt = $pdo->query("SELECT COUNT(*) FROM product_preorders WHERE status = 'pending'");
        if ($po_cnt_stmt) {
            $pending_preorder_count = (int)$po_cnt_stmt->fetchColumn();
        }
    } catch (Throwable $e) {
        $pending_preorder_count = 0;
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
    
    /* Base Transition Styles */
    .sidebar-transition {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Collapsed State Styles (Desktop) */
    .sidebar-collapsed {
        width: 5rem !important; /* w-20 equivalent */
    }
    .sidebar-collapsed .nav-text {
        opacity: 0;
        visibility: hidden;
        width: 0;
        margin-left: 0;
    }
    .sidebar-collapsed .nav-badge {
        display: none !important;
    }
    .sidebar-collapsed .nav-icon {
        margin-right: 0;
        margin-left: auto;
        margin-right: auto;
    }
    .sidebar-collapsed .logo-text {
        opacity: 0;
        visibility: hidden;
        width: 0;
    }
    .sidebar-collapsed .logo-icon {
        display: block !important;
    }
    .sidebar-collapsed .nav-item {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }
    .sidebar-collapsed .section-title {
        opacity: 0;
        height: 0;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }

    /* Custom Scrollbar for Sidebar */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #555; }
    
    /* Ensure tooltips show on hover when collapsed */
    .sidebar-collapsed .nav-item:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 4.5rem;
        background-color: #1f2937;
        color: white;
        padding: 0.375rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
        z-index: 50;
        pointer-events: none;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
</style>

<button id="mobile-menu-open" class="md:hidden fixed top-3 left-4 z-40 bg-white text-gray-800 p-2 rounded-lg shadow-md border border-gray-200 hover:bg-gray-50 focus:outline-none transition">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

<div id="sidebar-overlay" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

<aside id="admin-sidebar" class="bg-[#0a0a0a] border-r border-neutral-900 text-gray-300 w-64 flex flex-col shadow-2xl z-50 h-screen fixed md:relative transform -translate-x-full md:translate-x-0 sidebar-transition flex-shrink-0" style="font-family: 'Poppins', sans-serif;">
    
    <div class="h-16 px-6 border-b border-neutral-900 flex items-center justify-between md:justify-start sidebar-transition shrink-0">
        <div class="flex items-center w-full overflow-hidden">
            <div class="logo-icon hidden text-blue-500 w-8 h-8 rounded bg-blue-900/20 flex items-center justify-center font-black mx-auto">R</div>
            <h2 class="logo-text text-xl font-black text-white tracking-widest flex items-center gap-2 sidebar-transition whitespace-nowrap">
                <span class="text-blue-500 w-8 h-8 rounded bg-blue-900/20 flex items-center justify-center">R</span>
                READERSHUB
            </h2>
        </div>
        
        <button id="mobile-menu-close" class="md:hidden text-gray-500 hover:text-white p-1 rounded focus:outline-none transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
    
    <nav class="flex-1 px-3 py-6 space-y-1.5 overflow-y-auto overflow-x-hidden custom-scrollbar">
        
        <p class="section-title px-4 text-[10px] font-bold text-gray-600 uppercase tracking-widest mb-2 sidebar-transition">Main Menu</p>
        
        <a href="/admin/dashboard/dashboard.php" data-tooltip="Dashboard" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'dashboard.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'dashboard.php') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Dashboard</span>
        </a>

        <!-- Orders Menu -->
        <a href="/admin/orders/orders.php" data-tooltip="Orders" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'orders.php') !== false || strpos($current_path, 'view_order.php') !== false || strpos($current_path, 'create_order.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'orders.php') === false && strpos($current_path, 'view_order.php') === false && strpos($current_path, 'create_order.php') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Orders</span>
        </a>

        <!-- Pre-Orders Menu (With Badge) -->
        <a href="/admin/orders/preorders.php" data-tooltip="Pre-Orders" class="nav-item relative flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'preorders.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <div class="flex items-center">
                <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'preorders.php') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Pre-Orders</span>
            </div>
            <?php if ($pending_preorder_count > 0): ?>
                <span class="nav-badge text-[10px] font-black bg-amber-500 text-black px-2 py-0.5 rounded-full ml-auto">
                    <?= $pending_preorder_count; ?>
                </span>
            <?php endif; ?>
        </a>

        <p class="section-title px-4 text-[10px] font-bold text-gray-600 uppercase tracking-widest mt-6 mb-2 sidebar-transition">Catalog</p>

        <a href="/admin/product/all_product.php" data-tooltip="Products" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, '/product/all_product.php') !== false || strpos($current_path, '/product/add_product.php') !== false || strpos($current_path, '/product/edit_product.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, '/product/') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Products</span>
        </a>

        <a href="/admin/category/add_category.php" data-tooltip="Categories" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'add_category.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'category') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Categories</span>
        </a>

        <a href="/admin/brand/add_brand.php" data-tooltip="Brands" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'add_brand.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'brand') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Brands</span>
        </a>

        <a href="/admin/attribute/add_attribute.php" data-tooltip="Attributes" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'add_attribute.php') !== false || strpos($current_path, 'manage_terms.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'attribute') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Attributes</span>
        </a>

        <p class="section-title px-4 text-[10px] font-bold text-gray-600 uppercase tracking-widest mt-6 mb-2 sidebar-transition">Blog & Content</p>

        <a href="/admin/blog/blog_posts.php" data-tooltip="Blog Posts" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, '/blog/blog_posts.php') !== false || strpos($current_path, '/blog/add_post.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'blog_posts') === false && strpos($current_path, 'add_post') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">All Posts</span>
        </a>

        <a href="/admin/blog/blog_categories.php" data-tooltip="Blog Categories" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'blog_categories.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'blog_categories') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Blog Categories</span>
        </a>

        <p class="section-title px-4 text-[10px] font-bold text-gray-600 uppercase tracking-widest mt-6 mb-2 sidebar-transition">People & System</p>

        <a href="/admin/customer/users/users.php" data-tooltip="Users" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'users.php') !== false || strpos($current_path, 'customers.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'users') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Users</span>
        </a>

        <a href="/admin/gateway/payment_controller.php" data-tooltip="Payments" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'gateway') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'gateway') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Payments</span>
        </a>

        <a href="/admin/settings/shipping_zones.php" data-tooltip="Shipping" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'shipping_zones.php') !== false || strpos($current_path, 'shipping_methods.php') !== false || strpos($current_path, 'shipping_classes.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'shipping') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Shipping</span>
        </a>
        
        <a href="/admin/orders/email_templates.php" data-tooltip="Emails" class="nav-item relative flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo (strpos($current_path, 'email_templates.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-[#1a1a1a] hover:text-white text-gray-400'; ?>">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 <?php echo (strpos($current_path, 'email') === false) ? 'mr-3' : 'mr-3 text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            <span class="nav-text font-semibold text-sm whitespace-nowrap sidebar-transition">Email Templates</span>
        </a>
    </nav>
    
    <div class="px-3 py-4 border-t border-neutral-900 mt-auto shrink-0 flex flex-col gap-2">
        
        <button id="desktop-sidebar-toggle" data-tooltip="Collapse" class="nav-item relative hidden md:flex w-full items-center justify-center px-3 py-2.5 text-sm text-gray-500 hover:text-white hover:bg-[#1a1a1a] rounded-lg transition-all duration-200 cursor-pointer border border-transparent hover:border-neutral-800">
            <svg id="toggle-icon-left" class="nav-icon w-5 h-5 shrink-0 mr-3 sidebar-transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
            <svg id="toggle-icon-right" class="nav-icon w-5 h-5 shrink-0 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
            <span class="nav-text font-semibold whitespace-nowrap sidebar-transition">Collapse Menu</span>
        </button>

        <a href="/logout" data-tooltip="Logout" class="nav-item relative flex items-center justify-center w-full px-3 py-2.5 text-sm text-red-500 bg-red-950/20 hover:bg-red-600 hover:text-white rounded-lg transition-all duration-200 font-bold border border-red-900/30 hover:border-red-600 group">
            <svg class="nav-icon w-5 h-5 shrink-0 transition-all duration-200 mr-2 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            <span class="nav-text whitespace-nowrap sidebar-transition">Logout</span>
        </a>
    </div>
</aside>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const openBtn = document.getElementById('mobile-menu-open');
        const closeBtn = document.getElementById('mobile-menu-close');
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const desktopToggleBtn = document.getElementById('desktop-sidebar-toggle');
        const toggleIconLeft = document.getElementById('toggle-icon-left');
        const toggleIconRight = document.getElementById('toggle-icon-right');

        // --- Mobile Logic (Off-canvas) ---
        function openMobileSidebar() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        }

        function closeMobileSidebar() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }

        if(openBtn) openBtn.addEventListener('click', openMobileSidebar);
        if(closeBtn) closeBtn.addEventListener('click', closeMobileSidebar);
        if(overlay) overlay.addEventListener('click', closeMobileSidebar);

        // --- Desktop Logic (Collapse/Expand) ---
        const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
        
        if (isCollapsed && window.innerWidth >= 768) {
            sidebar.classList.add('sidebar-collapsed');
            toggleIconLeft.classList.add('hidden');
            toggleIconRight.classList.remove('hidden');
            desktopToggleBtn.setAttribute('data-tooltip', 'Expand');
        }

        if (desktopToggleBtn) {
            desktopToggleBtn.addEventListener('click', function() {
                if (window.innerWidth >= 768) {
                    sidebar.classList.toggle('sidebar-collapsed');
                    
                    const currentlyCollapsed = sidebar.classList.contains('sidebar-collapsed');
                    
                    localStorage.setItem('sidebar_collapsed', currentlyCollapsed);
                    
                    if (currentlyCollapsed) {
                        toggleIconLeft.classList.add('hidden');
                        toggleIconRight.classList.remove('hidden');
                        this.setAttribute('data-tooltip', 'Expand');
                    } else {
                        toggleIconLeft.classList.remove('hidden');
                        toggleIconRight.classList.add('hidden');
                        this.setAttribute('data-tooltip', 'Collapse');
                    }
                }
            });
        }
    });
</script>