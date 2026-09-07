<?php
/**
 * Main Entry Point - FW Management System
 * Location: root/index.php
 * Version: 4.0.0 (Financials, Account Name & Security Hardened)
 */
require_once 'functions.php';

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// index.php - Replace line 16-17
$allowed_pages = ['dashboard', 'workers', 'wizard', 'reports', 'invoice', 'archives', 'profile', 'logs', 'settings', 'users'];
$page = isset($_GET['page']) && in_array($_GET['page'], $allowed_pages) ? $_GET['page'] : 'dashboard';

// 2. Routing Logic
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// 3. Fetch Current User's Account Name (Used for "Issued By" in PDFs)
$stmt = $db->pdo->prepare("SELECT account_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_account_name = $stmt->fetchColumn() ?: $user_name;

// 4. Load Company Branding
$site_title = $db->get_setting('company_name', 'FWMS System');
$site_logo = $db->get_setting('company_logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($site_title); ?> | Portal</title>

    <!-- CSRF Meta Tag (read by JS instead of inline variable) -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">

    <!-- External Assets -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">

    <script>
        // Global Constants for AJAX and PDF Generation
        var API_URL = 'api.php';
        var CURRENT_USER_ACCOUNT_NAME = '<?php echo e($user_account_name); ?>';
    var USER_ROLE = '<?php echo $_SESSION['user_role']; ?>'; // <--- ADD THIS LINE
    </script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased font-sans">

    <!-- MOBILE OVERLAY -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/60 z-40 hidden transition-opacity lg:hidden" onclick="toggleSidebar()"></div>

    <div class="flex h-screen overflow-hidden">
        
        <!-- SIDEBAR -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-72 bg-slate-900 text-white flex flex-col z-50 transform -translate-x-full transition-transform duration-300 ease-in-out lg:relative lg:translate-x-0 shadow-2xl">
            <div class="p-6 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <?php if($site_logo): ?>
                        <img src="<?php echo e($site_logo); ?>" class="h-8 w-auto">
                    <?php else: ?>
                        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black italic shadow-lg">FW</div>
                    <?php endif; ?>
                    <h1 class="text-xl font-black uppercase italic tracking-tighter truncate"><?php echo e($site_title); ?></h1>
                </div>
                <button class="lg:hidden text-slate-400 hover:text-white" onclick="toggleSidebar()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <nav class="flex-1 p-4 space-y-1 overflow-y-auto custom-scrollbar">
                <a href="?page=dashboard" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='dashboard'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>📊</span> Dashboard
                </a>
                <a href="?page=workers" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='workers'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>👥</span> Worker List
                </a>
                <a href="?page=wizard" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='wizard'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>📝</span> Registration
                </a>
                <a href="?page=archives" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='archives'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>📦</span> Renewal Archives
                </a>
                <a href="?page=invoice" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='invoice'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>🧾</span> Document Gen
                </a>
                <a href="?page=reports" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='reports'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>📈</span> Reports
                </a>
                
                <div class="pt-6 mt-6 border-t border-slate-800 opacity-30 uppercase text-[10px] px-4 font-bold tracking-widest text-slate-500 italic mb-2">Management</div>
                
                <a href="?page=profile" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='profile'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>🔒</span> Security
                </a>
                
                <?php if($user_role == 'admin'): ?>
                <a href="?page=settings" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='settings'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>🎨</span> Branding
                </a>
                <a href="?page=users" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='users'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>🛠️</span> System Staff
                </a>
                <a href="?page=logs" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition font-bold <?php echo $page=='logs'?'active':'text-slate-400 hover:bg-slate-800'; ?>">
                    <span>📜</span> Audit Logs
                </a>
                <?php endif; ?>
            </nav>

            <div class="p-6 border-t border-slate-800">
                <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-3 bg-red-500/10 text-red-500 border border-red-500/20 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-red-500 hover:text-white transition duration-300">
                    Sign Out
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 flex flex-col relative overflow-hidden">
            
            <!-- HEADER -->
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-10 shadow-sm z-30">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden p-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-blue-600 hover:text-white transition-colors" onclick="toggleSidebar()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                    </button>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest hidden sm:block">
                        Portal / <span class="text-slate-900"><?php echo e(str_replace('_', ' ', $page)); ?></span>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="text-right hidden md:block">
                        <p class="text-xs font-black text-slate-800 leading-none mb-1"><?php echo e($user_account_name); ?></p>
                        <p class="text-[9px] text-blue-500 uppercase font-black tracking-tighter">Verified <?php echo e($user_role); ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center font-black text-slate-400 border border-slate-200">
                        <?php echo e(strtoupper(substr($user_name, 0, 1))); ?>
                    </div>
                </div>
            </header>

            <!-- DYNAMIC VIEW CONTENT -->
            <section class="flex-1 overflow-y-auto p-4 md:p-10 custom-scrollbar">
                <div class="max-w-7xl mx-auto">
                    <?php 
                        $f = "views/$page.php"; 
                        if(file_exists($f)) {
                            include $f; 
                        } else {
                            echo "<div class='p-20 text-center font-bold text-slate-300 uppercase italic tracking-widest'>The requested module could not be found.</div>"; 
                        }
                    ?>
                </div>
            </section>
        </main>
    </div>

    <!-- SIDEBAR TOGGLE SCRIPT -->
    <script>
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebar-overlay');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }
    </script>
    <script src="assets/js/script.js?v=5.0.3"></script>
</body>
</html>