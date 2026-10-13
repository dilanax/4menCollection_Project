<?php
// Include DB fallback if needed
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    } elseif (file_exists('config/db.php')) {
        require_once 'config/db.php';
    }
}

// Start session for CSRF & flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch Business Contact Info from email_settings
$settings = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $st = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
        if ($st) {
            $settings = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (Throwable $e) {
    $settings = [];
}

$business_phone   = !empty($settings['phone_number']) ? $settings['phone_number'] : '+94 75 188 3565';
$business_wa      = !empty($settings['whatsapp_number']) ? $settings['whatsapp_number'] : '0751883565';
$business_email   = !empty($settings['smtp_email']) ? $settings['smtp_email'] : 'info@loopzglobal.com';
$business_address = !empty($settings['address']) ? $settings['address'] : 'Divithurage Watta, Walpita South';
$admin_alert_mail = !empty($settings['admin_alert_email']) ? $settings['admin_alert_email'] : $business_email;

// Helper function: Zoho Mail Dispatch
function sendContactZohoMail($pdo, $toEmail, $toName, $subject, $htmlContent) {
    if (empty($toEmail)) return false;

    $stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $s = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$s || empty($s['smtp_email']) || empty($s['smtp_app_token'])) {
        return false;
    }

    $fullBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
            .email-wrapper { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
            .email-header { background: #0369a1; padding: 24px; text-align: center; color: #ffffff; }
            .email-body { padding: 24px; font-size: 13px; line-height: 1.6; color: #334155; }
            .email-footer { background: #f8fafc; padding: 16px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-header">
                <h2 style="margin: 0; font-size: 18px; font-weight: 800;">' . htmlspecialchars($s['business_name'] ?? 'ReadersHub') . '</h2>
            </div>
            <div class="email-body">
                ' . $htmlContent . '
            </div>
            <div class="email-footer">
                <p style="margin: 0;">' . htmlspecialchars($s['business_name'] ?? 'ReadersHub') . ' | ' . htmlspecialchars($s['phone_number'] ?? '') . '</p>
            </div>
        </div>
    </body>
    </html>';

    $host = $s['smtp_host'] ?: 'smtp.zoho.com';
    $port = (int)($s['smtp_port'] ?: 465);
    $username = trim($s['smtp_email']);
    $password = trim($s['smtp_app_token']);
    $fromName = $s['from_name'] ?: 'ReadersHub';

    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: " . "=?UTF-8?B?" . base64_encode($fromName) . "?=" . " <" . $username . ">",
        "To: <" . $toEmail . ">",
        "Subject: " . "=?UTF-8?B?" . base64_encode($subject) . "?=",
        "Date: " . date("r")
    ];
    $emailData = implode("\r\n", $headers) . "\r\n\r\n" . $fullBody;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        $scheme = ($port == 465) ? "smtps" : "smtp";
        curl_setopt($ch, CURLOPT_URL, "{$scheme}://{$host}:{$port}");
        curl_setopt($ch, CURLOPT_USERNAME, $username);
        curl_setopt($ch, CURLOPT_PASSWORD, $password);
        curl_setopt($ch, CURLOPT_MAIL_FROM, "<{$username}>");
        curl_setopt($ch, CURLOPT_MAIL_RCPT, ["<{$toEmail}>"]);
        curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);

        $temp = fopen('php://memory', 'r+');
        fwrite($temp, $emailData);
        rewind($temp);
        curl_setopt($ch, CURLOPT_INFILE, $temp);
        curl_setopt($ch, CURLOPT_UPLOAD, true);

        $res = curl_exec($ch);
        fclose($temp);
        curl_close($ch);
        if ($res) return true;
    }

    return false;
}

$feedback = null;
$feedback_type = '';

// Handle Contact Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name    = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $email   = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $phone   = trim(htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'));
    $subject = trim(htmlspecialchars($_POST['subject'] ?? 'General Inquiry', ENT_QUOTES, 'UTF-8'));
    $message = trim(htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'));

    if (empty($name) || empty($email) || empty($message)) {
        $feedback = 'Please fill in all required fields: name, email, and message.';
        $feedback_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = 'Please enter a valid email address.';
        $feedback_type = 'error';
    } else {
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                // Ensure table exists safely
                $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(150) NOT NULL,
                    email VARCHAR(150) NOT NULL,
                    phone VARCHAR(50) NULL,
                    subject VARCHAR(200) NULL,
                    message TEXT NOT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");

                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $phone, $subject, $message]);

                // Dispatch Confirmation Email to Customer
                $custHtml = "
                    <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p>Thank you for reaching out to us! We have received your inquiry regarding <strong>" . htmlspecialchars($subject) . "</strong>.</p>
                    <div style='background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin:15px 0;'>
                        <p style='margin:0 0 6px; font-size:11px; font-weight:bold; color:#64748b;'>YOUR MESSAGE:</p>
                        <p style='margin:0; font-style:italic;'>" . nl2br(htmlspecialchars($message)) . "</p>
                    </div>
                    <p>Our team is reviewing your message and will get back to you shortly.</p>
                ";
                sendContactZohoMail($pdo, $email, $name, "We've received your message - ReadersHub", $custHtml);

                // Dispatch Alert Email to Admin
                if (!empty($admin_alert_mail)) {
                    $adminHtml = "
                        <p><strong>New Customer Inquiry Received:</strong></p>
                        <p><strong>Name:</strong> " . htmlspecialchars($name) . "<br>
                        <strong>Email:</strong> " . htmlspecialchars($email) . "<br>
                        <strong>Phone:</strong> " . htmlspecialchars($phone ?: 'Not provided') . "<br>
                        <strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                        <div style='background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px;'>
                            " . nl2br(htmlspecialchars($message)) . "
                        </div>
                    ";
                    sendContactZohoMail($pdo, $admin_alert_mail, 'Admin', "New Inquiry: " . $subject, $adminHtml);
                }
            }
            $feedback = 'Thank you! Your message has been sent successfully. Our team will get back to you shortly.';
            $feedback_type = 'success';
        } catch (Throwable $e) {
            $feedback = 'Thank you! Your message has been received. We will respond as soon as possible.';
            $feedback_type = 'success';
        }
    }
}

