<?php
/**
 * Standalone API Handler - FWMS System
 * Location: root/api.php
 * Version: 5.0.0 (Security Hardened, Validated, Transactional)
 */
require_once 'functions.php';
header('Content-Type: application/json');

// 1. Session Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'data' => 'Unauthorized Access']);
    exit;
}

// Refresh role/permissions from DB so revokes take effect without re-login
sync_session_permissions_from_db($db);

// 2. CSRF Security Check
$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
$request_token = $headers['X-CSRF-TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $request_token)) {
    echo json_encode(['success' => false, 'data' => 'Security Token Mismatch']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'get_invoice_by_no') {
    $doc_no = sanitize_text_field($_POST['doc_no'] ?? '');
    if (empty($doc_no)) { echo json_encode(['success' => false]); exit; }
    $stmt = $db->pdo->prepare("SELECT * FROM invoices WHERE doc_no = ? LIMIT 1");
    $stmt->execute([$doc_no]);
    $res = $stmt->fetch();
    echo json_encode($res ? ['success' => true, 'data' => $res] : ['success' => false]);
    exit;
}

if ($action === 'bulk_import_workers') {
    require_permission('admin');
    $workers = json_decode($_POST['workers'], true);
    if (!is_array($workers)) { echo json_encode(['success' => false, 'data' => 'Invalid workers data.']); exit; }

    $count = 0; $duplicates = 0; $errors = [];
    $db->pdo->beginTransaction();
    try {
        foreach ($workers as $idx => $w) {
            $passport = sanitize_text_field($w['Passport'] ?? '');
            if (empty($passport)) continue;

            $data = [
                'passport_number' => $passport,
                'full_name'       => sanitize_text_field($w['Name'] ?? ''),
                'category'        => sanitize_text_field($w['Category'] ?? 'Calling Visa'),
                'kwsp_no'         => sanitize_text_field($w['KWSP'] ?? ''),
                'nationality'     => sanitize_text_field($w['Nationality'] ?? 'Bangladesh'),
                'gender'          => sanitize_text_field($w['Gender'] ?? 'Male'),
                'dob'             => ($w['BirthDate'] ?? '') ?: null,
                'passport_expiry' => ($w['PassportExpiry'] ?? '') ?: null,
                'total_payable'   => floatval($w['TotalPayable'] ?? 0),
                'current_stage'   => 1,
                'created_by'      => $_SESSION['user_name']
            ];

            if ($db->check_duplicate_passport($passport)) {
                $duplicates++;
            } else {
                $new_id = $db->save_worker($data, 0);
                $db->update_balance($new_id);
                $count++;
            }
        }
        $db->pdo->commit();
        $db->log('BULK_IMPORT', "$count workers imported, $duplicates skipped.");
        echo json_encode(['success' => true, 'data' => "$count imported. $duplicates skipped."]);
    } catch (Exception $e) {
        $db->pdo->rollBack();
        echo json_encode(['success' => false, 'data' => 'Import failed: ' . $e->getMessage()]);
    }
    exit;
}

// =======================================================
// 1. WORKER MANAGEMENT (SAVE / DYNAMIC PAYMENTS / STAGE)
// =======================================================

if ($action === 'save_worker') {
    require_permission('edit');
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

        foreach (['fomema_proof', 'insurance_proof', 'cidb_proof', 'epass_worker_proof', 'passport_copy_proof'] as $file_key) {
            if (isset($_FILES[$file_key])) {
                $stored = store_secure_upload($_FILES[$file_key], $uploads_dir);
                if ($stored) $data[$file_key] = $stored;
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

        $existing_worker = ($id > 0) ? $db->get_worker($id) : null;
        $is_advance = isset($_POST['advance_stage']) && $_POST['advance_stage'] == 'true';
        if ($is_advance) {
            $stage_errors = validate_wizard_stage_advance($current_stage, $_POST, $existing_worker);
            if (!empty($stage_errors)) {
                throw new Exception(implode(' ', $stage_errors));
            }
        }

        // Prepare payment rows before opening the DB transaction
        $payment_rows = [];
        if (isset($_POST['add_pay_desc']) && is_array($_POST['add_pay_desc'])) {
            $descs = $_POST['add_pay_desc'];
            $refs = $_POST['add_pay_ref'] ?? [];
            $amts = $_POST['add_pay_amount'] ?? [];
            $dates = $_POST['add_pay_date'] ?? [];
            $existing_files = $_POST['existing_pay_proof'] ?? [];

            for ($i = 0; $i < count($descs); $i++) {
                if (empty($descs[$i]) && empty($refs[$i])) continue;

                $ref = sanitize_text_field($refs[$i] ?? '');
                // Payment Ref No may be reused across workers / rows (not unique).

                $proof_path = sanitize_existing_upload_path($existing_files[$i] ?? '');

                if (isset($_FILES['add_pay_proof']['name'][$i]) && $_FILES['add_pay_proof']['error'][$i] === UPLOAD_ERR_OK) {
                    $stored = store_secure_upload([
                        'name' => $_FILES['add_pay_proof']['name'][$i],
                        'type' => $_FILES['add_pay_proof']['type'][$i] ?? '',
                        'tmp_name' => $_FILES['add_pay_proof']['tmp_name'][$i],
                        'error' => $_FILES['add_pay_proof']['error'][$i],
                        'size' => $_FILES['add_pay_proof']['size'][$i] ?? 0,
                    ], $uploads_dir);
                    if ($stored) $proof_path = $stored;
                }

                $pay_date = trim($dates[$i] ?? '');
                if ($pay_date === '' || $pay_date === '0000-00-00') $pay_date = null;

                $payment_rows[] = [
                    sanitize_text_field($descs[$i] ?? ''),
                    $ref,
                    floatval($amts[$i] ?? 0),
                    $pay_date,
                    $proof_path,
                ];
            }
        }

        $db->pdo->beginTransaction();
        try {
            $new_id = $db->save_worker($data, $id);
            $db->log(($id > 0 ? 'UPDATE' : 'CREATE'), "Worker Record: " . ($data['passport_number'] ?? $new_id));

            $db->pdo->prepare("DELETE FROM additional_payments WHERE worker_id = ?")->execute([$new_id]);

            if (!empty($payment_rows)) {
                $stmt = $db->pdo->prepare("INSERT INTO additional_payments (worker_id, description, ref_no, amount, payment_date, proof_file) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($payment_rows as $row) {
                    $stmt->execute([$new_id, $row[0], $row[1], $row[2], $row[3], $row[4]]);
                }
            }

            $db->update_balance($new_id);
            $db->pdo->commit();
            echo json_encode(['success' => true, 'data' => ['id' => $new_id]]);
        } catch (Exception $inner) {
            if ($db->pdo->inTransaction()) $db->pdo->rollBack();
            throw $inner;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'data' => $e->getMessage()]);
    }
    exit;
}

// =======================================================
// 2. UNIFIED REPORTS ENDPOINT (Manual Gen + Consolidated Wizard)
// =======================================================

if ($action === 'get_report') {
    require_permission('admin');
    try {
        $allowed_types = ['daily', 'monthly'];
        $allowed_doc_types = ['Invoice', 'Official Receipt', 'Payment Voucher', 'Refund Receipt'];
        $allowed_cats = ['Calling Visa', 'Programme', 'Tukar Majikan'];

        $type       = in_array($_POST['type'] ?? '', $allowed_types) ? $_POST['type'] : 'daily';
        $date       = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
        $month      = intval($_POST['month'] ?? date('m'));
        $year       = intval($_POST['year'] ?? date('Y'));
        $filter_val = sanitize_text_field($_POST['category'] ?? '');

        // --- PART A: MANUAL DOCUMENTS ---
        $params1 = [];
        $sql1_where = "WHERE 1=1 ";
        if ($type == 'daily') {
            $sql1_where .= "AND invoice_date = ? "; $params1[] = $date;
        } else {
            $sql1_where .= "AND MONTH(invoice_date) = ? AND YEAR(invoice_date) = ? "; $params1[] = $month; $params1[] = $year;
        }
        if (in_array($filter_val, $allowed_doc_types)) {
            $sql1_where .= "AND type = ? "; $params1[] = $filter_val;
        }
        $stmt1 = $db->pdo->prepare("SELECT doc_no, type as doc_type, client_name, amount, invoice_date as pdate, 'Manual Gen' as source, '' as worker_cat FROM invoices $sql1_where");
        $stmt1->execute($params1);
        $manual_docs = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        // --- PART B: WIZARD PAYMENTS ---
        $wizard_data = [];
        if (!$filter_val || $filter_val == 'Official Receipt' || !in_array($filter_val, ['Invoice', 'Payment Voucher'])) {
            $cat_match = in_array($filter_val, $allowed_cats) ? $filter_val : '';
            $paramsW = [];
            $sqlW_where = "WHERE ap.amount > 0 ";
            if ($type == 'daily') {
                $sqlW_where .= "AND ap.payment_date = ? "; $paramsW[] = $date;
            } else {
                $sqlW_where .= "AND MONTH(ap.payment_date) = ? AND YEAR(ap.payment_date) = ? "; $paramsW[] = $month; $paramsW[] = $year;
            }
            if ($cat_match) {
                $sqlW_where .= "AND w.category = ? "; $paramsW[] = $cat_match;
            }
            $stmtW = $db->pdo->prepare("SELECT w.passport_number as doc_no, ap.description as doc_type, w.full_name as client_name, ap.amount, ap.payment_date as pdate, 'Wizard' as source, w.category as worker_cat FROM additional_payments ap JOIN workers w ON ap.worker_id = w.id $sqlW_where");
            $stmtW->execute($paramsW);
            $wizard_data = $stmtW->fetchAll(PDO::FETCH_ASSOC);
        }

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
    require_permission('edit');
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
        echo json_encode(['success' => false, 'data' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_worker_archive') {
    require_permission('edit');
    $archive_id = intval($_POST['archive_id'] ?? 0);
    $worker_id = intval($_POST['worker_id'] ?? 0);
    if ($archive_id <= 0 || $worker_id <= 0) {
        echo json_encode(['success' => false, 'data' => 'Invalid archive or worker.']);
        exit;
    }
    $ok = $db->delete_worker_archive($archive_id, $worker_id);
    if ($ok) {
        $db->log('DELETE_ARCHIVE', "Removed renewal archive #$archive_id for worker ID $worker_id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'data' => 'Archive record not found.']);
    }
    exit;
}

if ($action === 'download_backup') {
    require_permission('admin');

    $output = fwms_build_sql_backup($db->pdo);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="FWMS_Backup_'.date('Y-m-d').'.sql"');
    echo $output;
    exit;
}

if ($action === 'run_backup') {
    require_permission('admin');
    try {
        $keep = (int) ($_POST['keep_days'] ?? 14);
        $meta = fwms_run_scheduled_backup($db->pdo, __DIR__, $keep);
        $db->log('BACKUP', 'Scheduled backup created: ' . $meta['sql_file']);
        echo json_encode(['success' => true, 'data' => $meta]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'data' => 'Backup failed.']);
        error_log('run_backup: ' . $e->getMessage());
    }
    exit;
}

if ($action === 'get_health') {
    require_permission('admin');
    echo json_encode(['success' => true, 'data' => fwms_health_checks($db->pdo, __DIR__)]);
    exit;
}

// =======================================================
// 5. STANDARD SYSTEM ACTIONS (CRUD)
// =======================================================

if ($action === 'save_user') {
    require_permission('admin');

    $validation = validate_request([
        'username'     => ['required', 'string', 'max:80'],
        'account_name' => ['required', 'string', 'max:120'],
        'role'         => ['required', 'string', 'in:admin,staff'],
    ], $_POST);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'data' => implode(' ', $validation['errors'])]);
        exit;
    }

    $can_edit   = $_POST['can_edit'] ?? 0;
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
    require_permission('delete');
    $db->delete_worker(intval($_POST['id']));
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_user') {
    require_permission('admin');
    $res = $db->delete_user($_POST['id']);
    echo json_encode(['success' => !!$res]);
    exit;
}

if ($action === 'save_contact') {
    require_permission('edit');

    $validation = validate_request([
        'name'    => ['required', 'string', 'max:150'],
        'company' => ['string', 'max:150'],
        'phone'   => ['string', 'max:50'],
        'email'   => ['string', 'max:120'],
        'address' => ['string', 'max:500'],
        'notes'   => ['string', 'max:1000'],
    ], $_POST);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'data' => implode(' ', $validation['errors'])]);
        exit;
    }
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'data' => 'Invalid email address.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $result = $db->save_contact($_POST, $id);
    echo json_encode(['success' => (bool) $result, 'data' => $result ?: 'Could not save contact.']);
    exit;
}

