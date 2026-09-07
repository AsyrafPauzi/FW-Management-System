<?php
/**
 * Standalone Core Logic & Security Helpers
 * Location: root/functions.php
 * Version: 5.0.0 (Validation Layer, Query Cache, Pagination)
 */
require_once 'config.php';

// ==================================================
// 1. SECURITY & COMPATIBILITY HELPERS
// ==================================================

/**
 * Decode accidental HTML entities stored in DB (e.g. "&amp;" -> "&").
 * Loops a few times in case values were double-encoded.
 */
function decode_stored_text($str) {
    $str = (string) $str;
    for ($i = 0; $i < 3; $i++) {
        $decoded = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $str) break;
        $str = $decoded;
    }
    return $str;
}

/**
 * Clean user input for DB storage. Do NOT htmlspecialchars here —
 * that belongs only in e() at output time (avoids "&" becoming "&amp;").
 */
function sanitize_text_field($str) {
    if (is_array($str)) return $str;
    $str = strip_tags(trim($str ?? ''));
    $str = str_replace("\0", '', $str);
    return decode_stored_text($str);
}

function e($str) {
    if (is_null($str)) return '';
    // Decode first so legacy "&amp;" rows display as "&"
    return htmlspecialchars(decode_stored_text($str), ENT_QUOTES, 'UTF-8');
}

function selected($val1, $val2, $echo = true) {
    $out = ($val1 == $val2) ? 'selected="selected"' : '';
    if($echo) echo $out;
    return $out;
}

function format_date_my($date) {
    if (!$date || $date == '0000-00-00' || $date == '1970-01-01' || $date == '0001-01-01') return '-';
    try {
        $timestamp = strtotime($date);
        if (!$timestamp || $timestamp < 0) return '-';
        return date('d/m/Y', $timestamp);
    } catch (Exception $e) {
        return '-';
    }
}

// --- PERMISSION HELPERS ---
function current_user_can_admin() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

function current_user_is_staff() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'staff');
}

function current_user_can_edit() {
    if (!isset($_SESSION['user_id'])) return false;
    if ($_SESSION['user_role'] === 'admin') return true;
    return (isset($_SESSION['can_edit']) && $_SESSION['can_edit'] == 1);
}

function current_user_can_delete() {
    if (!isset($_SESSION['user_id'])) return false;
    if ($_SESSION['user_role'] === 'admin') return true;
    return (isset($_SESSION['can_delete']) && $_SESSION['can_delete'] == 1);
}

/**
 * Centralized permission gate for API actions.
 * Sends JSON error and exits if permission is denied.
 */
function sync_session_permissions_from_db($db) {
    if (!isset($_SESSION['user_id'])) return;
    $stmt = $db->pdo->prepare("SELECT role, can_edit, can_delete FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['user_role'] = $u->role;
        $_SESSION['can_edit'] = (int)$u->can_edit;
        $_SESSION['can_delete'] = (int)$u->can_delete;
    }
}

function require_permission($level = 'edit') {
    $ok = false;
    if ($level === 'admin')  $ok = current_user_can_admin();
    elseif ($level === 'delete') $ok = current_user_can_delete();
    else $ok = current_user_can_edit();

    if (!$ok) {
        echo json_encode(['success' => false, 'data' => 'Access Denied: Insufficient permissions.']);
        exit;
    }
}

/**
 * Store an uploaded file with extension + MIME allowlist.
 * Returns public URL path, or null if no file. Throws on invalid upload.
 */
function store_secure_upload($file, $uploads_dir = 'uploads/') {
    if (!isset($file) || empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed.');
    }
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
        throw new Exception("Invalid file type: $ext");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $valid_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime, $valid_mimes, true)) {
        throw new Exception('Security alert: MIME mismatch');
    }

    $safe_filename = bin2hex(random_bytes(10)) . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploads_dir . $safe_filename)) {
        throw new Exception('Could not store uploaded file.');
    }
    return BASE_URL . $uploads_dir . $safe_filename;
}

/**
 * Only keep existing proof paths that already live under uploads/.
 */
function sanitize_existing_upload_path($path) {
    $path = trim((string) $path);
    if ($path === '') return null;
    $basename = basename(parse_url($path, PHP_URL_PATH) ?: $path);
    if ($basename === '' || $basename === '.' || $basename === '..') return null;
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $basename)) return null;
    $local = 'uploads/' . $basename;
    if (!is_file($local)) return null;
    return BASE_URL . $local;
}

/**
 * Validate wizard fields when advancing a stage (Draft stays permissive).
 */
