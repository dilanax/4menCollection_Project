<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config/db.php';
}

$error = '';
$success = '';

// ==========================================
// ZOHO SMTP WELCOME EMAIL DISPATCH (PORT 587 + STARTTLS SOCKET)
// ==========================================
function sendWelcomeZohoMail($pdo, $toEmail, $username) {
    if (empty($toEmail)) return false;

    $stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

    if (!$settings || empty($settings['smtp_email']) || empty($settings['smtp_app_token'])) {
        return false;
    }

    $business_name = !empty($settings['business_name']) ? $settings['business_name'] : 'ReadersHub';
    $fromName = !empty($settings['from_name']) ? $settings['from_name'] : $business_name;
    $siteUrl = !empty($settings['website_url']) ? rtrim($settings['website_url'], '/') : 'https://readershub.lk';
    $loginUrl = $siteUrl . '/login';

    $logoHtml = '';
    if (!empty($settings['business_logo'])) {
        $logoUrl = $siteUrl . '/' . ltrim($settings['business_logo'], '/');
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
            <p>Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
            <p>Welcome to <strong>' . htmlspecialchars($business_name) . '</strong>! Your reader account has been created successfully. You can now browse our full collection, place orders, and track your parcel deliveries in real-time.</p>
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; margin: 22px 0;">
                <p style="margin: 0 0 8px; font-size: 13px;"><strong>Username:</strong> <span style="color: #0369a1; font-weight: bold;">' . htmlspecialchars($username) . '</span></p>
                <p style="margin: 0; font-size: 13px;"><strong>Email Address:</strong> ' . htmlspecialchars($toEmail) . '</p>
            </div>

            <div style="margin: 26px 0; text-align: center;">
                <a href="' . htmlspecialchars($loginUrl) . '" style="background: #0369a1; color: #ffffff; padding: 12px 30px; border-radius: 12px; font-weight: bold; text-decoration: none; display: inline-block; font-size: 13px;">Sign In to Your Account &rarr;</a>
            </div>

            <p style="font-size: 12px; color: #64748b; line-height: 1.5; margin-top: 24px;">If you ever forget your password, you can easily request a reset link using your registered email address.</p>
        </div>
        <div class="email-footer">
            <p style="margin: 0 0 4px;">' . htmlspecialchars($business_name) . ' | ' . htmlspecialchars($settings['phone_number'] ?? '') . '</p>
            <p style="margin: 0;">WhatsApp: ' . htmlspecialchars($settings['whatsapp_number'] ?? '') . ' | ' . htmlspecialchars($settings['address'] ?? '') . '</p>
        </div>
    </div>
</body>
</html>';

    $host = !empty($settings['smtp_host']) ? trim($settings['smtp_host']) : 'smtp.zoho.com';
    $port = !empty($settings['smtp_port']) ? (int)$settings['smtp_port'] : 587;
    $smtpUser = trim($settings['smtp_email']);
    $password = trim($settings['smtp_app_token']);

    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $fullBody);
    $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

    $domain = (strpos($smtpUser, '@') !== false) ? explode('@', $smtpUser)[1] : 'readershub.lk';
    $messageId = "<" . time() . "." . md5(uniqid(rand(), true)) . "@" . $domain . ">";

    $subject = "Welcome to " . $business_name . "! Your Account is Ready";

    $headers = [
        "Date: " . date("r"),
        "To: <{$toEmail}>",
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$smtpUser}>",
        "Reply-To: <{$smtpUser}>",
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
        fputs($socket, base64_encode($smtpUser) . "\r\n"); $read($socket);
        fputs($socket, base64_encode($password) . "\r\n");
        $auth = $read($socket);
        if (strpos($auth, '235') === false) { fclose($socket); return false; }

        fputs($socket, "MAIL FROM:<{$smtpUser}>\r\n"); $read($socket);
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

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Logged in users redirected
if (isset($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    // CSRF Validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Security Validation Failed (CSRF).");
    }

    $username = trim(htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all the required fields.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,25}$/', $username)) {
        $error = "Username must be 3-25 characters long and contain only letters, numbers, and underscores.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match. Please re-enter.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
        $stmt->execute(['username' => $username, 'email' => $email]);
        
        if ($stmt->rowCount() > 0) {
            $error = "That username or email is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $insert_stmt = $pdo->prepare("INSERT INTO users (username, email, password, user_role) VALUES (:username, :email, :password, 'customer')");
            
            if ($insert_stmt->execute([
                'username' => $username, 
                'email'    => $email, 
                'password' => $hashed_password
            ])) {
                // Dispatch Welcome Email to the newly registered customer
                sendWelcomeZohoMail($pdo, $email, $username);

                $_SESSION['success_msg'] = "Account created successfully! We've sent a welcome email to your inbox.";
                header("Location: /login");
                exit;
            } else {
                $error = "An error occurred while creating your account. Please try again.";
            }
        }
    }
}
?>

