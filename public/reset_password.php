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

<script>document.title = "Create New Password | ReadersHub";</script>

<div class="min-h-[80vh] flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 bg-[#f8fafc]" style="font-family: 'Poppins', sans-serif;">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="w-12 h-12 bg-sky-50 text-sky-700 border border-sky-200 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-xs">
            <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
        </div>
        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
            Choose New Password
        </h2>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 font-medium">
            <?= $token_valid ? "Enter your new password of at least 8 characters." : "Unable to proceed with reset."; ?>
        </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 sm:px-10 rounded-3xl border border-slate-200/90 shadow-sm">
            
            <?php if ($message): ?>
                <div class="mb-5 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
                    <span><?= htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($token_valid): ?>
                <form class="space-y-4" action="/reset-password?token=<?= htmlspecialchars($token); ?>" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$user_id; ?>">

                    <div>
                        <label for="new_password" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">
                            New Password
                        </label>
                        <input 
                            id="new_password" 
                            name="new_password" 
                            type="password" 
                            required 
                            minlength="8" 
                            placeholder="At least 8 characters"
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition"
                        >
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">
                            Confirm New Password
                        </label>
                        <input 
                            id="confirm_password" 
                            name="confirm_password" 
                            type="password" 
                            required 
                            minlength="8" 
                            placeholder="Re-enter password"
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition"
                        >
                    </div>

                    <button 
                        type="submit" 
                        class="w-full flex justify-center py-3.5 px-4 rounded-xl text-xs font-bold uppercase tracking-wider text-white bg-sky-700 hover:bg-sky-800 transition shadow-md shadow-sky-700/20 active:scale-98"
                    >
                        Save & Update Password
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="/forgot-password" class="inline-block w-full py-3 px-4 rounded-xl text-xs font-bold uppercase tracking-wider text-sky-700 bg-sky-50 hover:bg-sky-100 transition border border-sky-200">
                        Request New Reset Link
                    </a>
                </div>
            <?php endif; ?>

            <div class="mt-6 text-center pt-4 border-t border-slate-100">
                <a href="/login" class="text-xs font-bold text-slate-500 hover:text-sky-700 transition inline-flex items-center gap-1">
                    <span>&larr; Return to Sign In</span>
                </a>
            </div>
        </div>
    </div>
</div>