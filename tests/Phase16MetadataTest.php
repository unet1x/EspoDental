<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase16MetadataTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    public function testStagingComposeFileExists(): void
    {
        $path = self::ROOT . '/deploy/staging/docker-compose.yml';
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('name: espodental-staging', $body);
        $this->assertStringContainsString('espodental-staging-db', $body);
        $this->assertStringContainsString('espodental-staging-web', $body);
        $this->assertStringContainsString('espodental-staging-daemon', $body);
        $this->assertStringContainsString('espodental-staging-websocket', $body);
        $this->assertStringContainsString('ESPOCRM_HTTP_PORT', $body);
        $this->assertStringContainsString('ESPOCRM_WS_PORT', $body);
        $this->assertStringContainsString('STAGING - test environment', $body);
        $this->assertStringContainsString('/volume1/espomodule-staging', $body);
    }

    public function testStagingEnvExampleExists(): void
    {
        $path = self::ROOT . '/deploy/staging/.env.example';
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('ESPOCRM_HTTP_PORT=8090', $body);
        $this->assertStringContainsString('ESPOCRM_WS_PORT=8091', $body);
        $this->assertStringContainsString('espodental_staging', $body);
    }

    public function testSharedLibsExist(): void
    {
        foreach (['lib/common.sh', 'lib/alert.sh'] as $rel) {
            $path = self::ROOT . '/deploy/scripts/' . $rel;
            $this->assertFileExists($path);
        }
        $common = (string) file_get_contents(self::ROOT . '/deploy/scripts/lib/common.sh');
        $this->assertStringContainsString('ESPODENTAL_PIPELINE_ID', $common);
        $this->assertStringContainsString('log_info', $common);
        $this->assertStringContainsString('require_env', $common);
        $this->assertStringContainsString('load_env', $common);

        $alert = (string) file_get_contents(self::ROOT . '/deploy/scripts/lib/alert.sh');
        $this->assertStringContainsString('alert_send', $alert);
        $this->assertStringContainsString('alert_telegram', $alert);
        $this->assertStringContainsString('alert_email', $alert);
        $this->assertStringContainsString('api.telegram.org', $alert);
        $this->assertStringContainsString('ALERT_SMTP_URL', $alert);
    }

    public function testBackupScriptExists(): void
    {
        $path = self::ROOT . '/deploy/scripts/backup-prod.sh';
        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path), 'backup-prod.sh must be executable');
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('mariadb-dump', $body);
        $this->assertStringContainsString('--single-transaction', $body);
        $this->assertStringContainsString('BACKUP_RETENTION_DAYS', $body);
        $this->assertStringContainsString('db-latest.sql.gz', $body);
        $this->assertStringContainsString('manifest', $body);
    }

    public function testRestoreScriptExists(): void
    {
        $path = self::ROOT . '/deploy/scripts/restore-to-staging.sh';
        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path), 'restore-to-staging.sh must be executable');
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('DROP DATABASE IF EXISTS', $body);
        $this->assertStringContainsString('gunzip -c', $body);
        $this->assertStringContainsString('rsync', $body);
        $this->assertStringContainsString('sanity_check', $body);
        $this->assertStringContainsString('STAGING_HEALTH_URL', $body);
        $this->assertStringContainsString('PATIENT_TABLE_NAME', $body);
    }

    public function testNightlyScriptExists(): void
    {
        $path = self::ROOT . '/deploy/scripts/nightly.sh';
        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path), 'nightly.sh must be executable');
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('run_step backup-prod backup', $body);
        $this->assertStringContainsString('run_step backup-prod-retry backup', $body);
        $this->assertStringContainsString('Backup FAILED twice', $body);
        $this->assertStringContainsString('Restore to staging FAILED', $body);
        $this->assertStringContainsString('Staging sanity check FAILED', $body);
        $this->assertStringContainsString('alert_send', $body);
    }

    public function testEnvExampleHasAlertSection(): void
    {
        $path = self::ROOT . '/deploy/.env.example';
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        foreach (
            ['ALERT_TELEGRAM_BOT_TOKEN', 'ALERT_TELEGRAM_CHAT_ID',
                'ALERT_EMAIL_TO', 'ALERT_EMAIL_FROM',
                'ALERT_SMTP_URL', 'ALERT_SMTP_USER', 'ALERT_SMTP_PASS',
                'STAGING_COMPOSE_DIR', 'STAGING_HEALTH_URL',
                'PATIENT_TABLE_NAME', 'LOG_DIR'] as $var
        ) {
            $this->assertStringContainsString($var, $body, "missing env var: ${var}");
        }
        $this->assertMatchesRegularExpression(
            '/BACKUP_RETENTION_DAYS\\s*=\\s*14\\b/',
            $body,
            'retention should default to 14'
        );
    }

    public function testAdminGuideHasStagingSection(): void
    {
        $body = (string) file_get_contents(self::ROOT . '/docs/admin-guide.md');
        $this->assertStringContainsString('Staging environment + nightly pipeline', $body);
        $this->assertStringContainsString('/volume1/espomodule-staging', $body);
        $this->assertStringContainsString('/volume1/espomodule-prod', $body);
        $this->assertStringContainsString('nightly.sh', $body);
        $this->assertStringContainsString('Rollback', $body);
        $this->assertStringContainsString('Promotion workflow', $body);
    }

    public function testReadmeHasStagingSection(): void
    {
        $body = (string) file_get_contents(self::ROOT . '/README.md');
        $this->assertStringContainsString('Staging + nightly pipeline', $body);
        $this->assertStringContainsString('deploy/staging/docker-compose.yml', $body);
        $this->assertStringContainsString('deploy/scripts/nightly.sh', $body);
    }

    public function testReleaseNotesHaveCurrentVersion(): void
    {
        $body = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');
        $this->assertStringContainsString('0.16.0 — Staging stack', $body);
        $this->assertStringContainsString('Backup FAILED twice', $body);
    }

    public function testManifestVersionBumped(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(self::ROOT . '/src/manifest.json'),
            true
        );
        $this->assertSame('0.16.0', $manifest['version']);
    }

    public function testModuleHostPathIsEnvDriven(): void
    {
        $prod = (string) file_get_contents(self::ROOT . '/deploy/docker-compose.yml');
        $this->assertStringContainsString(
            '${MODULE_HOST_PATH:-/volume1/espomodule}',
            $prod,
            'prod compose should reference MODULE_HOST_PATH with backwards-compatible default'
        );

        $staging = (string) file_get_contents(self::ROOT . '/deploy/staging/docker-compose.yml');
        $this->assertStringContainsString(
            '${MODULE_HOST_PATH:-/volume1/espomodule-staging}',
            $staging,
            'staging compose should reference MODULE_HOST_PATH'
        );

        $envExample = (string) file_get_contents(self::ROOT . '/deploy/.env.example');
        $this->assertStringContainsString(
            'MODULE_HOST_PATH=/volume1/espomodule-prod',
            $envExample
        );
    }

    public function testSynologyInstallGuideExists(): void
    {
        $path = self::ROOT . '/docs/install-synology.md';
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        foreach (
            ['Container Manager', 'Reverse Proxy', 'espo-dental-seed-roles',
                'nightly.sh', 'Telegram', 'staging', 'MODULE_HOST_PATH',
                '/volume1/espomodule-prod', '/volume1/espomodule-staging',
                'Финальная проверка', 'специального технического образования',
                'BotFather', 'Let\'s Encrypt'] as $needle
        ) {
            $this->assertStringContainsString(
                $needle,
                $body,
                "install-synology.md must mention '${needle}'"
            );
        }
        $partsHeadings = preg_match_all('/^## Часть \d+/m', $body);
        $this->assertGreaterThanOrEqual(
            11,
            $partsHeadings,
            'guide should have at least 11 numbered Часть sections'
        );
    }

    public function testReadmeLinksToInstallGuide(): void
    {
        $body = (string) file_get_contents(self::ROOT . '/README.md');
        $this->assertStringContainsString('docs/install-synology.md', $body);
        $this->assertStringContainsString('non-technical clinic administrators', $body);
    }
}
