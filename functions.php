<?php
/**
 * Standalone Core Logic & Security Helpers
 * Location: root/functions.php
 * Version: 4.2.2 (MySQL Strict Mode & Date Integrity Fix)
 */
require_once 'config.php';

// ==================================================
// 1. SECURITY & COMPATIBILITY HELPERS
// ==================================================

/**
 * Clean strings to prevent XSS on INPUT
 */
function sanitize_text_field($str) {
    if (is_array($str)) return $str;
    return htmlspecialchars(strip_tags(trim($str ?? '')), ENT_QUOTES, 'UTF-8');
}

// --- PERMISSION HELPERS ---
    function current_user_can_edit() {
        if (!isset($_SESSION['user_id'])) return false;
        if ($_SESSION['user_role'] === 'admin') return true; // Admin always can
        // Fetch fresh permission from DB to be safe
        global $db; // Assuming $db is available in global scope here, otherwise use session if stored
        // Ideally, store this in SESSION at login, but for now we query or rely on session if we update login
        return (isset($_SESSION['can_edit']) && $_SESSION['can_edit'] == 1);
    }

    function current_user_can_delete() {
        if (!isset($_SESSION['user_id'])) return false;
        if ($_SESSION['user_role'] === 'admin') return true;
        return (isset($_SESSION['can_delete']) && $_SESSION['can_delete'] == 1);
    }

/**
 * Escape for Output (Prevents XSS on DISPLAY)
 */
function e($str) {
    if (is_null($str)) return '';
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper for Select Dropdowns
 */
function selected($val1, $val2, $echo = true) {
    $out = ($val1 == $val2) ? 'selected="selected"' : '';
    if($echo) echo $out;
    return $out;
}

/**
 * Date Formatter (DD/MM/YYYY)
 * Hardened to handle MySQL Strict Mode '0000-00-00' or empty values
 */
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

/**
 * Check Permissions
 */
function current_user_can_admin() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

function current_user_is_staff() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'staff');
}




// ==================================================
// 2. DATABASE CLASS (PDO WRAPPER)
// ==================================================

class DB {
    public $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * EXPIRY INTELLIGENCE: Scans all compliance dates
     */
    /**
 * UPDATED: EXPIRY INTELLIGENCE (Visa Only)
 * Location: root/functions.php
 */
public function get_compliance_alerts() {
    $alerts = [
        'critical' => [], // < 30 days or Expired
        'warning'  => [], // 30 - 60 days
        'upcoming' => []  // 60 - 90 days
    ];

    // --- MODIFIED: Only monitor Visa Expiry ---
    $date_fields = [
        'visa_expiry' => 'Visa'
    ];

    // Fetch only necessary fields
    $stmt = $this->pdo->query("SELECT id, full_name, passport_number, visa_expiry FROM workers");
    $workers = $stmt->fetchAll();

    $now = new DateTime();

    foreach ($workers as $w) {
        foreach ($date_fields as $field => $label) {
            // Skip if no date is set
            if (empty($w->$field) || $w->$field == '0000-00-00' || $w->$field == '1970-01-01') continue;

            $exp = new DateTime($w->$field);
            $diff = $now->diff($exp);
            $days = $diff->days;
            
            // If the date is in the past, make days negative
            if ($exp < $now) {
                $days = -$days;
            }

            $item = [
                'id' => $w->id,
                'name' => $w->full_name,
                'passport' => $w->passport_number,
                'label' => $label,
                'date' => $w->$field,
                'days' => $days
            ];

            // Categorize based on Visa urgency
            if ($days <= 30) {
                $alerts['critical'][] = $item;
            } elseif ($days <= 60) {
                $alerts['warning'][] = $item;
            } elseif ($days <= 90) {
                $alerts['upcoming'][] = $item;
            }
        }
    }

    // Sort groups so the most urgent appear first
    foreach ($alerts as &$group) {
        usort($group, function($a, $b) { return $a['days'] - $b['days']; });
    }

    return $alerts;
}

    // ==================================================
    // 3. ANALYTICS & DASHBOARD METHODS
    // ==================================================

