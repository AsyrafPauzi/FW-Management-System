<?php
/**
 * Standalone API Handler - FWMS System
 * Location: root/api.php
 * Version: 4.2.0 (Unified Reporting, Financials & Identity)
 */
require_once 'functions.php';
header('Content-Type: application/json');

// 1. Session Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'data' => 'Unauthorized Access']);
    exit;
}

// 2. CSRF Security Check
$headers = apache_request_headers();
$request_token = $headers['X-CSRF-TOKEN'] ?? $_POST['csrf_token'] ?? '';

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $request_token)) {
    echo json_encode(['success' => false, 'data' => 'Security Token Mismatch']);
    exit;
}


$action = $_POST['action'] ?? '';

if ($action === 'get_invoice_by_no') {
    $doc_no = sanitize_text_field($_POST['doc_no'] ?? '');
    $stmt = $db->pdo->prepare("SELECT * FROM invoices WHERE doc_no = ? LIMIT 1");
    $stmt->execute([$doc_no]);
    $res = $stmt->fetch();

    if ($res) {
        echo json_encode(['success' => true, 'data' => $res]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'bulk_import_workers') {
    if ($_SESSION['user_role'] !== 'admin') exit;
    
    $workers = json_decode($_POST['workers'], true);
    $count = 0;
    $duplicates = 0;

    foreach ($workers as $w) {
        $data = [
            'passport_number' => sanitize_text_field($w['Passport'] ?? ''),
            'full_name'       => sanitize_text_field($w['Name'] ?? ''),
            'category'        => sanitize_text_field($w['Category'] ?? 'Calling Visa'),
            'kwsp_no'         => sanitize_text_field($w['KWSP'] ?? ''),
            'nationality'     => sanitize_text_field($w['Nationality'] ?? 'Bangladesh'),
            'gender'          => sanitize_text_field($w['Gender'] ?? 'Male'),
            'dob'             => sanitize_text_field($w['BirthDate'] ?? null),
            'passport_expiry' => sanitize_text_field($w['PassportExpiry'] ?? null),
            'total_payable'   => floatval($w['TotalPayable'] ?? 0),
            'current_stage'   => 1,
            'created_by'      => $_SESSION['user_name']
        ];

        if(!empty($data['passport_number'])) {
            $exists = $db->check_duplicate_passport($data['passport_number']);
            if(!$exists) {
                $new_id = $db->save_worker($data, 0);
                // Initial balance set
                $db->update_balance($new_id);
                $count++;
            } else { $duplicates++; }
        }
    }
    echo json_encode(['success' => true, 'data' => $count . " imported. " . $duplicates . " skipped."]);
    exit;
}

// =======================================================
// 1. WORKER MANAGEMENT (SAVE / DYNAMIC PAYMENTS / STAGE)
// =======================================================

if ($action === 'save_worker') {
    try {
        $id = intval($_POST['worker_id']);
        $passport = sanitize_text_field($_POST['passport_number'] ?? '');

        // 1. Backend block for duplicate Passport
        if(!empty($passport)) {
            $dup = $db->check_duplicate_passport($passport, $id);
            if ($dup) {
                throw new Exception("CRITICAL ERROR: Passport $passport is already assigned to " . $dup->full_name);
            }
        }

        // --- DATA MAPPING ---
        $data = [];
        
        $text_fields = [
            'passport_number', 'full_name', 'kwsp_no', 'nationality', 'gender', 'category',
            'fomema_status', 'fomema_code', 
            'insurance_policy', 'insurance_provider', 
            'levy_reference', 'levy_status', 'phone_number', 
            'permit_number', 'permit_status', // <--- CHECK THIS LINE
            'cidb_card_no', 'cidb_status', 'cidb_category'
        ];

        // Date Fields
        $date_fields = [
            'dob', 'passport_expiry', 'visa_expiry', 
            'fomema_expiry', 'insurance_expiry', 
            'levy_expiry', 'permit_issue', 'permit_expiry', 'cidb_expiry'
        ];

        // Numeric Fields
        $num_fields  = ['total_payable'];

        foreach($text_fields as $f) {
            if(isset($_POST[$f])) $data[$f] = sanitize_text_field($_POST[$f]);
        }

        foreach($date_fields as $f) {
            if(isset($_POST[$f])) {
                $val = trim($_POST[$f]);
                $data[$f] = ($val === '' || $val === '0000-00-00') ? null : $val;
            }
        }

        foreach($num_fields as $f) {
            if(isset($_POST[$f])) {
                $val = $_POST[$f];
                $data[$f] = ($val === '') ? 0 : floatval($val);
            }
        }

        // --- FILE UPLOADS (Hardened) ---
        $uploads_dir = 'uploads/';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

        foreach (['fomema_proof', 'cidb_proof', 'epass_worker_proof', 'passport_copy_proof'] as $file_key) {
    if (isset($_FILES[$file_key]) && !empty($_FILES[$file_key]['name']) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                
                $file_tmp  = $_FILES[$file_key]['tmp_name'];
                $file_orig = $_FILES[$file_key]['name'];
                $ext       = strtolower(pathinfo($file_orig, PATHINFO_EXTENSION));

                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                if (!in_array($ext, $allowed)) throw new Exception("Invalid file type: $ext");

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file_tmp);
                finfo_close($finfo);
                $valid_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
                if (!in_array($mime, $valid_mimes)) throw new Exception("Security alert: MIME mismatch");

                $safe_filename = bin2hex(random_bytes(10)) . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file_tmp, $uploads_dir . $safe_filename)) {
                    $data[$file_key] = BASE_URL . $uploads_dir . $safe_filename;
                }
            }
        }

        // --- STAGE LOGIC (V4 skips Stage 6) ---
        $current_stage = intval($_POST['current_stage']);
        if (isset($_POST['advance_stage']) && $_POST['advance_stage'] == 'true') {
            $next_stage = ($current_stage >= 8) ? 8 : $current_stage + 1;
            if($next_stage == 6) $next_stage = 7;
            $data['current_stage'] = $next_stage;
        } else {
            $data['current_stage'] = $current_stage;
        }

        if ($id == 0) $data['created_by'] = $_SESSION['user_name'];

        // 1. SAVE MAIN WORKER RECORD
        $new_id = $db->save_worker($data, $id);
        $db->log(($id > 0 ? 'UPDATE' : 'CREATE'), "Worker Record: " . ($data['passport_number'] ?? $new_id));

        // --- 2. SYNC ADDITIONAL PAYMENTS (Fixed Deletion) ---
        
        // Clear all existing payments first to handle row removals (even the last row)
        $db->pdo->prepare("DELETE FROM additional_payments WHERE worker_id = ?")->execute([$new_id]);

        // Re-insert rows currently present on the screen
        if (isset($_POST['add_pay_desc']) && is_array($_POST['add_pay_desc'])) {
            $descs = $_POST['add_pay_desc'];
            $refs = $_POST['add_pay_ref'] ?? [];
            $amts = $_POST['add_pay_amount'] ?? [];
            $dates = $_POST['add_pay_date'] ?? [];
            $existing_files = $_POST['existing_pay_proof'] ?? [];
            
            for($i = 0; $i < count($descs); $i++) {
                if(!empty($descs[$i]) || !empty($refs[$i])) {
                    // Use old file path if no new file is uploaded
                    $proof_path = $existing_files[$i] ?? null;

                    // Handle new file upload for this dynamic row
                    if (isset($_FILES['add_pay_proof']['name'][$i]) && $_FILES['add_pay_proof']['error'][$i] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['add_pay_proof']['tmp_name'][$i];
                        $ext = strtolower(pathinfo($_FILES['add_pay_proof']['name'][$i], PATHINFO_EXTENSION));
                        $fn = bin2hex(random_bytes(10)) . '.' . $ext;
                        if (move_uploaded_file($file_tmp, $uploads_dir . $fn)) {
                            $proof_path = BASE_URL . $uploads_dir . $fn;
                        }
                    }

                    $stmt = $db->pdo->prepare("INSERT INTO additional_payments (worker_id, description, ref_no, amount, payment_date, proof_file) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$new_id, sanitize_text_field($descs[$i]), sanitize_text_field($refs[$i]), floatval($amts[$i]), $dates[$i], $proof_path]);
                }
            }
        }

        // 3. RECALCULATE BALANCE (Always run after saving payments)
        $db->update_balance($new_id);

        echo json_encode(['success' => true, 'data' => ['id' => $new_id]]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'data' => $e->getMessage()]);
    }
    exit;
}

