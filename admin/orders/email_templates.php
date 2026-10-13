<?php
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
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
}

// Fetch Settings Record
try {
    $stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $pdo->query("INSERT INTO email_settings (id, business_name, smtp_host, smtp_port) VALUES (1, 'ReadersHub', 'smtp.zoho.com', 465)");
        $settings = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $settings = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email & Zoho SMTP Configuration | ReadersHub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, input, textarea, select, button { font-family: 'Poppins', sans-serif !important; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-slate-800 antialiased">

    <?php 
    if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">
        
        <header class="bg-white/95 backdrop-blur-md shadow-xs border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3.5 flex justify-between items-center z-10 sticky top-0 gap-3">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-open-header" type="button" class="md:hidden p-2 text-slate-600 hover:text-sky-700 focus:outline-none rounded-xl hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">System Settings</span>
                    <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 tracking-tight">Email & Zoho SMTP Setup</h1>
                </div>
            </div>
            
            <button type="submit" form="settings-form" class="bg-sky-700 hover:bg-sky-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-md shadow-sky-700/20 flex items-center gap-1.5 active:scale-98">
                <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span>Save Settings</span>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            
            <form id="settings-form" action="email_templates_action.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start max-w-7xl mx-auto">
                <input type="hidden" name="action" value="save_settings">

                <!-- Left 7 Columns: Zoho SMTP & Email Configuration -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs">
                        <div class="flex items-center gap-2.5 mb-4 border-b border-slate-100 pb-3">
                            <span class="w-2.5 h-2.5 rounded-full bg-sky-700"></span>
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">Zoho SMTP Server Settings</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">SMTP Host *</label>
                                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.zoho.com'); ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-mono focus:bg-white focus:border-sky-700 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">SMTP Port *</label>
                                <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '465'); ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-mono focus:bg-white focus:border-sky-700 outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Encryption *</label>
                                <select name="smtp_encryption" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none cursor-pointer">
                                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
                                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS / STARTTLS (Port 587)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Sender Name (From Name) *</label>
                                <input type="text" name="from_name" value="<?php echo htmlspecialchars($settings['from_name'] ?? 'ReadersHub'); ?>" required placeholder="e.g. ReadersHub Bookstore" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Zoho Email Address (User / Account) *</label>
                            <input type="email" name="smtp_email" value="<?php echo htmlspecialchars($settings['smtp_email'] ?? ''); ?>" required placeholder="orders@yourdomain.com" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-mono focus:bg-white focus:border-sky-700 outline-none">
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Zoho App-Specific Password / Token *</label>
                            <input type="password" name="smtp_app_token" value="<?php echo htmlspecialchars($settings['smtp_app_token'] ?? ''); ?>" required placeholder="••••••••••••••••" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-mono focus:bg-white focus:border-sky-700 outline-none">
                            <p class="text-[11px] text-slate-400 mt-1">Generate this from Zoho My Account &rarr; Security &rarr; App Passwords.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Admin Notification Email *</label>
                            <input type="email" name="admin_alert_email" value="<?php echo htmlspecialchars($settings['admin_alert_email'] ?? ''); ?>" placeholder="admin@yourdomain.com" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-mono focus:bg-white focus:border-sky-700 outline-none">
                            <p class="text-[11px] text-slate-400 mt-1">New order alert emails will be routed to this address.</p>
                        </div>
                    </div>

                    <!-- Modular Templates Notice -->
                    <div class="bg-sky-50 border border-sky-200/80 p-5 rounded-3xl text-sky-900">
                        <div class="flex items-center gap-2 mb-1.5">
                            <svg class="w-4 h-4 text-sky-700 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h4 class="text-xs font-black uppercase tracking-wider">Modular Email Architecture</h4>
                        </div>
                        <p class="text-xs text-sky-800 leading-relaxed">
                            Database clutter has been eliminated. The actual visual email layouts (Order Confirmation, New Account, Shipped Notification) are now maintained as dedicated clean PHP files under <code>/includes/emails/</code>, dynamically using the business info configured on this screen.
                        </p>
                    </div>

                </div>

                <!-- Right 5 Columns: Business Branding, Contact & Social Links -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs space-y-4">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Business & Branding</h3>
                        
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Company / Store Name *</label>
                            <input type="text" name="business_name" value="<?php echo htmlspecialchars($settings['business_name'] ?? 'ReadersHub'); ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Website URL</label>
                            <input type="url" name="website_url" value="<?php echo htmlspecialchars($settings['website_url'] ?? 'https://readershub.loopzglobal.com'); ?>" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none">
                        </div>

                        <!-- Email Header Logo with Remove Option -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Email Logo</label>
                            <input type="hidden" id="existing_logo" name="existing_logo" value="<?php echo htmlspecialchars($settings['business_logo'] ?? ''); ?>">
                            <input type="hidden" id="remove_logo" name="remove_logo" value="0">

                            <div id="logo_dropzone" class="<?php echo !empty($settings['business_logo']) ? 'hidden' : ''; ?> relative border-2 border-dashed border-slate-200 hover:border-sky-700 rounded-2xl p-4 text-center bg-slate-50/50 hover:bg-sky-50/30 transition-all cursor-pointer">
                                <input type="file" id="logo_input" name="business_logo" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                <div class="flex flex-col items-center justify-center py-1">
                                    <svg class="w-6 h-6 text-sky-700 mb-1 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    <span class="text-xs font-bold text-slate-700">Choose Logo</span>
                                    <span class="text-[10px] text-slate-400">PNG or WEBP with transparent background</span>
                                </div>
                            </div>

                            <div id="logo_preview_container" class="<?php echo empty($settings['business_logo']) ? 'hidden' : ''; ?> mt-2 relative text-center bg-slate-50 p-3 rounded-2xl border border-slate-200">
                                <div class="relative inline-block">
                                    <img id="logo_preview" src="<?php echo !empty($settings['business_logo']) ? '/' . htmlspecialchars($settings['business_logo']) : ''; ?>" class="h-12 w-auto max-w-[180px] object-contain mx-auto">
                                    <button type="button" id="remove_logo_btn" class="absolute -top-2 -right-2 bg-rose-600 hover:bg-rose-700 text-white rounded-full p-1 shadow-md transition active:scale-95" title="Remove logo">
                                        <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Phone Number</label>
                            <input type="text" name="phone_number" value="<?php echo htmlspecialchars($settings['phone_number'] ?? ''); ?>" placeholder="+94 77 123 4567" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">WhatsApp Number</label>
                            <input type="text" name="whatsapp_number" value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? ''); ?>" placeholder="+94 71 987 6543" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Office / Store Address</label>
                            <textarea name="address" rows="2" placeholder="123 Galle Road, Colombo 03, Sri Lanka" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none"><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Social Media Links (Included in Email Footers) -->
                    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xs space-y-4">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Social Media Links</h3>
                        
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Facebook URL</label>
                            <input type="url" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/yourpage" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none font-mono">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Instagram URL</label>
                            <input type="url" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>" placeholder="https://instagram.com/yourhandle" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none font-mono">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Twitter / X URL</label>
                            <input type="url" name="twitter_url" value="<?php echo htmlspecialchars($settings['twitter_url'] ?? ''); ?>" placeholder="https://x.com/yourhandle" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3.5 py-2 text-xs sm:text-sm focus:bg-white focus:border-sky-700 outline-none font-mono">
                        </div>
                    </div>

                </div>
            </form>

        </div>
    </main>

    <script>
        // SweetAlert Flash
        <?php if ($message): ?>
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

        // Logo Upload & Remove Controls
        const logoInput = document.getElementById('logo_input');
        const logoDropzone = document.getElementById('logo_dropzone');
        const logoPreviewContainer = document.getElementById('logo_preview_container');
        const logoPreview = document.getElementById('logo_preview');
        const existingLogo = document.getElementById('existing_logo');
        const removeLogo = document.getElementById('remove_logo');
        const removeLogoBtn = document.getElementById('remove_logo_btn');

        logoInput?.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    logoPreview.src = e.target.result;
                    logoDropzone.classList.add('hidden');
                    logoPreviewContainer.classList.remove('hidden');
                    removeLogo.value = '0';
                };
                reader.readAsDataURL(file);
            }
        });

        removeLogoBtn?.addEventListener('click', function() {
            logoInput.value = '';
            logoPreview.src = '';
            existingLogo.value = '';
            removeLogo.value = '1';
            logoPreviewContainer.classList.add('hidden');
            logoDropzone.classList.remove('hidden');
        });

        // Mobile Sidebar
        const headerOpenBtn = document.getElementById('mobile-menu-open-header');
        if (headerOpenBtn) {
            headerOpenBtn.addEventListener('click', function() {
                const actualOpenBtn = document.getElementById('mobile-menu-open');
                if (actualOpenBtn) actualOpenBtn.click();
            });
        }
    </script>
</body>
</html>