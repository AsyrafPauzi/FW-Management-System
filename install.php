<?php
/**
 * Hardened Database Installer - FWMS
 * Location: root/install.php
 * Version: 5.1.0 (Schema aligned with runtime)
 */

require_once 'config.php';

// 1. SECURITY: Check for Installer Lock
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
    $sql = "
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meta_key VARCHAR(100) UNIQUE,
        meta_value TEXT
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS workers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        passport_number VARCHAR(20) UNIQUE,
        full_name VARCHAR(100),
        category VARCHAR(50) DEFAULT 'Calling Visa',
        kwsp_no VARCHAR(50),
        phone_number VARCHAR(30),
        nationality VARCHAR(50),
        gender VARCHAR(10),
        dob DATE NULL,
        passport_expiry DATE NULL,
        visa_expiry DATE NULL,
        current_stage INT DEFAULT 1,
        total_payable DECIMAL(10,2) DEFAULT 0,
        balance_due DECIMAL(10,2) DEFAULT 0,
        fomema_status VARCHAR(30),
        fomema_code VARCHAR(50),
        fomema_expiry DATE NULL,
        fomema_proof VARCHAR(255),
        insurance_policy VARCHAR(80),
        insurance_provider VARCHAR(80),
        insurance_expiry DATE NULL,
        insurance_proof VARCHAR(255),
        levy_reference VARCHAR(80),
        levy_status VARCHAR(50),
        levy_expiry DATE NULL,
        permit_number VARCHAR(80),
        permit_status VARCHAR(50),
        permit_issue DATE NULL,
        permit_expiry DATE NULL,
        epass_worker_proof VARCHAR(255),
        passport_copy_proof VARCHAR(255),
        cidb_card_no VARCHAR(80),
        cidb_status VARCHAR(30),
        cidb_category VARCHAR(50),
        cidb_expiry DATE NULL,
        cidb_proof VARCHAR(255),
        created_by VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_workers_permit_expiry (permit_expiry),
        INDEX idx_workers_visa_expiry (visa_expiry),
        INDEX idx_workers_stage (current_stage),
        INDEX idx_workers_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS additional_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        worker_id INT NOT NULL,
        description VARCHAR(255),
        ref_no VARCHAR(100),
        amount DECIMAL(10,2) DEFAULT 0,
        payment_date DATE NULL,
        proof_file VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ap_worker (worker_id),
        INDEX idx_ap_ref (ref_no),
        INDEX idx_ap_date (payment_date),
        CONSTRAINT fk_ap_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doc_no VARCHAR(50),
        type VARCHAR(30),
        client_name VARCHAR(100),
        description TEXT,
        amount DECIMAL(10,2),
        invoice_date DATE,
        created_by VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inv_doc_no (doc_no),
        INDEX idx_inv_date (invoice_date),
        INDEX idx_inv_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        user_name VARCHAR(50),
        user_role VARCHAR(20),
        action VARCHAR(50),
        details TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logs_time (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS worker_archives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        worker_id INT,
        passport_number VARCHAR(20),
        full_name VARCHAR(100),
        archive_data TEXT,
        archive_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wa_worker (worker_id),
        INDEX idx_wa_passport (passport_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS login_attempts (
        ip_hash CHAR(64) PRIMARY KEY,
        attempts INT NOT NULL DEFAULT 0,
        locked_until INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);

    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0755, true);
    }
    $uploads_htaccess = __DIR__ . '/uploads/.htaccess';
    if (!file_exists($uploads_htaccess)) {
        file_put_contents($uploads_htaccess, "# Deny script execution in uploads\n<FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|phar|cgi|pl|py|jsp|asp|aspx|sh)$\">\n    Require all denied\n</FilesMatch>\nOptions -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .phar\n");
    }

    $pdo->prepare("INSERT IGNORE INTO settings (meta_key, meta_value) VALUES ('company_name', 'AGD Sports System'), ('company_logo', '')")->execute();

    $admin_pass = password_hash('password', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT IGNORE INTO users (username, account_name, password, role, can_edit, can_delete) VALUES ('admin', 'Administrator', ?, 'admin', 1, 1)")->execute([$admin_pass]);

    file_put_contents($lock_file, "Installed on: " . date('Y-m-d H:i:s'));

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
} catch (Exception $e) {
    echo "
    <div style='font-family:sans-serif; text-align:center; padding:100px;'>
        <h2 style='color:#ef4444;'>Installation Failed</h2>
        <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}