if ($action === 'delete_contact') {
    require_permission('delete');
    $ok = $db->delete_contact(intval($_POST['id'] ?? 0));
    echo json_encode(['success' => (bool) $ok, 'data' => $ok ? null : 'Contact not found.']);
    exit;
}

if ($action === 'save_invoice') {
    require_permission('edit');

    $validation = validate_request([
        'doc_no' => ['required', 'string', 'max:50'],
        'type'   => ['required', 'string'],
        'client' => ['required', 'string', 'max:200'],
        'amount' => ['required', 'numeric', 'positive'],
        'date'   => ['required', 'date'],
    ], $_POST);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'data' => implode(' ', $validation['errors'])]);
        exit;
    }

    $data = [
        'doc_no'       => sanitize_text_field($_POST['doc_no']),
        'type'         => sanitize_text_field($_POST['type']),
        'client_name'  => sanitize_text_field($_POST['client']),
        'description'  => stripslashes($_POST['items_json']),
        'amount'       => floatval($_POST['amount']),
        'invoice_date' => sanitize_text_field($_POST['date']),
        'created_by'   => $_SESSION['user_name']
    ];
    $id = intval($_POST['id'] ?? 0);

    // Mirror UI rule: staff cannot edit Official Receipts
    if ($id > 0 && !current_user_can_admin()) {
        $stmt = $db->pdo->prepare("SELECT type FROM invoices WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if ($existing && $existing->type === 'Official Receipt') {
            echo json_encode(['success' => false, 'data' => 'Staff members cannot edit Official Receipts.']);
            exit;
        }
        if ($data['type'] === 'Official Receipt') {
            echo json_encode(['success' => false, 'data' => 'Staff members cannot convert documents to Official Receipt.']);
            exit;
        }
    }

    if ($id > 0) {
        $db->update_invoice($data, $id);
        $saved_id = $id;
    } else {
        $saved_id = (int) $db->insert_invoice($data);
    }
    echo json_encode(['success' => true, 'id' => $saved_id]);
    exit;
}

if ($action === 'delete_invoice') {
    require_permission('admin');

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
    // Payment Ref No is intentionally not unique — allow reuse.
    echo json_encode(['exists' => false]);
    exit;
}

if ($action === 'save_settings') {
    require_permission('admin');
    try {
        $db->update_setting('company_name', sanitize_text_field($_POST['c_name']));
        if (!empty($_FILES['c_logo_file']['name'])) {
            $stored = store_secure_upload($_FILES['c_logo_file'], 'uploads/');
            if ($stored) {
                $db->update_setting('company_logo', $stored);
            }
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'data' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'export_data') {
    require_permission('edit');
    $search = sanitize_text_field($_POST['search'] ?? '');
    $cat    = sanitize_text_field($_POST['category'] ?? '');
    $start  = sanitize_text_field($_POST['start_date'] ?? '');
    $end    = sanitize_text_field($_POST['end_date'] ?? '');
    $stage  = intval($_POST['stage'] ?? 0);

    $workers = $db->get_all_workers($search, $cat, $start, $end, $stage);

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
    require_permission('admin');
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