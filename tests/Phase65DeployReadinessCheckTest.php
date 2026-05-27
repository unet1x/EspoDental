<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase65DeployReadinessCheckTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const SCRIPT = self::ROOT . '/deploy/check-deploy-readiness.sh';

    public function testDeployReadinessScriptValidatesComposeAndRecoveryDocs(): void
    {
        $script = $this->readFile(self::SCRIPT);

        $this->assertTrue(is_executable(self::SCRIPT), 'deploy readiness check must be executable');

        foreach (
            [
                'deploy/local/docker-compose.yml',
                'deploy/.env.example',
                'deploy/docker-compose.yml',
                'deploy/staging/.env.example',
                'deploy/staging/docker-compose.yml',
                'config --quiet',
                'bash -n',
                'deploy/scripts/backup-prod.sh',
                'deploy/scripts/restore-to-staging.sh',
                'docs/install-synology.md',
                'docs/proxmox-vm-migration.md',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $script);
        }
    }

    public function testDeployReadinessScriptDoesNotRunBackupOrRestore(): void
    {
        $script = $this->readFile(self::SCRIPT);

        foreach (
            [
                'bash deploy/scripts/backup-prod.sh',
                'bash deploy/scripts/restore-to-staging.sh',
                'docker compose up -d',
                'docker compose exec -T mariadb mariadb',
                'mariadb-dump',
            ] as $needle
        ) {
            $this->assertStringNotContainsString($needle, $script);
        }
    }

    public function testDocsTrackDeployReadinessCheck(): void
    {
        foreach (
            [
                self::ROOT . '/docs/dev-runbook.md',
                self::ROOT . '/docs/release-acceptance-checklist.md',
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('deploy/check-deploy-readiness.sh', $doc);
        }
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