    public function get_stats() {
        $stats = [];
        $stats['total'] = $this->pdo->query("SELECT COUNT(*) FROM workers")->fetchColumn();
        
        // Strict Mode Fix: Use comparison > min date instead of != 0000-00-00
        $stats['expired'] = $this->pdo->query("SELECT COUNT(*) FROM workers WHERE permit_expiry > '1000-01-01' AND permit_expiry < CURDATE()")->fetchColumn();
        $stats['urgent'] = $this->pdo->query("SELECT COUNT(*) FROM workers WHERE permit_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 4 MONTH)")->fetchColumn();
        $stats['fomema_pending'] = $this->pdo->query("SELECT COUNT(*) FROM workers WHERE current_stage = 3")->fetchColumn();
        $stats['completed'] = $this->pdo->query("SELECT COUNT(*) FROM workers WHERE current_stage >= 8")->fetchColumn();
        
        $stages = array_fill(1, 9, 0);
        $res = $this->pdo->query("SELECT current_stage, COUNT(*) as count FROM workers GROUP BY current_stage")->fetchAll();
        foreach($res as $r) { 
            $idx = (int)$r->current_stage;
            if($idx >= 1 && $idx <= 9) $stages[$idx] = (int)$r->count; 
        }
        $stats['stage_dist'] = array_values($stages);
        $stats['heatmap'] = $this->pdo->query("SELECT DATE(timestamp) as date, COUNT(*) as count FROM logs WHERE timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(timestamp)")->fetchAll();
        
        return $stats;
    }

