<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase12MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = __DIR__ . '/../src/files/client/custom/modules/espo-dental/src';
    private const ENTITIES = [
        'OrthodonticCard', 'TreatmentStage', 'ToothMovementPlan',
        'OrthoPhoto', 'CephalometricMeasurement',
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

    public function testScopesExist(): void
    {
        foreach (self::ENTITIES as $e) {
            $scope = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$e}.json");
            $this->assertTrue($scope['entity']);
        }
    }

    public function testOrthodonticCardEnums(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/OrthodonticCard.json');
        foreach (['open', 'in_treatment', 'retention', 'completed', 'cancelled'] as $s) {
            $this->assertContains($s, $def['fields']['status']['options']);
        }
        foreach (['I', 'II_div1', 'II_div2', 'III', 'crossbite', 'openbite', 'deepbite', 'other'] as $c) {
            $this->assertContains($c, $def['fields']['malocclusionClass']['options']);
        }
        foreach ([
            'brackets_metal', 'brackets_ceramic', 'brackets_lingual',
            'aligners', 'plates', 'headgear', 'twin_block', 'retainer', 'other',
        ] as $a) {
            $this->assertContains($a, $def['fields']['apparatusType']['options']);
        }
        $this->assertTrue($def['indexes']['cardNumber']['unique']);
    }

    public function testOrthodonticCardLinks(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/OrthodonticCard.json');
        foreach (['patient', 'clinic', 'doctor', 'stages', 'toothMovementPlans', 'photos', 'cephMeasurements'] as $l) {
            $this->assertArrayHasKey($l, $def['links']);
        }
    }

    public function testPatientLinksOrthodonticCards(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $this->assertArrayHasKey('orthodonticCards', $def['links']);
        $this->assertSame('hasMany', $def['links']['orthodonticCards']['type']);
    }

    public function testToothMovementPlanHasMovementFields(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/ToothMovementPlan.json');
        foreach ([
            'rotationDeg', 'intrusionMm', 'extrusionMm',
            'mesialMm', 'distalMm', 'buccalMm', 'lingualMm', 'torqueDeg',
        ] as $f) {
            $this->assertArrayHasKey($f, $def['fields']);
            $this->assertSame('float', $def['fields'][$f]['type']);
        }
    }

    public function testOrthoPhotoTypeOptions(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/OrthoPhoto.json');
        foreach ([
            'extra_front', 'extra_smile', 'extra_profile', 'extra_3q',
            'intra_front', 'intra_lateral_right', 'intra_lateral_left',
            'intra_upper_occlusal', 'intra_lower_occlusal',
            'xray_panoramic', 'xray_cephalometric',
            'model_upper', 'model_lower', 'other',
        ] as $t) {
            $this->assertContains($t, $def['fields']['type']['options']);
        }
        $this->assertSame('file', $def['fields']['file']['type']);
    }

    public function testCephalometricCodeOptions(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/CephalometricMeasurement.json');
        foreach (['SNA', 'SNB', 'ANB', 'FMA', 'U1_SN', 'IMPA', 'Interincisal'] as $c) {
            $this->assertContains($c, $def['fields']['code']['options']);
        }
    }

    public function testEntityPhpFiles(): void
    {
        foreach (self::ENTITIES as $e) {
            $path = self::MODULE_ROOT . "/Entities/{$e}.php";
            $this->assertFileExists($path);
            $code = (string) file_get_contents($path);
            $this->assertStringContainsString("const ENTITY_TYPE = '{$e}'", $code);
        }
        $card = (string) file_get_contents(self::MODULE_ROOT . '/Entities/OrthodonticCard.php');
        $this->assertStringContainsString('ACTIVE_STATUSES', $card);
        $this->assertStringContainsString('isActive', $card);
    }

    public function testAssignNumberHook(): void
    {
        $hook = self::MODULE_ROOT . '/Hooks/OrthodonticCard/AssignNumber.php';
        $this->assertFileExists($hook);
        $code = (string) file_get_contents($hook);
        $this->assertStringContainsString('ORTHO-', $code);
        $this->assertStringContainsString('beforeSave', $code);
    }

    public function testServiceAndControllerWithRoutes(): void
    {
        $svc = self::MODULE_ROOT . '/Services/OrthodonticCardService.php';
        $ctrl = self::MODULE_ROOT . '/Controllers/OrthodonticCard.php';
        $this->assertFileExists($svc);
        $this->assertFileExists($ctrl);
        $svcCode = (string) file_get_contents($svc);
        $this->assertStringContainsString('closeCard', $svcCode);
        $this->assertStringContainsString('reopenCard', $svcCode);
        $ctrlCode = (string) file_get_contents($ctrl);
        $this->assertStringContainsString('postActionClose', $ctrlCode);
        $this->assertStringContainsString('postActionReopen', $ctrlCode);

        $routes = json_decode((string) file_get_contents(self::MODULE_ROOT . '/Resources/routes.json'), true);
        $paths = array_column($routes, 'route');
        $this->assertContains('/EspoDental/OrthodonticCard/close', $paths);
        $this->assertContains('/EspoDental/OrthodonticCard/reopen', $paths);
    }

    public function testClientHandlers(): void
    {
        $this->assertFileExists(self::CLIENT_ROOT . '/handlers/orthodontic-card/close.js');
        $this->assertFileExists(self::CLIENT_ROOT . '/handlers/orthodontic-card/reopen.js');
    }

    public function testBoolFiltersAndSelectDefs(): void
    {
        $sel = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/selectDefs/OrthodonticCard.json');
        $this->assertArrayHasKey('activeCards', $sel['boolFilterClassNameMap']);
        $this->assertArrayHasKey('myDoctor', $sel['boolFilterClassNameMap']);
        $this->assertFileExists(self::MODULE_ROOT . '/Classes/Select/OrthodonticCard/BoolFilters/ActiveCards.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Classes/Select/OrthodonticCard/BoolFilters/MyDoctor.php');
    }

    public function testActiveOrthoCasesDashlet(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/dashlets/ActiveOrthoCases.json');
        $this->assertSame('espo-dental:views/dashlets/active-ortho-cases', $def['view']);
        $this->assertFileExists(self::CLIENT_ROOT . '/views/dashlets/active-ortho-cases.js');
    }

    public function testGlobalScopeNamesExtended(): void
    {
        foreach (self::LOCALES as $locale) {
            $g = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            foreach (self::ENTITIES as $e) {
                $this->assertArrayHasKey($e, $g['scopeNames']);
                $this->assertArrayHasKey($e, $g['scopeNamesPlural']);
            }
            $this->assertArrayHasKey('ActiveOrthoCases', $g['dashlets']);
            $this->assertArrayHasKey('activeCards', $g['boolFilters']);
        }
    }

    public function testPerEntityLocalesExist(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (self::ENTITIES as $e) {
                $loc = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/{$e}.json");
                $this->assertArrayHasKey('fields', $loc);
            }
        }
    }

    public function testAfterInstallContainsOrthoScopes(): void
    {
        $code = (string) file_get_contents(__DIR__ . '/../src/scripts/AfterInstall.php');
        foreach (self::ENTITIES as $e) {
            $this->assertStringContainsString("'{$e}'", $code);
        }
    }
}
