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

    $business_name = !empty($settings['business_name']) ? $settings['business_name'] : '4MEN Collection';
    $fromName = !empty($settings['from_name']) ? $settings['from_name'] : $business_name;
    $siteUrl = !empty($settings['website_url']) ? rtrim($settings['website_url'], '/') : 'http://localhost:8000';
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
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #111827; }
        .email-wrapper { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .email-header { background: #000000; padding: 32px 24px; text-align: center; color: #ffffff; }
        .email-body { padding: 32px 28px; font-size: 14px; line-height: 1.6; color: #374151; }
        .email-footer { background: #f9fafb; padding: 20px 24px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            ' . $logoHtml . '
            <h2 style="margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">' . htmlspecialchars($business_name) . '</h2>
        </div>
        <div class="email-body">
            <p>Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
            <p>Welcome to <strong>' . htmlspecialchars($business_name) . '</strong>! Your account has been created successfully. You can now browse our exclusive menswear collection, place orders, and track your deliveries in real-time.</p>
            
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 14px; padding: 18px; margin: 22px 0;">
                <p style="margin: 0 0 8px; font-size: 13px;"><strong>Username:</strong> <span style="color: #000000; font-weight: bold;">' . htmlspecialchars($username) . '</span></p>
                <p style="margin: 0; font-size: 13px;"><strong>Email Address:</strong> ' . htmlspecialchars($toEmail) . '</p>
            </div>

            <div style="margin: 28px 0; text-align: center;">
                <a href="' . htmlspecialchars($loginUrl) . '" style="background: #000000; color: #ffffff; padding: 13px 32px; border-radius: 12px; font-weight: bold; text-decoration: none; display: inline-block; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em;">Sign In to Your Account &rarr;</a>
            </div>

            <p style="font-size: 12px; color: #6b7280; line-height: 1.5; margin-top: 24px;">If you ever forget your password, you can easily request a reset link using your registered email address.</p>
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

    $domain = (strpos($smtpUser, '@') !== false) ? explode('@', $smtpUser)[1] : '4mencollection.lk';
    $messageId = "<" . time() . "." . md5(uniqid(rand(), true)) . "@" . $domain . ">";

    $subject = "Welcome to " . $business_name . "! Your Account is Ready";

    $headers = [
        "Date: " . date("r"),
        "To: <{$toEmail}>",
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$smtpUser}>",
        "Reply-To: <{$smtpUser}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "Message-ID: {$messageId}",
        "X-Mailer: 4MEN Collection PHP Mailer",
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
        fputs($socket, "EHLO 4mencollection.lk\r\n"); $read($socket);
        fputs($socket, "STARTTLS\r\n");
        $tls = $read($socket);
        if (strpos($tls, '220') === false) { fclose($socket); return false; }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        fputs($socket, "EHLO 4mencollection.lk\r\n"); $read($socket);
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

                $_SESSION['success_msg'] = "Account created successfully! Welcome to 4MEN Collection. Please sign in to continue.";
                header("Location: /login");
                exit;
            } else {
                $error = "An error occurred while creating your account. Please try again.";
            }
        }
    }
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<div class="min-h-[80vh] flex flex-col justify-center py-12 sm:py-16 px-4 sm:px-6 lg:px-8 relative selection:bg-black selection:text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">
    
    <!-- Subtle Ambient Background Decor -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden -z-10">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[550px] h-[550px] bg-neutral-100 dark:bg-neutral-800/20 rounded-full blur-3xl opacity-70"></div>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <!-- Modern 4MEN COLLECTION Admin-Style Monochrome Logo -->
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
            Create an Account
        </h1>
        <p class="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 mt-2">
            Join 4MEN Collection for an elevated shopping experience
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white dark:bg-neutral-900 py-9 px-6 sm:px-10 rounded-3xl shadow-xl shadow-neutral-900/5 border border-neutral-200 dark:border-neutral-800 transition-all">
            
            <!-- Error Message Notification -->
            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-900 dark:text-rose-200 p-4 rounded-2xl mb-6 flex items-start gap-3 text-xs sm:text-sm font-semibold animate-fade-in">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-4 sm:space-y-4.5" method="POST" action="/register">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                <!-- Username Field -->
                <div>
                    <label for="reg-username" class="block text-[11px] font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5">
                        Choose Username
                    </label>
                    <div class="relative">
                        <input 
                            id="reg-username"
                            type="text" 
                            name="username" 
                            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required 
                            pattern="[a-zA-Z0-9_]{3,25}" 
                            title="3-25 characters (letters, numbers, underscores)" 
                            placeholder="e.g. kasun_perera"
                            class="w-full pl-11 pr-4 py-3 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <!-- Email Address Field -->
                <div>
                    <label for="reg-email" class="block text-[11px] font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5">
                        Email Address
                    </label>
                    <div class="relative">
                        <input 
                            id="reg-email"
                            type="email" 
                            name="email" 
                            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required 
                            placeholder="your@email.com"
                            class="w-full pl-11 pr-4 py-3 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <label for="reg-password" class="block text-[11px] font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5">
                        Password
                    </label>
                    <div class="relative">
                        <input 
                            id="reg-password" 
                            type="password" 
                            name="password" 
                            required 
                            minlength="8" 
                            placeholder="Minimum 8 characters"
                            class="w-full pl-11 pr-11 py-3 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <button type="button" onclick="togglePassVisibility('reg-password', this)" aria-label="Toggle password visibility" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-neutral-400 hover:text-neutral-800 dark:text-neutral-500 dark:hover:text-neutral-200 transition">
                            <svg class="w-4 h-4 eye-icon stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password Field -->
                <div>
                    <label for="reg-confirm-password" class="block text-[11px] font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5">
                        Confirm Password
                    </label>
                    <div class="relative">
                        <input 
                            id="reg-confirm-password" 
                            type="password" 
                            name="confirm_password" 
                            required 
                            minlength="8" 
                            placeholder="Re-type your password"
                            class="w-full pl-11 pr-11 py-3 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </span>
                        <button type="button" onclick="togglePassVisibility('reg-confirm-password', this)" aria-label="Toggle confirm password visibility" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-neutral-400 hover:text-neutral-800 dark:text-neutral-500 dark:hover:text-neutral-200 transition">
                            <svg class="w-4 h-4 eye-icon stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2 sm:pt-3">
                    <button 
                        type="submit" 
                        name="register" 
                        class="w-full py-4 px-5 bg-black hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-200 dark:text-black text-white font-extrabold text-xs sm:text-sm uppercase tracking-wider rounded-2xl shadow-lg shadow-black/15 transition-all duration-200 hover:shadow-xl active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>Create Account</span>
                        <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </div>
            </form>
            
            <!-- Login Redirection Link -->
            <div class="mt-7 pt-6 border-t border-neutral-100 dark:border-neutral-800 text-center">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    Already have an account? 
                    <a href="/login" class="font-extrabold text-neutral-900 dark:text-white hover:underline transition ml-1">
                        Sign in
                    </a>
                </p>
            </div>

            <!-- Trust / Security Footnote -->
            <div class="mt-4 text-center">
                <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold tracking-wider uppercase text-neutral-400 dark:text-neutral-500">
                    <svg class="w-3 h-3 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    256-Bit SSL Encrypted Registration
                </span>
            </div>

        </div>
    </div>
</div>

<script>
    function togglePassVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = `<svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
            </svg>`;
            btn.classList.add('text-black', 'dark:text-white');
        } else {
            input.type = 'password';
            btn.innerHTML = `<svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>`;
            btn.classList.remove('text-black', 'dark:text-white');
        }
    }
</script>