function validate_wizard_stage_advance($stage, array $post, $existing_worker = null) {
    $stage = (int) $stage;
    $req = function ($key, $label) use ($post) {
        $val = trim((string) ($post[$key] ?? ''));
        if ($val === '') return "$label is required to continue.";
        return null;
    };
    $has_proof = function ($field) use ($existing_worker) {
        if (!empty($_FILES[$field]['name']) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            return true;
        }
        return $existing_worker && !empty($existing_worker->$field);
    };

    $errors = [];
    if ($stage <= 1) {
        foreach ([
            'category' => 'Category',
            'passport_number' => 'Passport number',
            'full_name' => 'Full name',
        ] as $k => $label) {
            if ($err = $req($k, $label)) $errors[] = $err;
        }
    }
    if ($stage == 4) {
        foreach ([
            'insurance_policy' => 'Insurance policy',
            'insurance_provider' => 'Insurance provider',
            'insurance_expiry' => 'Insurance expiry',
        ] as $k => $label) {
            if ($err = $req($k, $label)) $errors[] = $err;
        }
    }
    if ($stage == 7) {
        foreach ([
            'permit_number' => 'Permit sticker number',
            'permit_issue' => 'Permit issue date',
            'permit_expiry' => 'Permit expiry date',
        ] as $k => $label) {
            if ($err = $req($k, $label)) $errors[] = $err;
        }
        if (!$has_proof('epass_worker_proof')) {
            $errors[] = 'EPASS worker proof is required to continue.';
        }
    }
    if ($stage == 8) {
        foreach ([
            'cidb_status' => 'CIDB status',
            'cidb_category' => 'CIDB category',
            'cidb_expiry' => 'CIDB expiry',
        ] as $k => $label) {
            if ($err = $req($k, $label)) $errors[] = $err;
        }
    }
    return $errors;
}

// ==================================================
// 2. INPUT VALIDATION LAYER
// ==================================================

/**
 * Validate a single field value.
 * Returns null on pass, or an error string on failure.
 */
function validate_field($value, $rules) {
    if (in_array('required', $rules) && ($value === '' || $value === null)) {
        return 'This field is required.';
    }
    if ($value === '' || $value === null) return null; // optional empty passes

    if (in_array('string', $rules) && !is_string($value)) {
        return 'Must be a string.';
    }
    if (isset($rules['max']) && strlen($value) > $rules['max']) {
        return "Must not exceed {$rules['max']} characters.";
    }
    if (isset($rules['min']) && strlen($value) < $rules['min']) {
        return "Must be at least {$rules['min']} characters.";
    }
    if (in_array('date', $rules) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return 'Invalid date format (expected YYYY-MM-DD).';
    }
    if (in_array('numeric', $rules) && !is_numeric($value)) {
        return 'Must be a numeric value.';
    }
    if (in_array('positive', $rules) && floatval($value) < 0) {
        return 'Must be a positive number.';
    }
    if (isset($rules['in']) && !in_array($value, $rules['in'])) {
        return 'Invalid option selected.';
    }
    if (in_array('email', $rules) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid email address.';
    }
    return null;
}

/**
 * Validate a map of fields against their rules.
 * Returns ['valid' => true] or ['valid' => false, 'errors' => [...]]
 *
 * Example:
 *   validate_request([
 *     'full_name' => ['required', 'string', 'max' => 100],
 *     'dob'       => ['date'],
 *   ], $_POST);
 */
function validate_request(array $rules_map, array $data) {
    $errors = [];
    foreach ($rules_map as $field => $rules) {
        $value = $data[$field] ?? '';
        $err = validate_field($value, $rules);
        if ($err) $errors[$field] = $err;
    }
    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }
    return ['valid' => true];
}

// ==================================================
// 2b. LOGIN LOCKOUT (IP-backed, survives cookie clear)
// ==================================================

if (!defined('FWMS_MAX_LOGIN_ATTEMPTS')) define('FWMS_MAX_LOGIN_ATTEMPTS', 5);
if (!defined('FWMS_LOCKOUT_SECONDS')) define('FWMS_LOCKOUT_SECONDS', 900);

function fwms_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function fwms_login_ip_hash($ip = null) {
    $ip = $ip ?? fwms_client_ip();
    return hash('sha256', $ip . '|fwms-login');
}

