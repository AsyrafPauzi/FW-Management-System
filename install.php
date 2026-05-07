<?php
/**
 * Hardened Database Installer - FWMS
 * Location: root/install.php
 * Version: 3.0.0 (Security Hardened)
 */

require_once 'config.php';

// 1. SECURITY: Check for Installer Lock
// This prevents anyone from re-running the installer and overwriting data
$lock_file = __DIR__ . '/install.lock';
if (file_exists($lock_file)) {
    die("
    <div style='font-family:sans-serif; text-align:center; padding:100px;'>
        <h2 style='color:#ef4444;'>Security Alert</h2>
        <p style='color:#64748b;'>The system is already installed and the installer is locked.</p>
        <p style='font-size:12px; color:#94a3b8;'>To re-run, manually delete <b>install.lock</b> from your server.</p>
        <br>
        <a href='index.php' style='display:inline-block; background:#0f172a; color:white; padding:12px 24px; border-radius:12px; text-decoration:none; font-weight:bold;'>Go to Portal</a>
    </div>");
}

try {
    // 2. Define Schema
    $sql = "
    -- SETTINGS TABLE
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        meta_key VARCHAR(100) UNIQUE, 
        meta_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- USERS TABLE
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        username VARCHAR(50) UNIQUE, 
        password VARCHAR(255), 
        role ENUM('admin', 'staff') DEFAULT 'staff', 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    
    
    CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    username VARCHAR(50) UNIQUE, 
    account_name VARCHAR(100), 
    password VARCHAR(255), 
    role ENUM('admin', 'staff') DEFAULT 'staff', 
    can_edit TINYINT(1) DEFAULT 0, 
    can_delete TINYINT(1) DEFAULT 0, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

    -- WORKERS TABLE (Master Data)
    CREATE TABLE IF NOT EXISTS workers (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        passport_number VARCHAR(20) UNIQUE, 
        full_name VARCHAR(100), 
        nationality VARCHAR(50), 
        gender VARCHAR(10), 
        dob DATE, 
        current_stage INT DEFAULT 1, 
        payment1_receipt VARCHAR(50), 
        payment1_date DATE, 
        payment1_time TIME, 
        payment1_amount DECIMAL(10,2), 
        payment1_proof VARCHAR(255), 
        fomema_status VARCHAR(20), 
        fomema_code VARCHAR(20), 
        fomema_date DATE, 
        fomema_proof VARCHAR(255), 
        insurance_policy VARCHAR(50), 
        insurance_provider VARCHAR(50), 
        insurance_expiry DATE, 
        insurance_proof VARCHAR(255), 
        payment2_receipt VARCHAR(50), 
        payment2_date DATE, 
        payment2_time TIME, 
        payment2_amount DECIMAL(10,2), 
        payment2_proof VARCHAR(255), 
        levy_reference VARCHAR(50), 
        levy_expiry DATE, 
        permit_number VARCHAR(50), 
        permit_issue DATE, 
        permit_expiry DATE, 
        cidb_card_no VARCHAR(50), 
        cidb_expiry DATE, 
        created_by VARCHAR(50), 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- INVOICES TABLE
    CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        doc_no VARCHAR(50), 
        type VARCHAR(20), 
        client_name VARCHAR(100), 
        description TEXT, 
        amount DECIMAL(10,2), 
        invoice_date DATE, 
        created_by VARCHAR(50), 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- SYSTEM LOGS
    CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        user_id INT, 
        user_name VARCHAR(50), 
        user_role VARCHAR(20), 
        action VARCHAR(50), 
        details TEXT, 
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- WORKER ARCHIVES (RENEWAL HISTORY)
    CREATE TABLE IF NOT EXISTS worker_archives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        worker_id INT,
        passport_number VARCHAR(20),
        full_name VARCHAR(100),
        archive_data TEXT,
        archive_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // 3. Execute Table Creation
    $pdo->exec($sql);

    // 4. Seed Initial Settings
    $pdo->prepare("INSERT IGNORE INTO settings (meta_key, meta_value) VALUES ('company_name', 'AGD Sports System'), ('company_logo', '')")->execute();

    // 5. Seed Initial Admin Account
    // Default password is 'password' - MUST be changed upon first login
    $admin_pass = password_hash('password', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT IGNORE INTO users (username, password, role) VALUES ('admin', ?, 'admin')")->execute([$admin_pass]);

    // 6. Finalize: Create the Lock file
    file_put_contents($lock_file, "Installed on: " . date('Y-m-d H:i:s'));

    // Success UI
    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Installation Complete</title>
        <script src='https://cdn.tailwindcss.com'></script>
    </head>
    <body class='bg-slate-900 flex items-center justify-center h-screen'>
        <div class='bg-white p-12 rounded-[3rem] shadow-2xl text-center max-w-lg'>
            <div class='w-20 h-20 bg-emerald-100 text-emerald-600 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6'>✓</div>
            <h1 class='text-3xl font-black text-slate-800 uppercase italic tracking-tighter'>System Installed</h1>
            <p class='text-slate-500 mt-4 font-medium'>Database synchronized and security protocols established.</p>
            
            <div class='bg-slate-50 p-6 rounded-2xl my-8 text-left border border-slate-100'>
                <p class='text-[10px] font-black uppercase text-slate-400 mb-2'>Temporary Credentials</p>
                <p class='text-sm font-bold text-slate-700'>User: <span class='text-blue-600'>admin</span></p>
                <p class='text-sm font-bold text-slate-700'>Pass: <span class='text-blue-600'>password</span></p>
                <p class='text-[9px] text-red-400 mt-3 font-bold italic uppercase underline'>Change password immediately after login!</p>
            </div>

            <a href='login.php' class='block w-full bg-slate-900 text-white py-4 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-blue-600 transition shadow-xl'>
                Enter System Portal
            </a>
            
            <p class='text-[10px] text-slate-400 mt-6 uppercase font-bold tracking-widest'>Installer is now locked.</p>
        </div>
    </body>
    </html>";

} catch (PDOException $e) {
    // Error UI
    die("
    <div style='font-family:sans-serif; text-align:center; padding:100px;'>
        <h2 style='color:#ef4444;'>Installation Failed</h2>
        <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please check your config.php database credentials.</p>
    </div>");
}