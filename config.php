<?php
/**
 * System Configuration & Global Security
 * Location: root/config.php
 * Version: 5.0.0 (ENV-Hardened, Testable)
 */
if (session_status() === PHP_SESSION_NONE && !(defined('FWMS_SKIP_DB') && FWMS_SKIP_DB)) {
    if (PHP_SAPI !== 'cli') {
        session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Strict']);
    }
    session_start();
}
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = [];
}
// ==================================================
// 1. LOAD ENVIRONMENT VARIABLES FROM .env
// ==================================================
function load_env($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!empty($key) && !defined($key)) {
            define($key, $value);
            putenv("$key=$value");
        }
    }
}
load_env(__DIR__ . '/.env');

// ==================================================
// 2. DEFINE CONSTANTS (fallback if .env missing)
// ==================================================
if (!defined('DB_HOST'))   define('DB_HOST',   'localhost');
if (!defined('DB_USER'))   define('DB_USER',   '');
if (!defined('DB_PASS'))   define('DB_PASS',   '');
if (!defined('DB_NAME'))   define('DB_NAME',   '');
if (!defined('BASE_URL'))  define('BASE_URL',  'http://localhost/');
if (!defined('SMTP_HOST')) define('SMTP_HOST', '');
if (!defined('SMTP_PORT')) define('SMTP_PORT', '587');
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_FROM')) define('SMTP_FROM', '');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'FWMS System');
if (!defined('BACKUP_TOKEN')) define('BACKUP_TOKEN', '');
if (!defined('APP_ENV'))   define('APP_ENV',   'production');

// ==================================================
// 3. CSRF TOKEN GENERATION
// ==================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================================================
// 4. DATABASE CONNECTION
// ==================================================
// PHPUnit / unit tests can define FWMS_SKIP_DB before including config.
if (!defined('FWMS_SKIP_DB')) {
    define('FWMS_SKIP_DB', false);
}

$pdo = null;
if (!FWMS_SKIP_DB) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } catch (PDOException $e) {
        error_log("DB Connection Error: " . $e->getMessage());
        if (APP_ENV === 'production') {
            die("<h3>System Error</h3><p>Could not connect to the database. Please contact your administrator.</p>");
        } else {
            die("<h3>DB Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
        }
    }
}

// ==================================================
// 5. FATAL ERROR LOGGING (observability)
// ==================================================
if (!FWMS_SKIP_DB) {
    $fwms_storage = __DIR__ . '/storage';
    if (!is_dir($fwms_storage)) {
        @mkdir($fwms_storage, 0755, true);
    }
    register_shutdown_function(function () use ($fwms_storage) {
        $err = error_get_last();
        if (!$err) return;
        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatal_types, true)) return;
        $line = sprintf(
            "[%s] %s in %s:%d\n",
            date('c'),
            $err['message'],
            $err['file'],
            $err['line']
        );
        @file_put_contents($fwms_storage . '/php_errors.log', $line, FILE_APPEND | LOCK_EX);
    });
}
?>