function ensure_login_attempts_table(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        ip_hash CHAR(64) PRIMARY KEY,
        attempts INT NOT NULL DEFAULT 0,
        locked_until INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * @return array{locked:bool,remaining:int,attempts:int}
 */
function fwms_login_lockout_status(PDO $pdo, $ip = null) {
    ensure_login_attempts_table($pdo);
    $hash = fwms_login_ip_hash($ip);
    $stmt = $pdo->prepare("SELECT attempts, locked_until FROM login_attempts WHERE ip_hash = ? LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['locked' => false, 'remaining' => 0, 'attempts' => 0];
    }
    $locked_until = (int) $row['locked_until'];
    $now = time();
    if ($locked_until > $now) {
        return [
            'locked' => true,
            'remaining' => $locked_until - $now,
            'attempts' => (int) $row['attempts'],
        ];
    }
    // Lock expired — reset so the next cycle starts clean.
    if ($locked_until > 0 || (int) $row['attempts'] >= FWMS_MAX_LOGIN_ATTEMPTS) {
        $clear = $pdo->prepare("UPDATE login_attempts SET attempts = 0, locked_until = 0 WHERE ip_hash = ?");
        $clear->execute([$hash]);
        return ['locked' => false, 'remaining' => 0, 'attempts' => 0];
    }
    return [
        'locked' => false,
        'remaining' => 0,
        'attempts' => (int) $row['attempts'],
    ];
}

function fwms_login_lockout_record_failure(PDO $pdo, $ip = null) {
    ensure_login_attempts_table($pdo);
    $hash = fwms_login_ip_hash($ip);
    $status = fwms_login_lockout_status($pdo, $ip);
    $attempts = $status['attempts'] + 1;
    $locked_until = 0;
    if ($attempts >= FWMS_MAX_LOGIN_ATTEMPTS) {
        $locked_until = time() + FWMS_LOCKOUT_SECONDS;
    }
    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (ip_hash, attempts, locked_until)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until)"
    );
    $stmt->execute([$hash, $attempts, $locked_until]);
    return [
        'attempts' => $attempts,
        'locked' => $locked_until > time(),
        'remaining' => max(0, $locked_until - time()),
        'attempts_left' => max(0, FWMS_MAX_LOGIN_ATTEMPTS - $attempts),
    ];
}

function fwms_login_lockout_clear(PDO $pdo, $ip = null) {
    ensure_login_attempts_table($pdo);
    $hash = fwms_login_ip_hash($ip);
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_hash = ?");
    $stmt->execute([$hash]);
}

// ==================================================
// 2c. BACKUP HELPERS
// ==================================================

function fwms_backup_tables() {
    return ['settings', 'users', 'workers', 'invoices', 'logs', 'worker_archives', 'additional_payments', 'login_attempts', 'contacts'];
}

function fwms_build_sql_backup(PDO $pdo) {
    $tables = fwms_backup_tables();
    $output = "-- FWMS System Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        try {
            $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetch();
            if (!$exists) continue;

            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $create['Create Table'] . ";\n\n";

            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $values = array_map(function ($v) use ($pdo) {
                    if (is_null($v)) return 'NULL';
                    return $pdo->quote($v);
                }, $row);
                $output .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
            }
            $output .= "\n\n";
        } catch (PDOException $e) {
            error_log("Backup skip $table: " . $e->getMessage());
        }
    }

    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $output;
}

/**
 * Write SQL (+ optional uploads zip) under backups/. Returns metadata array.
 */
function fwms_run_scheduled_backup(PDO $pdo, $root_dir = null, $keep_days = 14) {
    $root_dir = $root_dir ?: dirname(__FILE__);
    $backup_dir = $root_dir . '/backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    $htaccess = $backup_dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }

    $stamp = date('Y-m-d_His');
    $sql_name = "FWMS_Backup_{$stamp}.sql";
    $sql_path = $backup_dir . '/' . $sql_name;
    $sql = fwms_build_sql_backup($pdo);
    if (file_put_contents($sql_path, $sql) === false) {
        throw new Exception('Could not write SQL backup.');
    }

    $zip_name = null;
    $zip_path = null;
    $uploads = $root_dir . '/uploads';
    if (class_exists('ZipArchive') && is_dir($uploads)) {
        $zip_name = "FWMS_Uploads_{$stamp}.zip";
        $zip_path = $backup_dir . '/' . $zip_name;
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                $relative = 'uploads/' . substr($file->getPathname(), strlen($uploads) + 1);
                $zip->addFile($file->getPathname(), $relative);
            }
            $zip->close();
        } else {
            $zip_path = null;
            $zip_name = null;
        }
    }

    // Retention purge
    $keep_days = max(1, (int) $keep_days);
    $cutoff = time() - ($keep_days * 86400);
    foreach (glob($backup_dir . '/FWMS_Backup_*.sql') ?: [] as $old) {
        if (filemtime($old) < $cutoff) @unlink($old);
    }
    foreach (glob($backup_dir . '/FWMS_Uploads_*.zip') ?: [] as $old) {
        if (filemtime($old) < $cutoff) @unlink($old);
    }

    return [
        'sql_file' => $sql_name,
        'sql_path' => $sql_path,
        'sql_bytes' => filesize($sql_path),
        'uploads_zip' => $zip_name,
        'uploads_path' => $zip_path,
        'created_at' => date('c'),
    ];
}

/**
 * Lightweight health checks for monitoring.
 * @return array{status:string,checks:array}
 */
