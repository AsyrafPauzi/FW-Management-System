<?php
/**
 * System Configuration & Global Security
 * Location: root/config.php
 * Version: 4.0.0 (ENV-Hardened)
 */
session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Strict']);
session_start();

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
?>