<div class="min-h-[75vh] flex flex-col justify-center py-10 sm:py-14 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <a href="/" class="inline-flex items-center gap-2 group mb-4">
            <div class="w-11 h-11 rounded-2xl bg-sky-500 text-white flex items-center justify-center shadow-lg shadow-sky-500/20 group-hover:scale-105 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="text-2xl font-black text-slate-900 tracking-tight">readers<span class="text-sky-500">hub</span></span>
        </a>
        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
            Create an Account
        </h2>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Join ReadersHub for faster checkouts and order tracking</p>
    </div>

    <div class="mt-7 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 sm:px-10 rounded-3xl shadow-sm border border-slate-200/80">
            
            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl mb-6 flex items-start gap-3 text-xs sm:text-sm font-semibold">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-500" fill="currentColor" viewBox="20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-4" method="POST" action="/register">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Choose Username</label>
                    <div class="relative">
                        <input 
                            type="text" 
                            name="username" 
                            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required 
                            pattern="[a-zA-Z0-9_]{3,25}" 
                            title="Only letters, numbers, and underscores (3-25 characters)" 
                            placeholder="e.g. ruwan_perera"
                            class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-sky-400 focus:ring-4 focus:ring-sky-100 outline-none transition"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Email Address</label>
                    <div class="relative">
                        <input 
                            type="email" 
                            name="email" 
                            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required 
                            placeholder="your@email.com"
                            class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-sky-400 focus:ring-4 focus:ring-sky-100 outline-none transition"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Password</label>
                    <div class="relative">
                        <input 
                            id="reg-password" 
                            type="password" 
                            name="password" 
                            required 
                            minlength="8" 
                            placeholder="Minimum 8 characters"
                            class="w-full pl-11 pr-11 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-sky-400 focus:ring-4 focus:ring-sky-100 outline-none transition"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <button type="button" onclick="togglePassVisibility('reg-password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Confirm Password</label>
                    <div class="relative">
                        <input 
                            id="reg-confirm-password" 
                            type="password" 
                            name="confirm_password" 
                            required 
                            minlength="8" 
                            placeholder="Re-type password"
                            class="w-full pl-11 pr-11 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-sky-400 focus:ring-4 focus:ring-sky-100 outline-none transition"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </span>
                        <button type="button" onclick="togglePassVisibility('reg-confirm-password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="pt-2">
                    <button 
                        type="submit" 
                        name="register" 
                        class="w-full py-3.5 px-4 bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs sm:text-sm rounded-xl shadow-md shadow-sky-200 transition focus:outline-none active:scale-98"
                    >
                        Create Account
                    </button>
                </div>
            </form>
            
            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-500">
                    Already have an account? 
                    <a href="/login" class="font-bold text-sky-600 hover:text-sky-700 hover:underline transition ml-1">
                        Sign in
                    </a>
                </p>
            </div>

        </div>
    </div>
</div>

<script>
    function togglePassVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            btn.classList.add('text-sky-600');
        } else {
            input.type = 'password';
            btn.classList.remove('text-sky-600');
        }
    }
</script>