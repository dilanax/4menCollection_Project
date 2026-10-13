<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'shop_admin'])) {
    header("Location: /login");
    exit;
}

require_once '../../config/db.php';

// ==========================================
// ZOHO SMTP EMAIL DISPATCH (PORT 587 + STARTTLS SOCKET)
// ==========================================
function sendRestockAlertMail($pdo, $toEmail, $customerName, $bookTitle, $bookUrl) {
    if (empty($toEmail)) return false;

    $stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $s = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (!$s || empty($s['smtp_email']) || empty($s['smtp_app_token'])) return false;

    $business_name = !empty($s['business_name']) ? $s['business_name'] : 'ReadersHub';
    $fromName = !empty($s['from_name']) ? $s['from_name'] : $business_name;
    $subject = "Good News! '{$bookTitle}' is Back in Stock - {$business_name}";

    $logoHtml = '';
    if (!empty($s['business_logo'])) {
        $logoUrl = rtrim($s['website_url'] ?? '', '/') . '/' . ltrim($s['business_logo'], '/');
        $logoHtml = '<img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($business_name) . '" style="max-height: 48px; margin-bottom: 12px; display: inline-block;">';
    }

    $fullBody = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
        .email-wrapper { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .email-header { background: #0369a1; padding: 28px 24px; text-align: center; color: #ffffff; }
        .email-body { padding: 30px 24px; font-size: 14px; line-height: 1.6; color: #334155; }
        .email-footer { background: #f8fafc; padding: 18px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            ' . $logoHtml . '
            <h2 style="margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">' . htmlspecialchars($business_name) . '</h2>
        </div>
        <div class="email-body">
            <p>Hello <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
            <p>Great news! The book you requested / pre-ordered <strong>"' . htmlspecialchars($bookTitle) . '"</strong> is now back in stock and ready for delivery!</p>
            <div style="margin: 24px 0;">
                <a href="' . htmlspecialchars($bookUrl) . '" style="background:#0369a1; color:#ffffff; padding:12px 26px; border-radius:12px; font-weight:bold; text-decoration:none; display:inline-block; font-size:13px;">Order Your Copy Now &rarr;</a>
            </div>
            <p style="font-size:12px; color:#64748b; line-height:1.5;">Stock is limited on this restock batch. Order early to secure your copy!</p>
        </div>
        <div class="email-footer">
            <p style="margin: 0 0 4px;">' . htmlspecialchars($business_name) . ' | ' . htmlspecialchars($s['phone_number'] ?? '') . '</p>
            <p style="margin: 0;">WhatsApp: ' . htmlspecialchars($s['whatsapp_number'] ?? '') . ' | ' . htmlspecialchars($s['address'] ?? '') . '</p>
        </div>
    </div>
</body>
</html>';

    $host = !empty($s['smtp_host']) ? trim($s['smtp_host']) : 'smtp.zoho.com';
    $port = !empty($s['smtp_port']) ? (int)$s['smtp_port'] : 587;
    $username = trim($s['smtp_email']);
    $password = trim($s['smtp_app_token']);

    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $fullBody);
    $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

    $domain = (strpos($username, '@') !== false) ? explode('@', $username)[1] : 'readershub.lk';
    $messageId = "<" . time() . "." . md5(uniqid(rand(), true)) . "@" . $domain . ">";

    $headers = [
        "Date: " . date("r"),
        "To: <{$toEmail}>",
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$username}>",
        "Reply-To: <{$username}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "Message-ID: {$messageId}",
        "X-Mailer: ReadersHub PHP Mailer",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit"
    ];

    $emailData = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;

    $read = function($socket) {
        $resp = "";
        while ($str = fgets($socket, 515)) {
            $resp .= $str;
            if (substr($str, 3, 1) === " ") break;
        }
        return $resp;
    };

    try {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 12);
        if (!$socket) return false;

        $read($socket);
        fputs($socket, "EHLO readershub.lk\r\n"); $read($socket);
        fputs($socket, "STARTTLS\r\n");
        $tls = $read($socket);
        if (strpos($tls, '220') === false) { fclose($socket); return false; }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        fputs($socket, "EHLO readershub.lk\r\n"); $read($socket);
        fputs($socket, "AUTH LOGIN\r\n"); $read($socket);
        fputs($socket, base64_encode($username) . "\r\n"); $read($socket);
        fputs($socket, base64_encode($password) . "\r\n");
        $auth = $read($socket);
        if (strpos($auth, '235') === false) { fclose($socket); return false; }

        fputs($socket, "MAIL FROM:<{$username}>\r\n"); $read($socket);
        fputs($socket, "RCPT TO:<{$toEmail}>\r\n"); $read($socket);
        fputs($socket, "DATA\r\n"); $read($socket);
        fputs($socket, $emailData . "\r\n.\r\n");
        $sent = $read($socket);
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return (strpos($sent, '250') !== false);
    } catch (Exception $e) {
        return false;
    }
}

// Action: Manual Send Notification Trigger
$msg = '';
$msgType = 'success';
if (isset($_GET['action']) && $_GET['action'] === 'notify' && isset($_GET['preorder_id'])) {
    $p_id = (int)$_GET['preorder_id'];
    $stmt = $pdo->prepare("SELECT po.*, p.name as product_name, p.slug FROM product_preorders po JOIN products p ON po.product_id = p.id WHERE po.id = ? LIMIT 1");
    $stmt->execute([$p_id]);
    $row = $stmt->fetch();

    if ($row) {
        // Fetch Website URL from email_settings dynamically
        $set_stmt = $pdo->query("SELECT website_url FROM email_settings WHERE id = 1 LIMIT 1");
        $siteUrl = $set_stmt ? rtrim($set_stmt->fetchColumn() ?: 'https://readershub.lk', '/') : 'https://readershub.lk';
        
        $bookUrl = $siteUrl . "/product/" . ($row['slug'] ?: $row['product_id']);
        
        if (sendRestockAlertMail($pdo, $row['customer_email'], $row['customer_name'], $row['product_name'], $bookUrl)) {
            $pdo->prepare("UPDATE product_preorders SET status = 'notified', notified_at = NOW() WHERE id = ?")->execute([$p_id]);
            $msg = "Restock notification email sent to " . htmlspecialchars($row['customer_email']) . " successfully!";
            $msgType = 'success';
        } else {
            $msg = "Error sending email. Please verify Zoho SMTP settings.";
            $msgType = 'error';
        }
    }
}

// Fetch All Preorders
$preorders = $pdo->query("
    SELECT po.*, p.name as product_name, p.main_image, p.stock_quantity, p.slug
    FROM product_preorders po
    JOIN products p ON po.product_id = p.id
    ORDER BY po.status ASC, po.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Pre-Orders | ReadersHub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-slate-800">

    <?php 
    if (file_exists('../../sidebar.php')) { require_once '../../sidebar.php'; } 
    elseif (file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">
        <header class="bg-white/95 border-b border-slate-200 px-6 py-4 flex justify-between items-center z-10 sticky top-0">
            <div>
                <h1 class="text-xl font-black text-slate-900">Book Pre-Orders & Restock Alerts</h1>
                <p class="text-xs text-slate-400 mt-0.5">Customers waiting for out-of-stock titles to arrive</p>
            </div>
            <span class="text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200 px-3.5 py-1.5 rounded-xl">
                Total Requests: <?= count($preorders); ?>
            </span>
        </header>

        <div class="flex-1 overflow-y-auto p-6 lg:p-8">
            <?php if ($msg): ?>
                <div class="mb-6 p-4 rounded-2xl border text-xs font-bold <?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'; ?>">
                    <?= htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-black">
                                <th class="py-3.5 px-5">Requested Book</th>
                                <th class="py-3.5 px-5">Customer Details</th>
                                <th class="py-3.5 px-5 text-center">Current Stock</th>
                                <th class="py-3.5 px-5 text-center">Status</th>
                                <th class="py-3.5 px-5">Requested Date</th>
                                <th class="py-3.5 px-5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($preorders)): ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400">No customer pre-order requests at the moment.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($preorders as $row): 
                                    $in_stock = (int)$row['stock_quantity'] > 0;
                                ?>
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-4 px-5 font-bold text-slate-900">
                                            <a href="/product/<?= $row['slug'] ?: $row['product_id']; ?>" target="_blank" class="hover:text-sky-700 flex items-center gap-2">
                                                <?= htmlspecialchars($row['product_name']); ?>
                                                <span class="text-[10px] text-slate-400">&nearr;</span>
                                            </a>
                                        </td>
                                        <td class="py-4 px-5">
                                            <p class="font-bold text-slate-800"><?= htmlspecialchars($row['customer_name']); ?></p>
                                            <p class="text-[11px] text-sky-700"><?= htmlspecialchars($row['customer_email']); ?></p>
                                            <?php if ($row['customer_phone']): ?>
                                                <p class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($row['customer_phone']); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-5 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-black <?= $in_stock ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                                                <?= (int)$row['stock_quantity']; ?> in stock
                                            </span>
                                        </td>
                                        <td class="py-4 px-5 text-center">
                                            <?php if ($row['status'] === 'notified'): ?>
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">Notified</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Waiting</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-5 text-slate-400 font-medium">
                                            <?= date('M d, Y', strtotime($row['created_at'])); ?>
                                        </td>
                                        <td class="py-4 px-5 text-right">
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <a href="preorders.php?action=notify&preorder_id=<?= $row['id']; ?>" class="inline-flex items-center gap-1.5 bg-sky-700 hover:bg-sky-800 text-white font-bold px-3 py-1.5 rounded-xl transition text-[11px] shadow-xs active:scale-98">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                    Notify Customer
                                                </a>
                                            <?php else: ?>
                                                <span class="text-[10px] text-slate-400 italic">Notified on <?= date('M d', strtotime($row['notified_at'])); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>