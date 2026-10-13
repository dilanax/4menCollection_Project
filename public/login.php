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

<div class="min-h-[75vh] flex flex-col justify-center py-10 sm:py-14 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <!-- Logo Icon -->
        <a href="/" class="inline-flex items-center gap-2 group mb-4">
            <div class="w-11 h-11 rounded-2xl bg-sky-500 text-white flex items-center justify-center shadow-lg shadow-sky-500/20 group-hover:scale-105 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="text-2xl font-black text-slate-900 tracking-tight">readers<span class="text-sky-500">hub</span></span>
        </a>
        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
            Welcome Back
        </h2>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Sign in to your ReadersHub account to continue</p>
    </div>

    <div class="mt-7 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 sm:px-10 rounded-3xl shadow-sm border border-slate-200/80">
            
            <?php if (!empty($success)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl mb-6 flex items-start gap-3 text-xs sm:text-sm font-semibold">
                    <svg class="w-5 h-5 flex-shrink-0 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl mb-6 flex items-start gap-3 text-xs sm:text-sm font-semibold">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-5" method="POST" action="/login">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                <div>
                    <label for="login_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Username or Email</label>
                    <div class="relative">
                        <input 
                            id="login_id" 
                            type="text" 
                            name="login_id" 
                            value="<?= htmlspecialchars($_POST['login_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required 
                            placeholder="e.g. kasun or your@email.com"
                            class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-sky-400 focus:ring-4 focus:ring-sky-100 outline-none transition"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Password</label>
                        <a href="/forgot-password" class="text-xs font-bold text-sky-600 hover:text-sky-700 transition hover:underline">
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
                            class="w-full pl-11 pr-11 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-sky-400 focus:ring-4 focus:ring-sky-100 outline-none transition"
                        >
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <button type="button" onclick="togglePassVisibility('password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600">
                            <svg class="w-4 h-4 eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <button 
                    type="submit" 
                    name="login" 
                    class="w-full py-3.5 px-4 bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs sm:text-sm rounded-xl shadow-md shadow-sky-200 transition focus:outline-none"
                >
                    Sign In Securely
                </button>
            </form>
            
            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-500">
                    New to ReadersHub? 
                    <a href="/register" class="font-bold text-sky-600 hover:text-sky-700 hover:underline transition ml-1">
                        Create an account
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