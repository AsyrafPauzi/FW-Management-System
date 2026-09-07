<?php

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testSanitizeTextFieldStripsTagsAndKeepsAmpersand(): void
    {
        $this->assertSame('A & B', sanitize_text_field('<b>A & B</b>'));
    }

    public function testSanitizeDecodesLegacyEntities(): void
    {
        $this->assertSame('A & B', sanitize_text_field('A &amp; B'));
    }

    public function testEscapeOutputsEntities(): void
    {
        $this->assertSame('A &amp; B', e('A & B'));
    }

    public function testValidateFieldRequired(): void
    {
        $this->assertNotNull(validate_field('', ['required']));
        $this->assertNull(validate_field('ok', ['required', 'string']));
    }

    public function testValidateFieldInList(): void
    {
        $this->assertNull(validate_field('admin', ['required', 'string', 'in' => ['admin', 'staff']]));
        $this->assertNotNull(validate_field('root', ['required', 'string', 'in' => ['admin', 'staff']]));
    }

    public function testValidateRequestAggregatesErrors(): void
    {
        $result = validate_request([
            'username' => ['required', 'string', 'max' => 80],
            'role' => ['required', 'string', 'in' => ['admin', 'staff']],
        ], ['username' => '', 'role' => 'nope']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('username', $result['errors']);
        $this->assertArrayHasKey('role', $result['errors']);
    }

    public function testLoginIpHashIsStable(): void
    {
        $a = fwms_login_ip_hash('203.0.113.10');
        $b = fwms_login_ip_hash('203.0.113.10');
        $c = fwms_login_ip_hash('203.0.113.11');
        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertSame(64, strlen($a));
    }

    public function testHealthChecksWithoutPdoAreDegraded(): void
    {
        $root = sys_get_temp_dir() . '/fwms_health_' . uniqid();
        mkdir($root . '/uploads', 0777, true);
        mkdir($root . '/storage', 0777, true);
        mkdir($root . '/backups', 0777, true);

        $health = fwms_health_checks(null, $root);
        $this->assertSame('degraded', $health['status']);
        $this->assertFalse($health['checks']['database']['ok']);
        $this->assertTrue($health['checks']['uploads_writable']['ok']);

        // cleanup
        @rmdir($root . '/uploads');
        @rmdir($root . '/storage');
        @rmdir($root . '/backups');
        @rmdir($root);
    }

    public function testBackupTablesListIncludesCoreTables(): void
    {
        $tables = fwms_backup_tables();
        $this->assertContains('workers', $tables);
        $this->assertContains('users', $tables);
        $this->assertContains('login_attempts', $tables);
    }
}