function fwms_health_checks(?PDO $pdo = null, $root_dir = null) {
    $root_dir = $root_dir ?: dirname(__FILE__);
    $checks = [];

    $db_ok = false;
    if ($pdo instanceof PDO) {
        try {
            $pdo->query('SELECT 1');
            $db_ok = true;
        } catch (Exception $e) {
            $db_ok = false;
        }
    }
    $checks['database'] = ['ok' => $db_ok];

    $uploads = $root_dir . '/uploads';
    $checks['uploads_writable'] = ['ok' => is_dir($uploads) && is_writable($uploads)];

    $storage = $root_dir . '/storage';
    if (!is_dir($storage)) @mkdir($storage, 0755, true);
    $checks['storage_writable'] = ['ok' => is_dir($storage) && is_writable($storage)];

    $backups = $root_dir . '/backups';
    if (!is_dir($backups)) @mkdir($backups, 0755, true);
    $checks['backups_writable'] = ['ok' => is_dir($backups) && is_writable($backups)];

    $free = @disk_free_space($root_dir);
    $checks['disk_space'] = [
        'ok' => $free === false ? true : ($free > 50 * 1024 * 1024),
        'free_mb' => $free === false ? null : (int) round($free / 1048576),
    ];

    $all_ok = true;
    foreach ($checks as $c) {
        if (empty($c['ok'])) $all_ok = false;
    }

    return [
        'status' => $all_ok ? 'ok' : 'degraded',
        'checks' => $checks,
        'time' => date('c'),
    ];
}

// ==================================================
// 3. DATABASE CLASS (PDO WRAPPER)
// ==================================================