    public function get_recent_workers($limit = 10) {
        $stmt = $this->pdo->prepare("SELECT * FROM workers ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function get_expiring_workers($limit = 10) {
        // Strict Mode Fix: Ensure date is valid before sorting
        $stmt = $this->pdo->prepare("SELECT * FROM workers WHERE permit_expiry > '1000-01-01' ORDER BY permit_expiry ASC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Support Category Filtering and Expiry Sorting (STRICT MODE SAFE)
    public function get_all_workers($search = '', $cat = '', $start_date = '', $end_date = '') {
        $where = ["1=1"]; 
        $params = [];
        
        if ($search) {
            $terms = preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY);
            $search_parts = [];
            foreach($terms as $t) { 
                $search_parts[] = "(passport_number LIKE ? OR full_name LIKE ?)"; 
                $params[]="%$t%"; $params[]="%$t%"; 
            }
            if (!empty($search_parts)) $where[] = "(" . implode(' OR ', $search_parts) . ")";
        }

        if ($cat) {
            $where[] = "category = ?";
            $params[] = $cat;
        }

        if (!empty($start_date) && !empty($end_date)) { 
            $where[] = "permit_expiry BETWEEN ? AND ?"; 
            $params[] = $start_date; $params[] = $end_date; 
        }

        // Fix: Use '1000-01-01' check instead of '0000-00-00' to avoid Error 1525
        $sql = "SELECT * FROM workers WHERE " . implode(' AND ', $where) . " 
                ORDER BY (permit_expiry IS NULL OR permit_expiry <= '1000-01-01'), permit_expiry ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function get_worker($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM workers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // ==================================================
    // 4. FINANCIAL & DYNAMIC PAYMENTS
    // ==================================================

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
    foreach($adds as $a) { 
        $total_paid += (float)($a->amount ?? 0); 
    }
    
    $balance = (float)($w->total_payable ?? 0) - $total_paid;
    
    $stmt = $this->pdo->prepare("UPDATE workers SET balance_due = ? WHERE id = ?");
    $stmt->execute([$balance, $id]);
}

    // ==================================================
    // 5. SECURITY, FRAUD & RENEWAL LOGIC
    // ==================================================

    public function check_duplicate_passport($passport, $exclude_id = 0) {
        // We check if the passport exists for any ID OTHER than the one we are editing
        $stmt = $this->pdo->prepare("SELECT full_name FROM workers WHERE passport_number = ? AND id != ? LIMIT 1");
        $stmt->execute([$passport, $exclude_id]);
        return $stmt->fetch();
    }

   /**
 * Check if a receipt reference is already used anywhere in the system.
 * Updated for Version 4.3.0 (Consolidated dynamic payments)
 */
public function check_global_receipt_usage($receipt) {
    // 1. Check Additional Payments table (The dynamic rows in Step 1)
    // We join with workers to get the owner's name for the alert popup
    $stmt1 = $this->pdo->prepare("
        SELECT w.id, w.full_name, w.passport_number, 'Payment List' as source 
        FROM additional_payments ap
        JOIN workers w ON ap.worker_id = w.id
        WHERE ap.ref_no = ? LIMIT 1
    ");
    $stmt1->execute([$receipt]);
    $res1 = $stmt1->fetch();
    if ($res1) return $res1;

    // 2. Check Manual Invoices / Receipts table
    $stmt2 = $this->pdo->prepare("
        SELECT id, client_name as full_name, doc_no as passport_number, 'Invoice History' as source 
        FROM invoices 
        WHERE doc_no = ? LIMIT 1
    ");
    $stmt2->execute([$receipt]);
    $res2 = $stmt2->fetch();
    if ($res2) return $res2;

    return false;
}

    /**
     * RENEWAL LOGIC: Safe Version 4.2.6
     */
   public function archive_and_reset_worker($id) {
        try {
            $worker = $this->get_worker($id);
            if (!$worker) return false;

            // 1. Fetch payments to include in this specific snapshot
            $adds = $this->get_additional_payments($id);

            $snapshot =[
                'worker_details' => $worker,
                'additional_payments' => $adds
            ];

            // 2. INSERT into history
            $stmt = $this->pdo->prepare("INSERT INTO worker_archives (worker_id, passport_number, full_name, archive_data) VALUES (?, ?, ?, ?)");
            $stmt->execute([$worker->id, $worker->passport_number, $worker->full_name, json_encode($snapshot)]);

            // 3. Reset the worker for the new year (Updated with V5 Columns)
            $reset_data =[
                'current_stage'      => 1,
                // FOMEMA
                'fomema_status'      => 'Pending', 
                'fomema_code'        => null, 
                'fomema_expiry'      => null, // V5 Renamed from fomema_date
                'fomema_proof'       => null,
                // Insurance
                'insurance_policy'   => null, 
                'insurance_provider' => null, 
                'insurance_expiry'   => null,
                // Levy
                'levy_status'        => null,
                'levy_reference'     => null, 
                'levy_expiry'        => null,
                // Permit
                'permit_status'      => null,
                'permit_number'      => null, 
                'permit_issue'       => null, 
                'permit_expiry'      => null, 
                'epass_worker_proof' => null,
                // CIDB
                'cidb_status'        => 'Pending', 
                'cidb_category'      => null,
                'cidb_expiry'        => null, 
                'cidb_proof'         => null,
                // Financials
                'balance_due'        => (float)($worker->total_payable ?? 0)
            ];

            // Execute the reset
            $this->save_worker($reset_data, $id);

            // 4. Clear old payments from Step 1
            $this->pdo->prepare("DELETE FROM additional_payments WHERE worker_id = ?")->execute([$id]);

            return true;

        } catch (PDOException $e) {
            // If it crashes, throw the exact MySQL error back to api.php
            throw new Exception("SQL Error: " . $e->getMessage());
        }
    }

/**
     * Delete a manual document/invoice from the database
     */
    public function delete_invoice($id) { 
        $stmt = $this->pdo->prepare("DELETE FROM invoices WHERE id = ?");
        return $stmt->execute([$id]); 
    }

    public function get_worker_archives($worker_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM worker_archives WHERE worker_id = ? ORDER BY archive_date DESC");
        $stmt->execute([$worker_id]);
        return $stmt->fetchAll();
    }

    // ==================================================
    // 6. CRUD OPERATIONS
    // ==================================================

    public function save_worker($data, $id = 0) {
        foreach($data as $key => $val) {
            if ($val === '') $data[$key] = null;
        }

        if ($id > 0) {
            $f = ""; $v = []; 
            foreach ($data as $k => $val) { 
                $f .= "$k = ?, "; 
                $v[] = $val; 
            }
            $f = rtrim($f, ", "); $v[] = $id;
            $stmt = $this->pdo->prepare("UPDATE workers SET $f WHERE id = ?");
            $stmt->execute($v);
            return $id;
        } else {
            $cols = implode(", ", array_keys($data)); 
            $p = implode(", ", array_fill(0, count($data), '?'));
            $stmt = $this->pdo->prepare("INSERT INTO workers ($cols) VALUES ($p)");
            $stmt->execute(array_values($data));
            return $this->pdo->lastInsertId();
        }
    }

    public function delete_worker($id) {
        $stmt = $this->pdo->prepare("SELECT fomema_proof, cidb_proof FROM workers WHERE id = ?");
        $stmt->execute([$id]); $w = $stmt->fetch();
        if ($w) { 
            foreach ([$w->fomema_proof,$w->cidb_proof] as $f) { 
                if ($f) { 
                    $filename = basename($f);
                    $path = 'uploads/'.$filename; 
                    if(file_exists($path)) @unlink($path); 
                } 
            } 
        }
        $this->pdo->prepare("DELETE FROM workers WHERE id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM worker_archives WHERE worker_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM additional_payments WHERE worker_id = ?")->execute([$id]);
    }

    // ==================================================
    // 7. INVOICES & SETTINGS
    // ==================================================

    public function insert_invoice($d) {
        $c = implode(", ", array_keys($d)); $p = implode(", ", array_fill(0, count($d), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO invoices ($c) VALUES ($p)");
        $stmt->execute(array_values($d));
        return $this->pdo->lastInsertId();
    }

    public function update_invoice($d, $id) {
        $f = ""; $v = []; foreach ($d as $k => $val) { $f .= "$k = ?, "; $v[] = $val; }
        $f = rtrim($f, ", "); $v[] = $id;
        return $this->pdo->prepare("UPDATE invoices SET $f WHERE id = ?")->execute($v);
    }

    public function get_setting($key, $default = '') {
        $stmt = $this->pdo->prepare("SELECT meta_value FROM settings WHERE meta_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetch();
        return $res ? $res->meta_value : $default;
    }

    public function update_setting($key, $value) {
        $stmt = $this->pdo->prepare("INSERT INTO settings (meta_key, meta_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_value = ?");
        return $stmt->execute([$key, $value, $value]);
    }

    // ==================================================
    // 8. USER MANAGEMENT
    // ==================================================

    public function get_users() { 
        return $this->pdo->query("SELECT * FROM users ORDER BY role ASC")->fetchAll(); 
    }

    // --- UPDATED SAVE USER (With Permissions) ---
    public function save_user($u, $an, $p, $r, $can_edit, $can_delete, $id = 0) {
        $check = $this->pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$u, $id]);
        if($check->fetch()) return false;

        $edit_val = ($can_edit === 'true' || $can_edit === 1) ? 1 : 0;
        $del_val  = ($can_delete === 'true' || $can_delete === 1) ? 1 : 0;

        if ($id > 0) {
            if ($p) { 
                $h = password_hash($p, PASSWORD_DEFAULT); 
                $sql = "UPDATE users SET username=?, account_name=?, password=?, role=?, can_edit=?, can_delete=? WHERE id=?";
                $this->pdo->prepare($sql)->execute([$u, $an, $h, $r, $edit_val, $del_val, $id]);
            } else { 
                $sql = "UPDATE users SET username=?, account_name=?, role=?, can_edit=?, can_delete=? WHERE id=?";
                $this->pdo->prepare($sql)->execute([$u, $an, $r, $edit_val, $del_val, $id]);
            }
        } else {
            if(!$p) return false;
            $h = password_hash($p, PASSWORD_DEFAULT); 
            $sql = "INSERT INTO users (username, account_name, password, role, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)";
            $this->pdo->prepare($sql)->execute([$u, $an, $h, $r, $edit_val, $del_val]);
        }
        return true;
    }

    public function delete_user($id) { 
        if($id == $_SESSION['user_id']) return false; 
        return $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]); 
    }

    // ==================================================
    // 9. LOGGING
    // ==================================================

    public function log($a, $d) {
        $stmt = $this->pdo->prepare("INSERT INTO logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['user_name'] ?? 'System', $_SESSION['user_role'] ?? 'system', $a, $d]);
    }

    public function get_logs($limit = 20, $offset = 0) {
        if ($limit == -1) return $this->pdo->query("SELECT * FROM logs ORDER BY timestamp DESC")->fetchAll();
        $stmt = $this->pdo->prepare("SELECT * FROM logs ORDER BY timestamp DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function get_total_logs() { return $this->pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn(); }
}

// 10. INITIALIZE DATABASE INSTANCE
$db = new DB($pdo);