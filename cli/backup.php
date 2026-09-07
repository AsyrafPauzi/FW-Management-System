<?php
/**
 * CLI / cron backup runner.
 * Usage:
 *   php cli/backup.php
 *   php cli/backup.php --keep=14
 *
 * Or HTTP cron (optional):
 *   curl "https://your-host/cli/backup.php?token=YOUR_BACKUP_TOKEN"
 *
 * Set BACKUP_TOKEN in .env for HTTP access. CLI always allowed.
 */
$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    // Bootstrap enough to read .env without dying on missing session cookie flags in some hosts
    require_once dirname(__DIR__) . '/functions.php';

    if (!defined('BACKUP_TOKEN') || BACKUP_TOKEN === '') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'data' => 'BACKUP_TOKEN not configured.']);
        exit;
    }
    $token = $_GET['token'] ?? $_POST['token'] ?? '';
    if (!hash_equals((string) BACKUP_TOKEN, (string) $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'data' => 'Forbidden.']);
        exit;
    }
} else {
    require_once dirname(__DIR__) . '/functions.php';
}

$keep = 14;
if ($is_cli) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--keep=') === 0) {
            $keep = (int) substr($arg, 7);
        }
    }
} else {
    $keep = (int) ($_GET['keep'] ?? $_POST['keep'] ?? 14);
}

try {
    $meta = fwms_run_scheduled_backup($pdo, dirname(__DIR__), $keep);
    if (isset($db) && $db instanceof DB) {
        $db->log('BACKUP', 'Cron backup: ' . $meta['sql_file']);
    }
    if ($is_cli) {
        echo "Backup OK\n";
        echo "SQL: {$meta['sql_file']} ({$meta['sql_bytes']} bytes)\n";
        if (!empty($meta['uploads_zip'])) {
            echo "Uploads: {$meta['uploads_zip']}\n";
        }
        exit(0);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $meta]);
} catch (Exception $e) {
    error_log('cli/backup.php: ' . $e->getMessage());
    if ($is_cli) {
        fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => 'Backup failed.']);
}