class DB {
    public $pdo;
    private $cache = [];
    private $cache_ttl = 60; // seconds

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensure_workers_insurance_proof_column();
        $this->ensure_performance_indexes();
        ensure_login_attempts_table($this->pdo);
        $this->ensure_contacts_table();
    }

    /** Contacts directory for Bill To / Pay To and general address book. */
    private function ensure_contacts_table() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                company VARCHAR(150) NULL,
                phone VARCHAR(50) NULL,
                email VARCHAR(120) NULL,
                address TEXT NULL,
                notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_contacts_name (name),
                INDEX idx_contacts_company (company)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            error_log('DB schema note (contacts): ' . $e->getMessage());
        }
    }

    /** Adds insurance_proof when upgrading older databases (safe no-op if present). */
    private function ensure_workers_insurance_proof_column() {
        try {
            $q = $this->pdo->query("SHOW COLUMNS FROM workers LIKE 'insurance_proof'");
            if ($q && $q->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE workers ADD COLUMN insurance_proof VARCHAR(255) NULL AFTER insurance_expiry");
            }
        } catch (PDOException $e) {
            error_log('DB schema note (insurance_proof): ' . $e->getMessage());
        }
    }

    /** Add helpful indexes on live DBs (safe no-op if already present). */
    private function ensure_performance_indexes() {
        $wanted = [
            ['workers', 'idx_workers_permit_expiry', 'permit_expiry'],
            ['workers', 'idx_workers_visa_expiry', 'visa_expiry'],
            ['workers', 'idx_workers_stage', 'current_stage'],
            ['workers', 'idx_workers_category', 'category'],
            ['invoices', 'idx_inv_doc_no', 'doc_no'],
            ['invoices', 'idx_inv_date', 'invoice_date'],
            ['additional_payments', 'idx_ap_ref', 'ref_no'],
            ['additional_payments', 'idx_ap_date', 'payment_date'],
            ['additional_payments', 'idx_ap_worker', 'worker_id'],
            ['logs', 'idx_logs_time', 'timestamp'],
        ];
        foreach ($wanted as [$table, $name, $column]) {
            try {
                $chk = $this->pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
                $chk->execute([$name]);
                if ($chk->rowCount() > 0) continue;
                $col_chk = $this->pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $this->pdo->quote($column));
                if (!$col_chk || $col_chk->rowCount() === 0) continue;
                $this->pdo->exec("ALTER TABLE `$table` ADD INDEX `$name` (`$column`)");
            } catch (PDOException $e) {
                error_log("DB index note ($table.$name): " . $e->getMessage());
            }
        }
    }

    // --------------------------------------------------
    // CACHE HELPERS
    // --------------------------------------------------
    private function cache_get($key) {
        if (isset($this->cache[$key]) && (time() - $this->cache[$key]['ts']) < $this->cache_ttl) {
            return $this->cache[$key]['data'];
        }
        return null;
    }

    private function cache_set($key, $data) {
        $this->cache[$key] = ['data' => $data, 'ts' => time()];
    }

    private function cache_flush() {
        $this->cache = [];
    }

    // --------------------------------------------------
    // EXPIRY INTELLIGENCE (Permit / Visa / Insurance / CIDB)
    // --------------------------------------------------
    public function get_compliance_alerts() {
        $alerts = ['critical' => [], 'warning' => [], 'upcoming' => []];
        $date_fields = [
            'permit_expiry' => 'Permit',
            'visa_expiry' => 'Visa',
            'insurance_expiry' => 'Insurance',
            'cidb_expiry' => 'CIDB',
            'fomema_expiry' => 'FOMEMA',
        ];
        $stmt = $this->pdo->query("SELECT id, full_name, passport_number, permit_expiry, visa_expiry, insurance_expiry, cidb_expiry, fomema_expiry FROM workers");
        $workers = $stmt->fetchAll();
        $now = new DateTime();

        foreach ($workers as $w) {
            foreach ($date_fields as $field => $label) {
                if (empty($w->$field) || $w->$field == '0000-00-00' || $w->$field == '1970-01-01') continue;
                try {
                    $exp = new DateTime($w->$field);
                } catch (Exception $e) {
                    continue;
                }
                $diff = $now->diff($exp);
                $days = (int) $diff->days;
                if ($exp < $now) $days = -$days;

                $item = [
                    'id' => $w->id,
                    'name' => $w->full_name,
                    'passport' => $w->passport_number,
                    'label' => $label,
                    'date' => $w->$field,
                    'days' => $days,
                ];

                if ($days <= 30)       $alerts['critical'][] = $item;
                elseif ($days <= 60)   $alerts['warning'][]  = $item;
                elseif ($days <= 90)   $alerts['upcoming'][] = $item;
            }
        }
        foreach ($alerts as &$group) {
            usort($group, function($a, $b) { return $a['days'] - $b['days']; });
        }
        return $alerts;
    }

    // --------------------------------------------------
    // ANALYTICS & DASHBOARD (with caching)
    // --------------------------------------------------
    public function get_stats() {
        $cached = $this->cache_get('stats');
        if ($cached !== null) return $cached;

        $stats = [];
        $stats['total']          = $this->pdo->query("SELECT COUNT(*) FROM workers")->fetchColumn();
        $stats['expired']        = $this->pdo->query("SELECT COUNT(*) FROM workers WHERE permit_expiry > '1000-01-01' AND permit_expiry < CURDATE()")->fetchColumn();
        $stats['urgent']         = $this->pdo->query("SELECT COUNT(*) FROM workers WHERE permit_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 4 MONTH)")->fetchColumn();
        $stats['fomema_pending'] = $this->pdo->query("SELECT COUNT(*) FROM workers WHERE current_stage = 3")->fetchColumn();
        $stats['completed']      = $this->pdo->query("SELECT COUNT(*) FROM workers WHERE current_stage >= 8")->fetchColumn();

        // Buckets aligned to current wizard: 1 Reg, 3 FOMEMA, 4 Ins, 5 Levy, 7 Permit, 8+ Done
        $bucket = [0, 0, 0, 0, 0, 0];
        $res = $this->pdo->query("SELECT current_stage, COUNT(*) as count FROM workers GROUP BY current_stage")->fetchAll();
        foreach ($res as $r) {
            $idx = (int) $r->current_stage;
            $count = (int) $r->count;
            if ($idx <= 2) $bucket[0] += $count;
            elseif ($idx == 3) $bucket[1] += $count;
            elseif ($idx == 4) $bucket[2] += $count;
            elseif ($idx == 5 || $idx == 6) $bucket[3] += $count;
            elseif ($idx == 7) $bucket[4] += $count;
            else $bucket[5] += $count; // 8+
        }
        $stats['stage_dist'] = $bucket;
        $stats['stage_labels'] = ['Reg & Pay', 'FOMEMA', 'Insurance', 'Levy', 'Permit', 'CIDB/Done'];
        $stats['heatmap']    = $this->pdo->query("SELECT DATE(timestamp) as date, COUNT(*) as count FROM logs WHERE timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(timestamp)")->fetchAll();

        $this->cache_set('stats', $stats);
        return $stats;
    }

    public function get_recent_workers($limit = 10) {
        $stmt = $this->pdo->prepare("SELECT * FROM workers ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function get_expiring_workers($limit = 10) {
        $stmt = $this->pdo->prepare("SELECT * FROM workers WHERE permit_expiry > '1000-01-01' ORDER BY permit_expiry ASC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --------------------------------------------------
    // WORKER LISTING WITH PAGINATION
    // --------------------------------------------------
    public function get_all_workers($search = '', $cat = '', $start_date = '', $end_date = '', $stage = '', $limit = 0, $offset = 0) {
        $where = ["1=1"];
        $params = [];

        if ($search) {
            $terms = preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY);
            $search_parts = [];
            foreach ($terms as $t) {
                $search_parts[] = "(passport_number LIKE ? OR full_name LIKE ?)";
                $params[] = "%$t%"; $params[] = "%$t%";
            }
            if (!empty($search_parts)) $where[] = "(" . implode(' OR ', $search_parts) . ")";
        }

        if ($cat)  { $where[] = "category = ?"; $params[] = $cat; }
        if ($stage !== '') { $where[] = "current_stage = ?"; $params[] = intval($stage); }

        if (!empty($start_date) && !empty($end_date)) {
            $where[] = "permit_expiry BETWEEN ? AND ?";
            $params[] = $start_date; $params[] = $end_date;
        }

        $sql = "SELECT * FROM workers WHERE " . implode(' AND ', $where) .
               " ORDER BY (permit_expiry IS NULL OR permit_expiry <= '1000-01-01'), permit_expiry ASC";

        $stmt = $this->pdo->prepare($sql . ($limit > 0 ? " LIMIT ? OFFSET ?" : ""));
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        if ($limit > 0) {
            $stmt->bindValue($i++, (int) $limit, PDO::PARAM_INT);
            $stmt->bindValue($i++, (int) $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function count_workers($search = '', $cat = '', $start_date = '', $end_date = '', $stage = '') {
        $where = ["1=1"];
        $params = [];

        if ($search) {
            $terms = preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY);
            $search_parts = [];
            foreach ($terms as $t) {
                $search_parts[] = "(passport_number LIKE ? OR full_name LIKE ?)";
                $params[] = "%$t%"; $params[] = "%$t%";
            }
            if (!empty($search_parts)) $where[] = "(" . implode(' OR ', $search_parts) . ")";
        }

        if ($cat)  { $where[] = "category = ?"; $params[] = $cat; }
        if ($stage !== '') { $where[] = "current_stage = ?"; $params[] = intval($stage); }

        if (!empty($start_date) && !empty($end_date)) {
            $where[] = "permit_expiry BETWEEN ? AND ?";
            $params[] = $start_date; $params[] = $end_date;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM workers WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function get_worker($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM workers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // --------------------------------------------------
    // FINANCIAL & DYNAMIC PAYMENTS
    // --------------------------------------------------
    public function get_additional_payments($worker_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM additional_payments WHERE worker_id = ? ORDER BY payment_date ASC");
        $stmt->execute([$worker_id]);
        return $stmt->fetchAll();
    }

    public function update_balance($id) {
        $w = $this->get_worker($id);
        if (!$w) return;
        $adds = $this->get_additional_payments($id);
        $total_paid = 0;
        foreach ($adds as $a) { $total_paid += (float)($a->amount ?? 0); }
        $balance = (float)($w->total_payable ?? 0) - $total_paid;
        $stmt = $this->pdo->prepare("UPDATE workers SET balance_due = ? WHERE id = ?");
        $stmt->execute([$balance, $id]);
    }

    // --------------------------------------------------
    // SECURITY, FRAUD & RENEWAL LOGIC
    // --------------------------------------------------
    public function check_duplicate_passport($passport, $exclude_id = 0) {
        $stmt = $this->pdo->prepare("SELECT full_name FROM workers WHERE passport_number = ? AND id != ? LIMIT 1");
        $stmt->execute([$passport, $exclude_id]);
        return $stmt->fetch();
    }

    public function check_global_receipt_usage($receipt, $exclude_worker_id = 0) {
        $receipt = trim((string) $receipt);
        if ($receipt === '') return false;

        $stmt1 = $this->pdo->prepare("SELECT w.id, w.full_name, w.passport_number, 'Payment List' as source FROM additional_payments ap JOIN workers w ON ap.worker_id = w.id WHERE ap.ref_no = ? AND ap.worker_id != ? LIMIT 1");
        $stmt1->execute([$receipt, (int) $exclude_worker_id]);
        $res1 = $stmt1->fetch();
        if ($res1) return $res1;

        $stmt2 = $this->pdo->prepare("SELECT id, client_name as full_name, doc_no as passport_number, 'Invoice History' as source FROM invoices WHERE doc_no = ? LIMIT 1");
        $stmt2->execute([$receipt]);
        $res2 = $stmt2->fetch();
        if ($res2) return $res2;

        return false;
    }

    public function archive_and_reset_worker($id) {
        $this->pdo->beginTransaction();
        try {
            $worker = $this->get_worker($id);
            if (!$worker) { $this->pdo->rollBack(); return false; }

            $adds = $this->get_additional_payments($id);
            $snapshot = ['worker_details' => $worker, 'additional_payments' => $adds];

            $stmt = $this->pdo->prepare("INSERT INTO worker_archives (worker_id, passport_number, full_name, archive_data) VALUES (?, ?, ?, ?)");
            $stmt->execute([$worker->id, $worker->passport_number, $worker->full_name, json_encode($snapshot)]);

            $reset_data = [
                'current_stage' => 1, 'fomema_status' => 'Pending', 'fomema_code' => null,
                'fomema_expiry' => null, 'fomema_proof' => null, 'insurance_policy' => null,
                'insurance_provider' => null, 'insurance_expiry' => null, 'insurance_proof' => null, 'levy_status' => null,
                'levy_reference' => null, 'levy_expiry' => null, 'permit_status' => null,
                'permit_number' => null, 'permit_issue' => null, 'permit_expiry' => null,
                'epass_worker_proof' => null, 'cidb_status' => 'Pending', 'cidb_category' => null,
                'cidb_expiry' => null, 'cidb_proof' => null,
                'balance_due' => (float)($worker->total_payable ?? 0)
            ];

            $this->save_worker($reset_data, $id);
            $this->pdo->prepare("DELETE FROM additional_payments WHERE worker_id = ?")->execute([$id]);
            $this->pdo->commit();
            $this->cache_flush();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception("SQL Error: " . $e->getMessage());
        }
    }

    // --------------------------------------------------
    // CRUD OPERATIONS
    // --------------------------------------------------
    public function delete_invoice($id) {
        $stmt = $this->pdo->prepare("DELETE FROM invoices WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function get_worker_archives($worker_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM worker_archives WHERE worker_id = ? ORDER BY archive_date DESC");
        $stmt->execute([$worker_id]);
        return $stmt->fetchAll();
    }

    public function delete_worker_archive($archive_id, $worker_id) {
        $stmt = $this->pdo->prepare("DELETE FROM worker_archives WHERE id = ? AND worker_id = ?");
        $stmt->execute([(int)$archive_id, (int)$worker_id]);
        if ($stmt->rowCount() > 0) {
            $this->cache_flush();
            return true;
        }
        return false;
    }

    public function save_worker($data, $id = 0) {
        foreach ($data as $key => $val) {
            if ($val === '') $data[$key] = null;
        }
        if ($id > 0) {
            $f = ""; $v = [];
            foreach ($data as $k => $val) { $f .= "$k = ?, "; $v[] = $val; }
            $f = rtrim($f, ", "); $v[] = $id;
            $this->pdo->prepare("UPDATE workers SET $f WHERE id = ?")->execute($v);
            $this->cache_flush();
            return $id;
        } else {
            $cols = implode(", ", array_keys($data));
            $p = implode(", ", array_fill(0, count($data), '?'));
            $this->pdo->prepare("INSERT INTO workers ($cols) VALUES ($p)")->execute(array_values($data));
            $this->cache_flush();
            return $this->pdo->lastInsertId();
        }
    }

    public function delete_worker($id) {
        $worker = $this->get_worker($id);
        if (!$worker) return false;

        $stmt = $this->pdo->prepare("SELECT fomema_proof, insurance_proof, cidb_proof, epass_worker_proof, passport_copy_proof FROM workers WHERE id = ?");
        $stmt->execute([$id]); $w = $stmt->fetch();
        if ($w) {
            foreach ([$w->fomema_proof, $w->insurance_proof, $w->cidb_proof, $w->epass_worker_proof, $w->passport_copy_proof] as $f) {
                if ($f) { $path = 'uploads/' . basename($f); if (file_exists($path)) @unlink($path); }
            }
        }
        $this->pdo->prepare("DELETE FROM workers WHERE id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM worker_archives WHERE worker_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM additional_payments WHERE worker_id = ?")->execute([$id]);
        $this->cache_flush();
        $this->log('DELETE_WORKER', "Deleted worker: {$worker->full_name} (Passport: {$worker->passport_number})");
        return true;
    }

    // --------------------------------------------------
    // INVOICES & SETTINGS
    // --------------------------------------------------
    public function insert_invoice($d) {
        $c = implode(", ", array_keys($d)); $p = implode(", ", array_fill(0, count($d), '?'));
        $this->pdo->prepare("INSERT INTO invoices ($c) VALUES ($p)")->execute(array_values($d));
        return $this->pdo->lastInsertId();
    }

    public function update_invoice($d, $id) {
        $f = ""; $v = []; foreach ($d as $k => $val) { $f .= "$k = ?, "; $v[] = $val; }
        $f = rtrim($f, ", "); $v[] = $id;
        return $this->pdo->prepare("UPDATE invoices SET $f WHERE id = ?")->execute($v);
    }

    public function get_setting($key, $default = '') {
        $cached = $this->cache_get('setting_' . $key);
        if ($cached !== null) return $cached;
        $stmt = $this->pdo->prepare("SELECT meta_value FROM settings WHERE meta_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetch();
        $val = $res ? $res->meta_value : $default;
        $this->cache_set('setting_' . $key, $val);
        return $val;
    }

    public function update_setting($key, $value) {
        $stmt = $this->pdo->prepare("INSERT INTO settings (meta_key, meta_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_value = ?");
        $result = $stmt->execute([$key, $value, $value]);
        $this->cache_flush();
        return $result;
    }

    // --------------------------------------------------
    // USER MANAGEMENT
    // --------------------------------------------------
    public function get_users() {
        return $this->pdo->query("SELECT * FROM users ORDER BY role ASC")->fetchAll();
    }

    // --------------------------------------------------
    // CONTACTS DIRECTORY
    // --------------------------------------------------
    public function get_contacts($search = '') {
        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt = $this->pdo->prepare(
                "SELECT * FROM contacts
                 WHERE name LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ?
                 ORDER BY name ASC"
            );
            $stmt->execute([$like, $like, $like, $like]);
            return $stmt->fetchAll();
        }
        return $this->pdo->query("SELECT * FROM contacts ORDER BY name ASC")->fetchAll();
    }

    public function get_contact_names() {
        try {
            return $this->pdo->query(
                "SELECT name FROM contacts WHERE name IS NOT NULL AND name != '' ORDER BY name ASC"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function save_contact(array $data, $id = 0) {
        $id = (int) $id;
        $name = sanitize_text_field($data['name'] ?? '');
        if ($name === '') return false;

        $row = [
            'name' => $name,
            'company' => sanitize_text_field($data['company'] ?? '') ?: null,
            'phone' => sanitize_text_field($data['phone'] ?? '') ?: null,
            'email' => sanitize_text_field($data['email'] ?? '') ?: null,
            'address' => sanitize_text_field($data['address'] ?? '') ?: null,
            'notes' => sanitize_text_field($data['notes'] ?? '') ?: null,
        ];

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE contacts SET name=?, company=?, phone=?, email=?, address=?, notes=? WHERE id=?"
            );
            $ok = $stmt->execute([
                $row['name'], $row['company'], $row['phone'], $row['email'], $row['address'], $row['notes'], $id
            ]);
            if ($ok) $this->log('UPDATE_CONTACT', "Updated contact: {$row['name']} (#$id)");
            return $ok ? $id : false;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO contacts (name, company, phone, email, address, notes) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ok = $stmt->execute([
            $row['name'], $row['company'], $row['phone'], $row['email'], $row['address'], $row['notes']
        ]);
        if (!$ok) return false;
        $new_id = (int) $this->pdo->lastInsertId();
        $this->log('CREATE_CONTACT', "Created contact: {$row['name']} (#$new_id)");
        return $new_id;
    }

    public function delete_contact($id) {
        $id = (int) $id;
        $stmt = $this->pdo->prepare("SELECT name FROM contacts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $contact = $stmt->fetch();
        if (!$contact) return false;
        $del = $this->pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $ok = $del->execute([$id]);
        if ($ok) $this->log('DELETE_CONTACT', "Deleted contact: {$contact->name} (#$id)");
        return $ok;
    }

    public function save_user($u, $an, $p, $r, $can_edit, $can_delete, $id = 0) {
        $check = $this->pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$u, $id]);
        if ($check->fetch()) return false;

        $edit_val = ($can_edit === 'true' || $can_edit === 1) ? 1 : 0;
        $del_val  = ($can_delete === 'true' || $can_delete === 1) ? 1 : 0;

        if ($id > 0) {
            if ($p) {
                $h = password_hash($p, PASSWORD_DEFAULT);
                $this->pdo->prepare("UPDATE users SET username=?, account_name=?, password=?, role=?, can_edit=?, can_delete=? WHERE id=?")->execute([$u, $an, $h, $r, $edit_val, $del_val, $id]);
            } else {
                $this->pdo->prepare("UPDATE users SET username=?, account_name=?, role=?, can_edit=?, can_delete=? WHERE id=?")->execute([$u, $an, $r, $edit_val, $del_val, $id]);
            }
        } else {
            if (!$p) return false;
            $h = password_hash($p, PASSWORD_DEFAULT);
            $this->pdo->prepare("INSERT INTO users (username, account_name, password, role, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)")->execute([$u, $an, $h, $r, $edit_val, $del_val]);
        }
        return true;
    }

    public function delete_user($id) {
        if ($id == $_SESSION['user_id']) return false;
        $stmt = $this->pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        $result = $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        if ($result && $user) {
            $this->log('DELETE_USER', "Deleted user: {$user->username} (Role: {$user->role})");
        }
        return $result;
    }

    // --------------------------------------------------
    // LOGGING
    // --------------------------------------------------
    public function log($a, $d) {
        $stmt = $this->pdo->prepare("INSERT INTO logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['user_name'] ?? 'System', $_SESSION['user_role'] ?? 'system', $a, $d]);
    }

    public function get_logs($limit = 20, $offset = 0) {
        $this->purge_old_logs();
        if ($limit == -1) return $this->pdo->query("SELECT * FROM logs ORDER BY timestamp DESC")->fetchAll();
        $stmt = $this->pdo->prepare("SELECT * FROM logs ORDER BY timestamp DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function get_total_logs() { return $this->pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn(); }

    /** Delete audit logs older than 90 days (runs at most once per request cache). */
    public function purge_old_logs($days = 90) {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $days = max(30, (int) $days);
            $this->pdo->prepare("DELETE FROM logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
        } catch (PDOException $e) {
            error_log('Log retention note: ' . $e->getMessage());
        }
    }

    // --------------------------------------------------
    // EMAIL NOTIFICATIONS (Compliance Alerts)
    // --------------------------------------------------
    public function send_compliance_email($to, $subject, $body) {
        if (empty(SMTP_FROM) || empty($to)) return false;
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        return mail($to, $subject, $body, $headers);
    }

    public function get_notification_email() {
        return $this->get_setting('notification_email', '');
    }
}

// ==================================================
// 4. INITIALIZE DATABASE INSTANCE
// ==================================================
$db = null;
if (!FWMS_SKIP_DB && isset($pdo) && $pdo instanceof PDO) {
    $db = new DB($pdo);
}