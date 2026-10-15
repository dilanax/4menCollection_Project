<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login වී සිටී නම් Home වෙත යොමු කිරීම
if (isset($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

require_once __DIR__ . '/../config/db.php'; 

// ==========================================
// ZOHO SMTP EMAIL DISPATCH (PORT 587 + STARTTLS SOCKET)
// ==========================================
function sendResetSuccessZohoMail($pdo, $toEmail, $toName, $subject, $htmlContent) {
    if (empty($toEmail)) return false;

    $stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

    if (!$settings || empty($settings['smtp_email']) || empty($settings['smtp_app_token'])) {
        return false;
    }

    $logoHtml = '';
    if (!empty($settings['business_logo'])) {
        $logoUrl = rtrim($settings['website_url'] ?? '', '/') . '/' . ltrim($settings['business_logo'], '/');
        $logoHtml = '<img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($settings['business_name'] ?? 'ReadersHub') . '" style="max-height: 44px; margin-bottom: 12px; display: inline-block;">';
    }

    $fullBody = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
        .email-wrapper { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .email-header { background: #0369a1; padding: 26px 24px; text-align: center; color: #ffffff; }
        .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #334155; }
        .email-footer { background: #f8fafc; padding: 18px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            ' . $logoHtml . '
            <h2 style="margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">' . htmlspecialchars($settings['business_name'] ?? 'ReadersHub') . '</h2>
        </div>
        <div class="email-body">
            ' . $htmlContent . '
        </div>
        <div class="email-footer">
            <p style="margin: 0 0 4px;">' . htmlspecialchars($settings['business_name'] ?? 'ReadersHub') . ' | ' . htmlspecialchars($settings['phone_number'] ?? '') . '</p>
            <p style="margin: 0;">WhatsApp: ' . htmlspecialchars($settings['whatsapp_number'] ?? '') . ' | ' . htmlspecialchars($settings['address'] ?? '') . '</p>
        </div>
    </div>
</body>
</html>';

    $host = !empty($settings['smtp_host']) ? trim($settings['smtp_host']) : 'smtp.zoho.com';
    $port = !empty($settings['smtp_port']) ? (int)$settings['smtp_port'] : 587;
    $username = trim($settings['smtp_email']);
    $password = trim($settings['smtp_app_token']);
    $fromName = !empty($settings['from_name']) ? trim($settings['from_name']) : 'ReadersHub';

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

$message = '';
$messageType = '';
$token_valid = false;
$user_id = null;

// 1. URL එකෙහි Token එක පරික්ෂා කිරීම
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = "Invalid or missing password reset link.";
    $messageType = "error";
} else {
    // 2. Token එක වලංගුද සහ කල් ඉකුත්වී නැද්දැයි බැලීම
    $current_time = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE reset_token = ? AND reset_token_expire > ? LIMIT 1");
    $stmt->execute([$token, $current_time]);
    $user = $stmt->fetch();

    if ($user) {
        $token_valid = true;
        $user_id = $user['id'];
    } else {
        $message = "This password reset link is invalid or has expired. Please request a new one.";
        $messageType = "error";
    }
}

// 3. නව Password එක Submit වූ විට (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $post_token = $_POST['token'] ?? '';
    $post_user_id = (int)($_POST['user_id'] ?? 0);

    // නැවත වරක් Token එක Verify කිරීම
    $check_stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ? AND reset_token = ? AND reset_token_expire > ? LIMIT 1");
    $check_stmt->execute([$post_user_id, $post_token, date('Y-m-d H:i:s')]);
    $verified_user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verified_user) {
        $message = "Session expired or invalid token. Please request a new reset link.";
        $messageType = "error";
        $token_valid = false;
    } 
    elseif (strlen($new_password) < 8) {
        $message = "New password must be at least 8 characters long.";
        $messageType = "error";
    } 
    elseif ($new_password !== $confirm_password) {
        $message = "Password confirmation does not match.";
        $messageType = "error";
    } 
    else {
        // Password Hash කර Token එක NULL කර දැමීම
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expire = NULL WHERE id = ?");
        
        if ($update_stmt->execute([$hashed_password, $post_user_id])) {

            // Security Confirmation Email යැවීම
            if (!empty($verified_user['email'])) {
                $sub = "Your Password Has Been Changed - ReadersHub";
                $html = "
                    <p>Hello <strong>" . htmlspecialchars($verified_user['username']) . "</strong>,</p>
                    <p>The password for your ReadersHub reader account has been successfully changed.</p>
                    <div style='background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:15px; margin:20px 0; color:#166534; font-size:13px;'>
                        ✅ <strong>Account Security Update:</strong> Password was updated on " . date('Y-m-d H:i:s') . " (Sri Lanka Time).
                    </div>
                    <p style='color:#64748b; font-size:12px;'>If you made this change, you can safely ignore this email.<br>If you did <strong>not</strong> make this change, please contact our support team immediately.</p>
                ";
                sendResetSuccessZohoMail($pdo, $verified_user['email'], $verified_user['username'], $sub, $html);
            }

            $_SESSION['swal_msg'] = "Password reset successfully! You can now log in.";
            $_SESSION['swal_type'] = "success";
            header("Location: /login");
            exit;
        } else {
            $message = "Something went wrong while updating password. Please try again.";
            $messageType = "error";
        }
    }
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script>document.title = "Create New Password | 4MEN COLLECTION";</script>

<div class="min-h-[80vh] flex flex-col justify-center py-12 sm:py-16 px-4 sm:px-6 lg:px-8 relative selection:bg-black selection:text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">
    
    <div class="absolute inset-0 pointer-events-none overflow-hidden -z-10">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-neutral-100 dark:bg-neutral-800/20 rounded-full blur-3xl opacity-70"></div>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <!-- 4MEN COLLECTION Admin-Style Monochrome Logo -->
        <a href="/" class="inline-flex items-center gap-3.5 group mb-6 transition-transform duration-200 hover:scale-[1.02]">
            <div class="w-12 h-12 rounded-2xl bg-black text-white flex items-center justify-center font-black text-base shadow-lg shadow-black/15 shrink-0 tracking-tight border border-black group-hover:bg-neutral-900 transition-colors">
                4M
            </div>
            <div class="flex flex-col text-left leading-tight">
                <span class="text-xl font-black tracking-wider text-neutral-900 dark:text-white uppercase">4MEN</span>
                <span class="text-[10px] font-bold tracking-[0.26em] text-neutral-500 uppercase mt-0.5">COLLECTION</span>
            </div>
        </a>

        <h1 class="text-2xl sm:text-3xl font-black text-neutral-900 dark:text-white tracking-tight">
            Choose New Password
        </h1>
        <p class="mt-2 text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 font-medium">
            <?= $token_valid ? "Enter your new password of at least 8 characters." : "Unable to proceed with reset."; ?>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white dark:bg-neutral-900 py-9 px-6 sm:px-10 rounded-3xl border border-neutral-200 dark:border-neutral-800 shadow-xl shadow-neutral-900/5">
            
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-2xl text-xs sm:text-sm font-semibold flex items-center gap-3 <?= $messageType === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200 border border-rose-200 dark:border-rose-800/60'; ?>">
                    <svg class="w-5 h-5 flex-shrink-0 <?= $messageType === 'success' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="<?= $messageType === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; ?>"/>
                    </svg>
                    <span><?= htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($token_valid): ?>
                <form class="space-y-4" action="/reset-password?token=<?= htmlspecialchars($token); ?>" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$user_id; ?>">

                    <div>
                        <label for="new_password" class="block text-[11px] font-bold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider mb-2">
                            New Password
                        </label>
                        <div class="relative">
                            <input 
                                id="new_password" 
                                name="new_password" 
                                type="password" 
                                required 
                                minlength="8" 
                                placeholder="At least 8 characters"
                                class="w-full pl-11 pr-4 py-3.5 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                            >
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                                <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-[11px] font-bold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider mb-2">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <input 
                                id="confirm_password" 
                                name="confirm_password" 
                                type="password" 
                                required 
                                minlength="8" 
                                placeholder="Re-enter password"
                                class="w-full pl-11 pr-4 py-3.5 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                            >
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                                <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button 
                            type="submit" 
                            class="w-full py-4 px-5 rounded-2xl text-xs sm:text-sm font-extrabold uppercase tracking-wider text-white bg-black hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition shadow-lg shadow-black/15 hover:shadow-xl active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>Save & Update Password</span>
                            <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-2">
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-5">This password reset link is invalid or has expired.</p>
                    <a href="/forgot-password" class="inline-block w-full py-3.5 px-4 rounded-2xl text-xs font-bold uppercase tracking-wider text-white bg-black hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition shadow-md">
                        Request New Reset Link
                    </a>
                </div>
            <?php endif; ?>

            <div class="mt-7 text-center pt-6 border-t border-neutral-100 dark:border-neutral-800">
                <a href="/login" class="text-xs font-bold text-neutral-500 dark:text-neutral-400 hover:text-black dark:hover:text-white transition inline-flex items-center gap-1.5 hover:underline">
                    <span>&larr; Return to Sign In</span>
                </a>
            </div>
        </div>
    </div>
</div>