// WhatsApp Link Formatter
$clean_wa = preg_replace('/[^0-9]/', '', $business_wa);
if (str_starts_with($clean_wa, '0')) {
    $clean_wa = '94' . substr($clean_wa, 1);
}
?>

<div class="w-full bg-[#f8fafc] text-slate-800 py-6 sm:py-10 min-h-screen" style="font-family: 'Poppins', sans-serif;">
    <div class="w-full px-4 sm:px-6 lg:px-8 xl:px-12">
        
        <!-- Header & Breadcrumbs -->
        <div class="mb-8 border-b border-slate-200 pb-5">
            <nav class="flex items-center text-xs font-semibold text-slate-400 mb-2 gap-2">
                <a href="/" class="hover:text-sky-700 transition-colors">Home</a>
                <span>/</span>
                <span class="text-sky-700 font-bold">Contact Us</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">Contact Us</h1>
            <p class="text-slate-500 text-xs sm:text-sm mt-1">
                Have questions about book orders, islandwide delivery, or wholesale requests? Our customer support team is here to assist you.
            </p>
        </div>

        <!-- Main Layout: Cards & Form -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            
            <!-- Left 5 Cols: Quick Contact Cards & Info -->
            <div class="lg:col-span-5 space-y-4">
                
                <!-- Card 1: Phone Support -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center shrink-0 border border-sky-100">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-extrabold text-slate-900">Phone Support</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Monday – Sunday (8:00 AM – 9:00 PM)</p>
                        <div class="mt-2">
                            <a href="tel:<?= htmlspecialchars($business_phone); ?>" class="block text-xs font-bold text-sky-700 hover:underline"><?= htmlspecialchars($business_phone); ?></a>
                        </div>
                    </div>
                </div>

                <!-- Card 2: WhatsApp Chat -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.94-3.138 7.398 7.398 0 01-1.876-4.767c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-extrabold text-slate-900">Instant WhatsApp Support</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Quick assistance for book orders & parcel tracking</p>
                        <a href="https://wa.me/<?= htmlspecialchars($clean_wa); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 hover:text-emerald-700 mt-2">
                            <span>Chat on WhatsApp (<?= htmlspecialchars($business_wa); ?>)</span>
                            <span class="text-sm">&rarr;</span>
                        </a>
                    </div>
                </div>

                <!-- Card 3: Email Support -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center shrink-0 border border-indigo-100">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-extrabold text-slate-900">Email Inquiries</h3>
                        <p class="text-xs text-slate-400 mt-0.5">For general, publisher, and bulk orders</p>
                        <a href="mailto:<?= htmlspecialchars($business_email); ?>" class="block text-xs font-bold text-indigo-700 hover:underline mt-2"><?= htmlspecialchars($business_email); ?></a>
                    </div>
                </div>

                <!-- Card 4: Store / Distribution Hub -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-100">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-extrabold text-slate-900">Main Distribution Hub</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            <?= nl2br(htmlspecialchars($business_address)); ?>
                        </p>
                    </div>
                </div>

            </div>

            <!-- Right 7 Cols: Contact Form -->
            <div class="lg:col-span-7 bg-white p-6 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-xs">
                
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight mb-1">Send Us a Message</h2>
                <p class="text-slate-400 text-xs mb-6">Fill out the form below, and our support team will respond within a few hours.</p>

                <?php if ($feedback): ?>
                    <div class="mb-6 p-4 rounded-xl text-xs font-semibold <?= $feedback_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
                        <?= htmlspecialchars($feedback); ?>
                    </div>
                <?php endif; ?>

                <form action="/contact" method="POST" class="space-y-4">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Your Full Name *</label>
                            <input 
                                type="text" 
                                name="name" 
                                required 
                                placeholder="John Doe" 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition-colors"
                            >
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Phone Number</label>
                            <input 
                                type="tel" 
                                name="phone" 
                                placeholder="+94 77 123 4567" 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition-colors"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Email Address *</label>
                            <input 
                                type="email" 
                                name="email" 
                                required 
                                placeholder="name@example.com" 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition-colors"
                            >
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Inquiry Subject</label>
                            <select name="subject" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition-colors cursor-pointer">
                                <option value="Order Inquiry">Order Inquiry</option>
                                <option value="Delivery Status">Delivery Tracking</option>
                                <option value="Book Request">Request a Book</option>
                                <option value="Wholesale">Wholesale & Bulk Orders</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Message *</label>
                        <textarea 
                            name="message" 
                            rows="5" 
                            required 
                            placeholder="Please provide details about your inquiry or required book titles..." 
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition-colors resize-none"
                        ></textarea>
                    </div>

                    <button 
                        type="submit" 
                        name="send_message" 
                        class="w-full sm:w-auto px-8 py-3.5 bg-sky-700 hover:bg-sky-800 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md shadow-sky-700/20 active:scale-98"
                    >
                        Send Message &rarr;
                    </button>

                </form>

            </div>

        </div>

        <!-- Google Map Embedded Section -->
        <div class="mt-10 w-full overflow-hidden rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-xs h-72 sm:h-96">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126743.58638753232!2d79.786164!3d6.921833!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae253d10f7a7003%3A0x320b2e4d32d3838d!2sColombo!5e0!3m2!1sen!2slk!4v1690000000000!5m2!1sen!2slk" 
                class="w-full h-full border-0" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>

    </div>
</div>