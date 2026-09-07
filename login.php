<?php
/**
 * Hardened Login Handler - FWMS
 * Location: root/login.php
 * Version: 5.0.0 (IP-backed lockout)
 */
require_once 'functions.php';

// 1. If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$ip = fwms_client_ip();
$lock = fwms_login_lockout_status($pdo, $ip);
$is_locked_out = !empty($lock['locked']);
$remaining_lockout = (int) ($lock['remaining'] ?? 0);

// 2. Handle Login Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($is_locked_out) {
        $error = "Too many failed attempts. Please wait " . ceil($remaining_lockout / 60) . " minute(s).";
    } else {
        // SECURITY: Verify CSRF Token
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            die("Security Check Failed: Invalid CSRF Token.");
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user->password)) {
                fwms_login_lockout_clear($pdo, $ip);
                session_regenerate_id(true);

                $_SESSION['user_id']   = (int)$user->id;
                $_SESSION['user_name'] = $user->username;
                $_SESSION['user_role'] = $user->role;
                $_SESSION['can_edit']  = (int)$user->can_edit;
                $_SESSION['can_delete']= (int)$user->can_delete;

                header("Location: index.php");
                exit;
            } else {
                $fail = fwms_login_lockout_record_failure($pdo, $ip);
                $is_locked_out = !empty($fail['locked']);
                $remaining_lockout = (int) ($fail['remaining'] ?? 0);

                if ($is_locked_out) {
                    $error = "Account temporarily locked after " . FWMS_MAX_LOGIN_ATTEMPTS . " failed attempts. Try again in 15 minutes.";
                } else {
                    $error = "Invalid username or password. " . (int) $fail['attempts_left'] . " attempt(s) remaining.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | FWMS Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="bg-slate-900 flex items-center justify-center h-screen px-4">

    <!-- Decorative background elements -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10">
        <div class="absolute top-[-10%] right-[-10%] w-96 h-96 bg-blue-600 rounded-full blur-[120px] opacity-20"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-96 h-96 bg-blue-900 rounded-full blur-[120px] opacity-30"></div>
    </div>

    <div class="login-card p-8 md:p-12 rounded-[2.5rem] shadow-2xl w-full max-w-md border border-slate-100/50">
        
        <!-- Branding Header -->
        <div class="text-center mb-10">
            <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center text-white font-black italic mx-auto mb-4 shadow-xl shadow-blue-500/20">FW</div>
            <h2 class="text-3xl font-black italic uppercase tracking-tighter text-slate-800 leading-none">System Login</h2>
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mt-3">Personnel Access Gateway</p>
        </div>
        
        <!-- Error Message -->
        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 text-xs font-black uppercase text-center border border-red-100 flex items-center justify-center gap-2 animate-pulse">
                <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" class="space-y-6" id="loginForm" onsubmit="handleLoginSubmit(this)">
            <!-- CSRF Token Hidden Field -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="group">
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2 group-focus-within:text-blue-500 transition-colors">Username</label>
                <input type="text" name="username"
                    class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-4 focus:ring-blue-50 transition-all font-bold text-slate-700 shadow-inner outline-none"
                    placeholder="Enter identification"
                    <?php echo $is_locked_out ? 'disabled' : 'required autofocus'; ?>>
            </div>

            <div class="group">
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2 group-focus-within:text-blue-500 transition-colors">Security Password</label>
                <input type="password" name="password"
                    class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-4 focus:ring-blue-50 transition-all font-bold text-slate-700 shadow-inner outline-none"
                    placeholder="••••••••"
                    <?php echo $is_locked_out ? 'disabled' : 'required'; ?>>
            </div>

            <button type="submit" id="loginBtn"
                class="w-full bg-slate-900 text-white py-5 rounded-2xl hover:bg-blue-600 transition-all font-black uppercase text-xs tracking-widest shadow-xl shadow-slate-200 transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                Sign In to Portal
            </button>
        </form>

        <div class="mt-10 pt-6 border-t border-slate-100">
            <p class="text-center text-[9px] text-slate-300 uppercase font-black tracking-[0.3em]">
                Security Protocol v5.0 Enabled
            </p>
        </div>
    </div>

    <script>
        function handleLoginSubmit(form) {
            var btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.textContent = 'Authenticating...';
        }
    </script>
</body>
</html>
