<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase5MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const ENTITIES = [
        'ServiceCategory', 'Service', 'VisitServiceLine',
        'ToothChartSnapshot', 'VisitPhoto',
    ];
    private const LOCALES = ['ru_RU', 'en_US', 'es_ES'];

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: $path");
        return $data;
    }

    public function testEntityScopesExist(): void
    {
        foreach (self::ENTITIES as $entity) {
            $scope = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$entity}.json");
            $this->assertSame('EspoDental', $scope['module']);
            $this->assertTrue($scope['entity']);
        }
    }

    public function testEntityDefsAndPhpClassesExist(): void
    {
        foreach (self::ENTITIES as $entity) {
            $this->assertFileExists(self::MODULE_ROOT . "/Resources/metadata/entityDefs/{$entity}.json");
            $this->assertFileExists(self::MODULE_ROOT . "/Entities/{$entity}.php");
        }
    }

    public function testServiceLinksToCategoryAndVisitLines(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Service.json');
        $this->assertSame('belongsTo', $def['links']['category']['type']);
        $this->assertSame('services', $def['links']['category']['foreign']);
        $this->assertSame('hasMany', $def['links']['visitLines']['type']);
    }

    public function testVisitServiceLineLinks(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitServiceLine.json');
        $this->assertSame('belongsTo', $def['links']['visit']['type']);
        $this->assertSame('serviceLines', $def['links']['visit']['foreign']);
        $this->assertSame('belongsTo', $def['links']['service']['type']);
        $this->assertSame('visitLines', $def['links']['service']['foreign']);
    }

    public function testVisitExtendedLinks(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Visit.json');
        foreach (['serviceLines', 'photos', 'toothChartSnapshots'] as $link) {
            $this->assertArrayHasKey($link, $def['links'], "Missing Visit link {$link}");
            $this->assertSame('hasMany', $def['links'][$link]['type']);
        }
    }

    public function testPatientExtendedLinks(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $this->assertArrayHasKey('toothChartSnapshots', $def['links']);
        $this->assertArrayHasKey('photos', $def['links']);
    }

    public function testToothChartDentitionEnumHasThreeOptions(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/ToothChartSnapshot.json');
        $this->assertSame(['adult', 'child', 'mixed'], $def['fields']['dentitionType']['options']);
    }

    public function testToothChartTeethIsJsonObject(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/ToothChartSnapshot.json');
        $this->assertSame('jsonObject', $def['fields']['teeth']['type']);
    }

    public function testVisitPhotoStageEnum(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitPhoto.json');
        $this->assertSame(['before', 'during', 'after'], $def['fields']['stage']['options']);
    }

    public function testRecalculateAmountHookExists(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/VisitServiceLine/RecalculateAmount.php');
        $code = (string) file_get_contents(
            self::MODULE_ROOT . '/Hooks/VisitServiceLine/RecalculateAmount.php'
        );
        $this->assertMatchesRegularExpression('/public\s+static\s+int\s+\$order\s*=\s*5/', $code);
        $this->assertStringContainsString('beforeSave', $code);
    }

    public function testVisitServiceAndControllerExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Services/VisitService.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Controllers/Visit.php');
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Visit.php');
        $this->assertStringContainsString('postActionFinishVisit', $code);
    }

    public function testToothChartCustomRecordViewWired(): void
    {
        $clientDefs = $this->readJson(
            self::MODULE_ROOT . '/Resources/metadata/clientDefs/ToothChartSnapshot.json'
        );
        $this->assertSame(
            'espo-dental:views/tooth-chart-snapshot/record/detail',
            $clientDefs['recordViews']['detail']
        );
        $this->assertFileExists(
            __DIR__ .
            '/../src/files/client/custom/modules/espo-dental/src/views/tooth-chart-snapshot/record/detail.js'
        );
        $this->assertFileExists(
            __DIR__ .
            '/../src/files/client/custom/modules/espo-dental/src/tooth-chart/renderer.js'
        );
    }

    public function testRendererHasFdiQuadrants(): void
    {
        $code = (string) file_get_contents(
            __DIR__ .
            '/../src/files/client/custom/modules/espo-dental/src/tooth-chart/renderer.js'
        );
        foreach (['18', '11', '21', '28', '38', '31', '41', '48'] as $adult) {
            $this->assertStringContainsString("'{$adult}'", $code, "Adult tooth {$adult} missing");
        }
        foreach (['55', '51', '61', '65', '75', '71', '81', '85'] as $child) {
            $this->assertStringContainsString("'{$child}'", $code, "Child tooth {$child} missing");
        }
    }

    public function testLocalesForNewEntities(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (self::ENTITIES as $entity) {
                $path = self::MODULE_ROOT . "/Resources/i18n/{$locale}/{$entity}.json";
                $this->assertFileExists($path);
                $loc = $this->readJson($path);
                $this->assertArrayHasKey('fields', $loc);
            }
        }
    }

    public function testGlobalScopeNamesExtendedForPhase5(): void
    {
        foreach (self::LOCALES as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            foreach (self::ENTITIES as $entity) {
                $this->assertArrayHasKey($entity, $global['scopeNames']);
                $this->assertArrayHasKey($entity, $global['scopeNamesPlural']);
            }
        }
    }

    public function testAfterInstallSeedsServiceCategoriesAndScopes(): void
    {
        $code = (string) file_get_contents(__DIR__ . '/../src/scripts/AfterInstall.php');
        $this->assertStringContainsString("'ServiceCategory'", $code);
        $this->assertStringContainsString("'Service'", $code);
        $this->assertStringContainsString("'VisitServiceLine'", $code);
        $this->assertStringContainsString("'ToothChartSnapshot'", $code);
        $this->assertStringContainsString("'VisitPhoto'", $code);
        $this->assertStringContainsString('SERVICE_CATEGORIES', $code);
    }
}