// =======================================================
// 2. UNIFIED REPORTS ENDPOINT (Manual Gen + Consolidated Wizard)
// =======================================================

if ($action === 'get_report') {
    try {
        $type = $_POST['type'] ?? 'daily'; // 'daily' or 'monthly'
        $date = $_POST['date'] ?? date('Y-m-d');
        $month = $_POST['month'] ?? date('m');
        $year = $_POST['year'] ?? date('Y');
        $filter_val = $_POST['category'] ?? ''; // Filter can be Doc Type (INV/PV/OR) or Worker Category

        $data = [];

        // --- PART A: MANUAL DOCUMENTS (from invoices table) ---
        $sql1 = "SELECT doc_no, type as doc_type, client_name, amount, invoice_date as pdate, 'Manual Gen' as source, '' as worker_cat 
                 FROM invoices WHERE 1=1 ";
        
        if($type == 'daily') {
            $sql1 .= "AND invoice_date = '$date' ";
        } else {
            $sql1 .= "AND MONTH(invoice_date) = '$month' AND YEAR(invoice_date) = '$year' ";
        }
        
        // If the user filtered by a specific document type (Invoice, Official Receipt, Payment Voucher)
if(in_array($filter_val, ['Invoice', 'Official Receipt', 'Payment Voucher', 'Refund Receipt'])) {
    $sql1 .= "AND type = '$filter_val' ";
}

        $manual_docs = $db->pdo->query($sql1)->fetchAll(PDO::FETCH_ASSOC);

        // --- PART B: WIZARD PAYMENTS (from additional_payments table only) ---
        $wizard_data = [];
        
        // We only show wizard payments if the filter is empty, or specifically "Official Receipt",
        // or if the filter is a Worker Category (Calling Visa, etc.)
        if(!$filter_val || $filter_val == 'Official Receipt' || !in_array($filter_val, ['Invoice', 'Payment Voucher'])) {
            
            // Check if filter_val is a specific Worker Category
            $cat_match = (in_array($filter_val, ['Calling Visa', 'Programme', 'Tukar Majikan'])) ? $filter_val : '';

            $sqlW = "SELECT w.passport_number as doc_no, ap.description as doc_type, w.full_name as client_name, ap.amount, ap.payment_date as pdate, 'Wizard' as source, w.category as worker_cat 
                     FROM additional_payments ap 
                     JOIN workers w ON ap.worker_id = w.id 
                     WHERE ap.amount > 0 ";

            if($type == 'daily') {
                $sqlW .= "AND ap.payment_date = '$date' ";
            } else {
                $sqlW .= "AND MONTH(ap.payment_date) = '$month' AND YEAR(ap.payment_date) = '$year' ";
            }

            if($cat_match) {
                $sqlW .= "AND w.category = '$cat_match' ";
            }

            $wizard_data = $db->pdo->query($sqlW)->fetchAll(PDO::FETCH_ASSOC);
        }

        // Merge both sources
        $final_data = array_merge($manual_docs, $wizard_data);

        echo json_encode(['success' => true, 'data' => $final_data]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'data' => $e->getMessage()]);
    }
    exit;
}

