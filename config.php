<?php
/**
 * System Configuration & Global Security
 * Location: root/config.php
 * Version: 3.0.0 (Hardened)
 */
 session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Strict']);
session_start();

// 1. DATABASE CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_USER', 'agdsport_fwms_db');
define('DB_PASS', '_pkxJ1+c$c!84WkS');
define('DB_NAME', 'agdsport_fwms_db');

// 2. DOMAIN CONFIGURATION
define('BASE_URL', 'https://internal.agdsports.com/'); 

// 3. CSRF TOKEN GENERATION
// Generates a cryptographically secure token if one doesn't exist for the session
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        // Fallback for older PHP versions
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// 4. DATABASE CONNECTION
try {
    // We include charset=utf8mb4 in the DSN for better security and emoji support
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    
    // Set Error Mode to Exception so we can catch connection issues
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set Default Fetch Mode to Object for cleaner code ($user->name instead of $user['name'])
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    
    // Disable Emulated Prepares to ensure the database handles the security of placeholders
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    // If connection fails, show a clean error message without leaking database details
    error_log("Connection Error: " . $e->getMessage()); // Logs the error internally
    die("<h3>System Error</h3><p>Could not connect to the database. Please check your system configuration settings.</p>");
}
?>