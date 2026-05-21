<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase21ShiftTemplateTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testDoctorShiftTemplateEntityIsRegistered(): void
    {
        foreach ([
            'Resources/metadata/entityDefs/DoctorShiftTemplate.json',
            'Resources/metadata/scopes/DoctorShiftTemplate.json',
            'Resources/metadata/clientDefs/DoctorShiftTemplate.json',
            'Resources/layouts/DoctorShiftTemplate/detail.json',
            'Resources/layouts/DoctorShiftTemplate/list.json',
            'Resources/layouts/DoctorShiftTemplate/filters.json',
            'Resources/layouts/DoctorShiftTemplate/relationships.json',
            'Resources/i18n/en_US/DoctorShiftTemplate.json',
            'Resources/i18n/ru_RU/DoctorShiftTemplate.json',
            'Resources/i18n/es_ES/DoctorShiftTemplate.json',
        ] as $relative) {
            $path = self::MODULE_ROOT . '/' . $relative;
            $this->assertFileExists($path, "Missing: {$relative}");
            $this->readJson($path);
        }

        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/DoctorShiftTemplate.json');

        foreach (
            [
                'doctor', 'assistant', 'clinic', 'cabinet', 'weekday',
                'timeStart', 'timeEnd', 'dateStart', 'dateEnd', 'type', 'status',
            ] as $field
        ) {
            $this->assertArrayHasKey($field, $def['fields']);
        }

        $this->assertSame(
            ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            $def['fields']['weekday']['options']
        );
        $this->assertSame(['regular', 'additional', 'closed'], $def['fields']['type']['options']);
        $this->assertSame(['active', 'paused'], $def['fields']['status']['options']);
        $this->assertSame('DoctorShift', $def['links']['shifts']['entity']);
    }

    public function testDoctorShiftReferencesTemplate(): void
    {
        $shift = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/DoctorShift.json');
        $detail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/DoctorShift/detail.json');
        $list = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/DoctorShift/list.json');

        $this->assertArrayHasKey('shiftTemplate', $shift['fields']);
        $this->assertSame('DoctorShiftTemplate', $shift['links']['shiftTemplate']['entity']);
        $this->assertSame('shifts', $shift['links']['shiftTemplate']['foreign']);
        $this->assertStringContainsString('shiftTemplate', json_encode([$detail, $list], JSON_THROW_ON_ERROR));
    }

    public function testGenerateActionIsWired(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/DoctorShiftTemplate.json');
        $handler = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/handlers/doctor-shift-template/generate.js'
        );
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/DoctorShiftTemplate.php');

        $buttons = $clientDefs['menu']['detail']['buttons'];
        $this->assertSame('generateShifts', $buttons[0]['name']);
        $this->assertSame('espo-dental:handlers/doctor-shift-template/generate', $buttons[0]['handler']);
        $this->assertSame('actionGenerate', $buttons[0]['actionFunction']);

        $this->assertStringContainsString("DoctorShiftTemplate/action/generate", $handler);
        $this->assertStringContainsString('postActionGenerate', $controller);
        $this->assertStringContainsString('DoctorShiftTemplateService', $controller);
    }

    public function testTemplateServiceGeneratesIdempotentUtcShifts(): void
    {
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/DoctorShiftTemplateService.php');
        $template = (string) file_get_contents(self::MODULE_ROOT . '/Entities/DoctorShiftTemplate.php');
        $hook = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/DoctorShiftTemplate/Defaults.php');

        $this->assertStringContainsString('WEEKDAY_TO_ISO', $template);
        $this->assertStringContainsString('Template generation range cannot exceed 370 days', $service);
        $this->assertStringContainsString('buildUtcDateTimes', $service);
        $this->assertStringContainsString('findExistingShift', $service);
        $this->assertStringContainsString("'shiftTemplateId'", $service);
        $this->assertStringContainsString("'lastGeneratedAt'", $service);
        $this->assertStringContainsString('timeEnd must be after timeStart', $hook);
        $this->assertStringContainsString('resolveDoctorName', (string) file_get_contents(
            self::MODULE_ROOT . '/Hooks/DoctorShift/Defaults.php'
        ));
    }

    public function testTemplateIsVisibleInLocalesRolesAndWorkspace(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");

            $this->assertArrayHasKey('DoctorShiftTemplate', $global['scopeNames']);
            $this->assertArrayHasKey('DoctorShiftTemplate', $global['scopeNamesPlural']);
        }

        $roleSeeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');
        $workspaceSeeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        $this->assertStringContainsString('DoctorShiftTemplate', $roleSeeder);
        $this->assertStringContainsString("\$manager['DoctorShiftTemplate']", $roleSeeder);
        $this->assertStringContainsString(
            "'DoctorShiftTemplate'  => \$row('yes', 'all', 'all', 'no', 'no')",
            $roleSeeder
        );
        $this->assertStringContainsString("'DoctorShiftTemplate'", $workspaceSeeder);
    }

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
}
