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
function sendResetZohoMail($pdo, $toEmail, $toName, $subject, $htmlContent) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
    $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // 1. Email එක database හි තිබේදැයි පරීක්ෂාව
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // 2. Token එක සෑදීම (පැය 1ක expiry කාලයක් සහිතව)
            $token = bin2hex(random_bytes(32)); 
            $expire_time = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 3. Database එකෙහි token එක update කිරීම
            $upd_stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expire = ? WHERE id = ?");
            if ($upd_stmt->execute([$token, $expire_time, $user['id']])) {

                // 4. Reset Link එක සාදා Email යැවීම
                $reset_link = "https://readershub.lk/reset-password?token=" . $token;

                $subject = "Password Reset Request - ReadersHub";
                $body = "
                    <p>Hello <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                    <p>We received a request to reset your password on ReadersHub. Click the button below to choose a new password:</p>
                    <div style='margin: 24px 0;'>
                        <a href='" . $reset_link . "' style='background:#0369a1; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; font-size:13px; display:inline-block;'>Reset My Password &rarr;</a>
                    </div>
                    <p style='color:#64748b; font-size:12px; line-height: 1.5;'>This link will automatically expire in 1 hour.<br>If you did not make this request, you can safely ignore this email.</p>
                ";

                if (sendResetZohoMail($pdo, $email, $user['username'], $subject, $body)) {
                    $message = "A password reset link has been sent to your email address.";
                    $messageType = "success";
                } else {
                    $message = "Could not send reset email. Please try again or contact support.";
                    $messageType = "error";
                }
            } else {
                $message = "A database error occurred. Please try again.";
                $messageType = "error";
            }
        } else {
            $message = "If that email exists in our system, we have dispatched a password reset link.";
            $messageType = "success";
        }
    } else {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    }
}
?>

<script>document.title = "Forgot Password | ReadersHub";</script>

<div class="min-h-[80vh] flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 bg-[#f8fafc]" style="font-family: 'Poppins', sans-serif;">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="w-12 h-12 bg-sky-50 text-sky-700 border border-sky-200 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-xs">
            <svg class="w-6 h-6 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
        </div>
        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
            Reset Password
        </h2>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 font-medium">
            Enter your registered email address and we'll send you an instant reset link.
        </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 sm:px-10 rounded-3xl border border-slate-200/90 shadow-sm">

            <?php if ($message): ?>
                <div class="mb-5 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
                    <span><?= htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-4" action="/forgot-password" method="POST">
                <input type="hidden" name="action" value="forgot_password">

                <div>
                    <label for="email" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">
                        Registered Email Address
                    </label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        autocomplete="email" 
                        required 
                        placeholder="name@example.com"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-sky-700 outline-none transition"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full flex justify-center py-3.5 px-4 rounded-xl text-xs font-bold uppercase tracking-wider text-white bg-sky-700 hover:bg-sky-800 transition shadow-md shadow-sky-700/20 active:scale-98"
                >
                    Send Reset Link
                </button>
            </form>

            <div class="mt-6 text-center pt-4 border-t border-slate-100">
                <a href="/login" class="text-xs font-bold text-slate-500 hover:text-sky-700 transition inline-flex items-center gap-1">
                    <span>&larr; Return to Sign In</span>
                </a>
            </div>
        </div>
    </div>
</div>