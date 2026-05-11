<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase14MetadataTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    public function testReadmeIsBilingual(): void
    {
        $path = self::ROOT . '/README.md';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('## English', $content);
        $this->assertStringContainsString('## Русский', $content);
        $this->assertStringContainsString('EspoDental', $content);
        $this->assertStringContainsString('OrthodonticCard', $content);
        $this->assertStringContainsString('MIT', $content);
    }

    public function testAdminGuideExists(): void
    {
        $path = self::ROOT . '/docs/admin-guide.md';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        foreach (['Installation', 'Backup', 'Upgrade', 'Telegram'] as $section) {
            $this->assertStringContainsString($section, $content);
        }
        $this->assertStringContainsString('Установка', $content);
    }

    public function testUserGuideExists(): void
    {
        $path = self::ROOT . '/docs/user-guide.md';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        foreach (['Reception', 'Doctor', 'Cash desk', 'Inventory', 'Manager'] as $section) {
            $this->assertStringContainsString($section, $content);
        }
        $this->assertStringContainsString('Регистратура', $content);
    }

    public function testReleaseNotesCoverPhases(): void
    {
        $path = self::ROOT . '/docs/release-notes.md';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        foreach (['0.14.0', '0.13.0', '0.12.0', '0.11.0', '0.10.0', '0.9.0', '0.8.0', '0.7.0'] as $v) {
            $this->assertStringContainsString($v, $content, "Missing version section $v");
        }
    }

    public function testSmokeStackPresent(): void
    {
        $compose = self::ROOT . '/deploy/smoke/docker-compose.smoke.yml';
        $script = self::ROOT . '/deploy/smoke/smoke.sh';
        $this->assertFileExists($compose);
        $this->assertFileExists($script);
        $this->assertTrue(is_executable($script), 'smoke.sh must be executable');
        $composeContent = (string) file_get_contents($compose);
        $this->assertStringContainsString('mariadb:10.11', $composeContent);
        $this->assertStringContainsString('espocrm/espocrm:9.2.7', $composeContent);
        $this->assertStringContainsString(
            '/var/www/html/custom/Espo/Modules/EspoDental',
            $composeContent
        );
        $scriptContent = (string) file_get_contents($script);
        $this->assertStringContainsString('docker compose', $scriptContent);
        $this->assertStringContainsString('Resources/routes.json', $scriptContent);
    }

    public function testManifestVersionIsCurrent(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(self::ROOT . '/src/manifest.json'),
            true
        );
        $this->assertSame('0.14.0', $manifest['version']);
    }
}
