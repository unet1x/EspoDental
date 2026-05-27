<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase61PreflightAwareQueueProcessingTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testNotificationDeliverySkipsPreflightBlockedRowsWithoutMutating(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/NotificationDeliveryService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/NotificationLog.php');

        foreach (
            [
                '$this->messageDeliveryGateway->preflight($log)',
                'NotificationLog::STATUS_SKIPPED',
                'skippedReason',
                '$preflight[\'error\']',
                '$preflight[\'provider\']',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }

        $preflightBlock = $this->blockStartingAt($service, '$preflight = $this->messageDeliveryGateway->preflight($log);');
        $this->assertStringContainsString('return [', $preflightBlock);
        $this->assertStringNotContainsString('saveEntity', $preflightBlock);
        $this->assertStringContainsString("(\$stats['processed'] + \$stats['skipped']) >= \$limit", $service);
        $this->assertStringContainsString('$stats[\'skipped\']++', $service);

        $this->assertStringContainsString('$skipped = $row[\'status\'] === NotificationLogEntity::STATUS_SKIPPED', $controller);
        $this->assertStringContainsString("'processed' => \$skipped ? 0 : 1", $controller);
    }

    public function testUiAndLocalesSurfacePreflightSkip(): void
    {
        $dashlet = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');
        $handler = $this->readFile(self::CLIENT_ROOT . '/handlers/notification-log/process-queued.js');

        $this->assertStringContainsString('Пропущено preflight', $dashlet);
        $this->assertStringContainsString('Queued notification skipped by preflight', $handler);

        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $labels = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/NotificationLog.json");

            $this->assertArrayHasKey('Queued notification skipped by preflight', $labels['messages']);
            $this->assertStringContainsString('preflight', $labels['messages']['Process queued notification confirmation']);
        }
    }

    public function testDocsTrackPreflightAwareProcessing(): void
    {
        foreach (
            [
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/integration-architecture.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('preflight-blocked', $doc);
            $this->assertStringContainsString('remains queued', $doc);
        }
    }

    private function blockStartingAt(string $code, string $needle): string
    {
        $start = strpos($code, $needle);
        $this->assertIsInt($start);

        $end = strpos($code, '$attemptedAt =', $start);
        $this->assertIsInt($end);

        return substr($code, $start, $end - $start);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);

        $contents = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($contents, "Invalid JSON: {$path}");

        return $contents;
    }
}
