<?php
/**
 * PHPUnit bootstrap — load helpers without a live database.
 */
define('FWMS_SKIP_DB', true);

if (session_status() === PHP_SESSION_NONE) {
    // Avoid secure-cookie warnings in CI CLI
    @ini_set('session.use_cookies', '0');
}

require_once dirname(__DIR__) . '/functions.php';