// =======================================================
// 3. PROFILE & IDENTITY MANAGEMENT
// =======================================================

if ($action === 'update_personal_profile') {
    $name = sanitize_text_field($_POST['account_name'] ?? '');
    $pass = $_POST['new_password'] ?? '';
    $user_id = $_SESSION['user_id'];

    try {
        // 1. Update Account Name (Used for Issued By in PDF)
        $stmt = $db->pdo->prepare("UPDATE users SET account_name = ? WHERE id = ?");
        $stmt->execute([$name, $user_id]);

        // 2. Update Password if provided
        if (!empty($pass)) {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $db->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
        }

        $db->log('SECURITY', 'Updated personal profile and account name.');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'data' => $e->getMessage()]);
    }
    exit;
}

// =======================================================
// 4. RENEWAL & ARCHIVING
// =======================================================

if ($action === 'archive_worker') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) { 
        echo json_encode(['success' => false, 'data' => 'Invalid Worker ID']); 
        exit; 
    }
    
    try {
        $success = $db->archive_and_reset_worker($id);
        if ($success) {
            $db->log('RENEWAL', "Worker ID $id moved to archive.");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'data' => 'Archive process returned false.']);
        }
    } catch (Exception $e) {
        // This will send the EXACT SQL error to the red popup
        echo json_encode(['success' => false, 'data' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'download_backup') {
    if ($_SESSION['user_role'] !== 'admin') exit;
    
    $tables = ['settings', 'users', 'workers', 'invoices', 'logs', 'worker_archives', 'additional_payments'];
    $output = "-- FWMS System Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        $stmt = $db->pdo->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        // Get Create Table Syntax
        $create = $db->pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_ASSOC);
        $output .= $create['Create Table'] . ";\n\n";

        foreach ($rows as $row) {
            $values = array_map(function($v) use ($db) {
                if (is_null($v)) return 'NULL';
                return $db->pdo->quote($v);
            }, $row);
            $output .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
        }
        $output .= "\n\n";
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="FWMS_Backup_'.date('Y-m-d').'.sql"');
    echo $output;
    exit;
}

// =======================================================
// 5. STANDARD SYSTEM ACTIONS (CRUD)
// =======================================================

if ($action === 'save_user') {
    if ($_SESSION['user_role'] !== 'admin') exit;
    
    // Capture permission flags
    $can_edit = $_POST['can_edit'] ?? 0;
    $can_delete = $_POST['can_delete'] ?? 0;

    $success = $db->save_user(
        $_POST['username'], 
        $_POST['account_name'], 
        $_POST['password'], 
        $_POST['role'], 
        $can_edit, 
        $can_delete, 
        $_POST['id']
    );
    echo json_encode(['success' => $success]);
    exit;
}

if ($action === 'delete_worker') {
    if ($_SESSION['user_role'] !== 'admin') { echo json_encode(['success'=>false, 'data'=>'Admin access required']); exit; }
    $db->delete_worker(intval($_POST['id']));
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_user') {
    if ($_SESSION['user_role'] !== 'admin') exit;
    $res = $db->delete_user($_POST['id']);
    echo json_encode(['success' => !!$res]);
    exit;
}

if ($action === 'save_invoice') {
    $data = [
        'doc_no'       => sanitize_text_field($_POST['doc_no']),
        'type'         => sanitize_text_field($_POST['type']),
        'client_name'  => sanitize_text_field($_POST['client']),
        'description'  => stripslashes($_POST['items_json']), 
        'amount'       => floatval($_POST['amount']),
        'invoice_date' => sanitize_text_field($_POST['date']),
        'created_by'   => $_SESSION['user_name']
    ];
    $id = intval($_POST['id']);
    $res = ($id > 0) ? $db->update_invoice($data, $id) : $db->insert_invoice($data);
    echo json_encode(['success' => true, 'id' => $id]);
    exit;
}

if ($action === 'delete_invoice') {
    // Only Admin can delete financial records
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['success' => false, 'data' => 'Access Denied: Admin only.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $res = $db->delete_invoice($id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'data' => 'Invalid Document ID']);
    }
    exit;
}

if ($action === 'check_passport') {
    $res = $db->check_duplicate_passport($_POST['passport'], $_POST['exclude_id']);
    echo json_encode(['exists' => !!$res, 'name' => $res ? $res->full_name : '']);
    exit;
}

if ($action === 'check_receipt') {
    $receipt = sanitize_text_field($_POST['receipt'] ?? '');
    $exclude_id = intval($_POST['exclude_id'] ?? 0);

   

    // 2. Check Additional Payments table (Across ALL workers)
    $stmt2 = $db->pdo->prepare("SELECT w.full_name FROM additional_payments ap JOIN workers w ON ap.worker_id = w.id WHERE ap.description = ? AND ap.worker_id != ? LIMIT 1");
    $stmt2->execute([$receipt, $exclude_id]);
    $res2 = $stmt2->fetch();
    if ($res2) {
        echo json_encode(['exists' => true, 'message' => "Exists in Payments for: " . $res2->full_name]);
        exit;
    }

    echo json_encode(['exists' => false]);
    exit;
}

if ($action === 'save_settings') {
    if ($_SESSION['user_role'] !== 'admin') exit;
    $db->update_setting('company_name', sanitize_text_field($_POST['c_name']));
    if (!empty($_FILES['c_logo_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['c_logo_file']['name'], PATHINFO_EXTENSION));
        $fn = 'logo_'.time().'.'.$ext;
        if (move_uploaded_file($_FILES['c_logo_file']['tmp_name'], 'uploads/'.$fn)) {
            $db->update_setting('company_logo', BASE_URL.'uploads/'.$fn);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'export_data') {
    // Map the keys sent by JavaScript to the variables expected by the DB function
    $search = sanitize_text_field($_POST['search'] ?? '');
    $cat    = sanitize_text_field($_POST['category'] ?? ''); 
    $start  = sanitize_text_field($_POST['start_date'] ?? '');
    $end    = sanitize_text_field($_POST['end_date'] ?? '');
    
    $workers = $db->get_all_workers($search, $cat, $start, $end);
    
    if (empty($workers)) {
        echo json_encode(['success' => false, 'data' => 'No records found to export.']);
        exit;
    }

    $export = [];
    foreach ($workers as $w) {
        $export[] = [
            'Passport' => $w->passport_number,
            'Name'     => $w->full_name,
            'Category' => $w->category,
            'Stage'    => $w->current_stage,
            'Total'    => number_format((float)$w->total_payable, 2),
            'Balance'  => number_format((float)$w->balance_due, 2),
            'Expiry'   => format_date_my($w->permit_expiry)
        ];
    }
    echo json_encode(['success' => true, 'data' => $export]);
    exit;
}

// =======================================================
// ARCHIVE MASTER EXPORT (All 8 Steps + Dynamic Payments)
// =======================================================
if ($action === 'export_archives') {
    try {
        $search = sanitize_text_field($_POST['search'] ?? '');
        $start = sanitize_text_field($_POST['start_date'] ?? '');
        $end = sanitize_text_field($_POST['end_date'] ?? '');

        $where = ["1=1"]; $params = [];
        if ($search) { $where[] = "(passport_number LIKE ? OR full_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($start && $end) { $where[] = "DATE(archive_date) BETWEEN ? AND ?"; $params[] = $start; $params[] = $end; }

        $sql = "SELECT * FROM worker_archives WHERE " . implode(' AND ', $where) . " ORDER BY archive_date DESC";
        $stmt = $db->pdo->prepare($sql);
        $stmt->execute($params);
        $archives = $stmt->fetchAll();
        
        $export = [];
        foreach ($archives as $a) {
            $snapshot = json_decode($a->archive_data, true); 
            // Support V4/V5 (nested) or V3 (flat) data
            $d = $snapshot['worker_details'] ?? $snapshot; 
            $adds = $snapshot['additional_payments'] ?? [];

            // Combine all dynamic payments into one cell for Excel
            $payment_history = "";
            if(!empty($adds)){
                foreach($adds as $index => $pay) {
                    $payment_history .= ($index + 1) . ". " . ($pay['description'] ?? 'Payment') . " [Ref: " . ($pay['ref_no'] ?? '-') . "] (MYR " . ($pay['amount'] ?? '0') . ") | ";
                }
                $payment_history = rtrim($payment_history, " | ");
            }

            $export[] = [
                // System Info
                'Date Archived'     => $a->archive_date,
                
                // STEP 1: IDENTITY & DETAILS
                'Worker Category'   => $d['category'] ?? '-',
                'Passport No'       => $a->passport_number,
                'Full Name'         => $a->full_name,
                'KWSP Member No'    => $d['kwsp_no'] ?? '-',
                'Nationality'       => $d['nationality'] ?? '-',
                'Gender'            => $d['gender'] ?? '-',
                'Date of Birth'     => format_date_my($d['dob'] ?? ''),
                'Passport Expiry'   => format_date_my($d['passport_expiry'] ?? ''),
                'Visa Expiry'       => format_date_my($d['visa_expiry'] ?? ''),
                
                // STEP 1: FINANCIALS
                'Total Payable'     => number_format((float)($d['total_payable'] ?? 0), 2),
                'Balance Remaining' => number_format((float)($d['balance_due'] ?? 0), 2),
                'Payment Details'   => $payment_history,

                // STEP 3: FOMEMA
                'FOMEMA Status'     => $d['fomema_status'] ?? '-',
                'FOMEMA Code'       => $d['fomema_code'] ?? '-',
                'FOMEMA Expiry'     => format_date_my($d['fomema_expiry'] ?? ($d['fomema_date'] ?? '')),
                
                // STEP 4: INSURANCE
                'Insurance Policy'  => $d['insurance_policy'] ?? '-',
                'Insurance Provider'=> $d['insurance_provider'] ?? '-',
                'Insurance Expiry'  => format_date_my($d['insurance_expiry'] ?? ''),
                
                // STEP 5: LEVY
                'Levy Status'       => $d['levy_status'] ?? '-',
                'Levy Ref No'       => $d['levy_reference'] ?? '-',
                
                // STEP 7: PERMIT (PLKS)
                'Permit Status'     => $d['permit_status'] ?? '-',
                'Permit Sticker No' => $d['permit_number'] ?? '-',
                'Permit Issue Date' => format_date_my($d['permit_issue'] ?? ''),
                'Permit Expiry Date'=> format_date_my($d['permit_expiry'] ?? ''),
                
                // STEP 8: CIDB
                'CIDB Status'       => $d['cidb_status'] ?? '-',
                'CIDB Category'     => $d['cidb_category'] ?? '-',
                'CIDB Expiry Date'  => format_date_my($d['cidb_expiry'] ?? ''),

                // Audit
                'Record Created By' => $d['created_by'] ?? '-'
            ];
        }
        echo json_encode(['success' => true, 'data' => $export]);
    } catch (Exception $e) { 
        echo json_encode(['success' => false, 'data' => $e->getMessage()]); 
    }
    exit;
}

echo json_encode(['success' => false, 'data' => 'Invalid Request']);