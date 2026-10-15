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
                $reset_link = "http://localhost:8000/reset-password?token=" . $token;

                $subject = "Password Reset Request - 4MEN Collection";
                $body = "
                    <p>Hello <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                    <p>We received a request to reset your password on 4MEN Collection. Click the button below to choose a new password:</p>
                    <div style='margin: 28px 0;'>
                        <a href='" . $reset_link . "' style='background:#000000; color:#ffffff; padding:13px 30px; border-radius:12px; text-decoration:none; font-weight:bold; font-size:13px; display:inline-block; text-transform:uppercase; letter-spacing:0.05em;'>Reset My Password &rarr;</a>
                    </div>
                    <p style='color:#6b7280; font-size:12px; line-height: 1.5;'>This link will automatically expire in 1 hour.<br>If you did not make this request, you can safely ignore this email.</p>
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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script>document.title = "Reset Password | 4MEN COLLECTION";</script>

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
            Reset Password
        </h1>
        <p class="mt-2 text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 font-medium">
            Enter your registered email address and we'll send you an instant reset link
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

            <form class="space-y-4" action="/forgot-password" method="POST">
                <input type="hidden" name="action" value="forgot_password">

                <div>
                    <label for="email" class="block text-[11px] font-bold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider mb-2">
                        Registered Email Address
                    </label>
                    <div class="relative">
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            autocomplete="email" 
                            required 
                            placeholder="your@email.com"
                            class="w-full pl-11 pr-4 py-3.5 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="pt-2">
                    <button 
                        type="submit" 
                        class="w-full py-4 px-5 rounded-2xl text-xs sm:text-sm font-extrabold uppercase tracking-wider text-white bg-black hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition shadow-lg shadow-black/15 hover:shadow-xl active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>Send Reset Link</span>
                        <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-7 text-center pt-6 border-t border-neutral-100 dark:border-neutral-800">
                <a href="/login" class="text-xs font-bold text-neutral-500 dark:text-neutral-400 hover:text-black dark:hover:text-white transition inline-flex items-center gap-1.5 hover:underline">
                    <span>&larr; Return to Sign In</span>
                </a>
            </div>
        </div>
    </div>
</div>