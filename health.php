<?php
/**
 * Public health endpoint for uptime monitors.
 * Returns JSON. Does not expose credentials or absolute paths beyond free disk MB.
 */
require_once __DIR__ . '/functions.php';

$health = fwms_health_checks($pdo, __DIR__);
$code = ($health['status'] === 'ok') ? 200 : 503;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
http_response_code($code);
echo json_encode($health);
