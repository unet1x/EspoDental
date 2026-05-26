<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the questionnaire schema is well-formed and consistent
 * across all supported languages, and that all alert flags propagate.
 */
final class QuestionnaireSchemaTest extends TestCase
{
    private const SCHEMA_PATH =
        __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental/Resources/metadata/dental/questionnaireSchema.json';
    private const LANGUAGES = ['ru_RU', 'en_US', 'es_ES'];

    public function testSchemaIsValidJson(): void
    {
        $data = $this->loadSchema();
        $this->assertSame(2, $data['version']);
        $this->assertIsArray($data['groups']);
        $this->assertGreaterThan(0, count($data['groups']));
    }

    #[DataProvider('languageProvider')]
    public function testEveryItemHasLabelInEveryLanguage(string $language): void
    {
        $data = $this->loadSchema();
        foreach ($data['groups'] as $group) {
            $this->assertArrayHasKey(
                $language,
                $group['labels'],
                "Group {$group['id']} missing label in {$language}"
            );
            $this->assertNotEmpty($group['labels'][$language]);
            foreach ($group['items'] as $item) {
                $this->assertArrayHasKey(
                    $language,
                    $item['labels'] ?? [],
                    "Item {$item['id']} missing label in {$language}"
                );
                $this->assertNotEmpty($item['labels'][$language]);
            }
        }
    }

    public function testItemIdsAreUnique(): void
    {
        $data = $this->loadSchema();
        $ids = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $ids[] = $item['id'];
            }
        }
        $this->assertCount(count(array_unique($ids)), $ids, 'Item ids are not unique');
    }

    public function testItemTypesAreSupported(): void
    {
        $data = $this->loadSchema();
        foreach ($data['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $this->assertContains(
                    $item['type'] ?? 'bool',
                    ['bool', 'text'],
                    "Unsupported item type for {$item['id']}"
                );
            }
        }
    }

    public function testAlertFlagOnlyOnBoolItems(): void
    {
        $data = $this->loadSchema();
        foreach ($data['groups'] as $group) {
            foreach ($group['items'] as $item) {
                if (!empty($item['alert'])) {
                    $this->assertSame('bool', $item['type'] ?? 'bool', "Alert flag on non-bool item {$item['id']}");
                }
            }
        }
    }

    public function testHasMinimumExpectedAlerts(): void
    {
        $data = $this->loadSchema();
        $alerts = 0;
        foreach ($data['groups'] as $group) {
            foreach ($group['items'] as $item) {
                if (!empty($item['alert'])) {
                    $alerts++;
                }
            }
        }
        $this->assertGreaterThanOrEqual(8, $alerts, 'Expected at least 8 alert-flagged items');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function languageProvider(): iterable
    {
        foreach (self::LANGUAGES as $lang) {
            yield $lang => [$lang];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSchema(): array
    {
        $raw = file_get_contents(self::SCHEMA_PATH);
        $this->assertIsString($raw);
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
