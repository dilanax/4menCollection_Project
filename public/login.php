<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config/db.php';
}

$error = '';
$success = '';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Password Reset එකෙන් හෝ Register එකෙන් එන Success Message
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// දැනටමත් Logged in නම් Redirect කිරීම
if (isset($_SESSION['user_id'])) {
    if (in_array($_SESSION['user_role'] ?? '', ['admin', 'shop_admin'])) {
        header("Location: /admin");
    } else {
        header("Location: /");
    }
    exit;
}

// Brute Force Lockout Check (විනාඩි 5ක Block එක)
if (isset($_SESSION['lockout_time']) && time() < $_SESSION['lockout_time']) {
    $time_left = ceil(($_SESSION['lockout_time'] - time()) / 60);
    $error = "Too many failed attempts. Please try again in {$time_left} minute(s).";
} elseif (isset($_SESSION['lockout_time']) && time() >= $_SESSION['lockout_time']) {
    unset($_SESSION['login_attempts']);
    unset($_SESSION['lockout_time']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login']) && empty($error)) {
    
    // CSRF Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Security Validation Failed (CSRF).");
    }

    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_id) || empty($password)) {
        $error = "Please enter both your Username/Email and Password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :login_id OR email = :login_id LIMIT 1");
        $stmt->execute(['login_id' => $login_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            unset($_SESSION['login_attempts']);
            unset($_SESSION['lockout_time']);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
            $_SESSION['user_name'] = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
            $_SESSION['user_role'] = $user['user_role'];
            $_SESSION['last_login'] = time();

            if (in_array($user['user_role'], ['admin', 'shop_admin'])) {
                header("Location: /admin");
            } else {
                header("Location: /");
            }
            exit;
        } else {
            if (!isset($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts'] = 0;
            }
            $_SESSION['login_attempts']++;

            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['lockout_time'] = time() + (5 * 60);
                $error = "Too many failed attempts. Please try again in 5 minutes.";
            } else {
                $attempts_left = 5 - $_SESSION['login_attempts'];
                $error = "Invalid Username/Email or Password. ({$attempts_left} attempts left)";
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
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-neutral-100 dark:bg-neutral-800/20 rounded-full blur-3xl opacity-70"></div>
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
            Welcome Back
        </h1>
        <p class="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 mt-2">
            Sign in to access your personal account & orders
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white dark:bg-neutral-900 py-9 px-6 sm:px-10 rounded-3xl shadow-xl shadow-neutral-900/5 border border-neutral-200 dark:border-neutral-800 transition-all">
            
            <!-- Success Message Notification -->
            <?php if (!empty($success)): ?>
                <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-200 p-4 rounded-2xl mb-6 flex items-start gap-3 text-xs sm:text-sm font-semibold animate-fade-in">
                    <svg class="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Error Message Notification -->
            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-900 dark:text-rose-200 p-4 rounded-2xl mb-6 flex items-start gap-3 text-xs sm:text-sm font-semibold animate-fade-in">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-5" method="POST" action="/login">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                <!-- Username or Email Field -->
                <div>
                    <label for="login_id" class="block text-[11px] font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-2">
                        Username or Email
                    </label>
                    <div class="relative">
                        <input 
                            id="login_id" 
                            type="text" 
                            name="login_id" 
                            value="<?= htmlspecialchars($_POST['login_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required 
                            placeholder="Enter your username or email"
                            class="w-full pl-11 pr-4 py-3.5 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <!-- Password Field with Show/Hide Toggle -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-[11px] font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300">
                            Password
                        </label>
                        <a href="/forgot-password" class="text-xs font-semibold text-neutral-500 hover:text-black dark:text-neutral-400 dark:hover:text-white transition hover:underline">
                            Forgot password?
                        </a>
                    </div>
                    <div class="relative">
                        <input 
                            id="password" 
                            type="password" 
                            name="password" 
                            required 
                            placeholder="Enter your password"
                            class="w-full pl-11 pr-11 py-3.5 bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/80 rounded-2xl text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:bg-white dark:focus:bg-neutral-900 focus:border-black dark:focus:border-white focus:ring-4 focus:ring-neutral-900/5 dark:focus:ring-white/5 outline-none transition font-medium"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-neutral-400 dark:text-neutral-500">
                            <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <button type="button" onclick="togglePassVisibility('password', this)" aria-label="Toggle password visibility" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-neutral-400 hover:text-neutral-800 dark:text-neutral-500 dark:hover:text-neutral-200 transition">
                            <svg class="w-4 h-4 eye-icon stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button 
                        type="submit" 
                        name="login" 
                        class="w-full py-4 px-5 bg-black hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-200 dark:text-black text-white font-extrabold text-xs sm:text-sm uppercase tracking-wider rounded-2xl shadow-lg shadow-black/15 transition-all duration-200 hover:shadow-xl active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>Sign In</span>
                        <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </div>
            </form>
            
            <!-- Register Redirection Link -->
            <div class="mt-7 pt-6 border-t border-neutral-100 dark:border-neutral-800 text-center">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    New to 4MEN Collection? 
                    <a href="/register" class="font-extrabold text-neutral-900 dark:text-white hover:underline transition ml-1">
                        Create an account
                    </a>
                </p>
            </div>

            <!-- Trust / Security Footnote -->
            <div class="mt-4 text-center">
                <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold tracking-wider uppercase text-neutral-400 dark:text-neutral-500">
                    <svg class="w-3 h-3 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    256-Bit SSL Encrypted Access
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