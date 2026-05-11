<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase4MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const ENTITIES = ['Appointment', 'AppointmentStatusLog', 'Visit'];
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

    public function testAppointmentCalendarEnabled(): void
    {
        $scope = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/scopes/Appointment.json');
        $this->assertTrue($scope['calendar']);
    }

    public function testAppointmentHasPolymorphicParent(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json');

        $this->assertArrayHasKey('parent', $def['fields']);
        $this->assertSame('linkParent', $def['fields']['parent']['type']);
        $this->assertSame(['Patient', 'PreliminaryPatient'], $def['fields']['parent']['entityList']);

        $this->assertArrayHasKey('parent', $def['links']);
        $this->assertSame('belongsToParent', $def['links']['parent']['type']);
        $this->assertSame('appointments', $def['links']['parent']['foreign']);
    }

    public function testAppointmentStatusHasSevenOptions(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json');
        $statusOpts = $def['fields']['status']['options'];

        $this->assertCount(7, $statusOpts);
        foreach ([
            'planned', 'rescheduled', 'cancelled', 'arrived',
            'in_progress', 'finished', 'no_show',
        ] as $expected) {
            $this->assertContains($expected, $statusOpts);
        }
    }

    public function testPatientAndPrelimHaveAppointmentsChildLink(): void
    {
        $patient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $this->assertArrayHasKey('appointments', $patient['links']);
        $this->assertSame('hasChildren', $patient['links']['appointments']['type']);
        $this->assertSame('parent', $patient['links']['appointments']['foreign']);

        $prelim = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/PreliminaryPatient.json');
        $this->assertArrayHasKey('appointments', $prelim['links']);
        $this->assertSame('hasChildren', $prelim['links']['appointments']['type']);
    }

    public function testPatientHasVisitsLink(): void
    {
        $patient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $this->assertArrayHasKey('visits', $patient['links']);
        $this->assertSame('hasMany', $patient['links']['visits']['type']);
    }

    public function testCabinetAndClinicHaveAppointmentsLink(): void
    {
        $cabinet = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Cabinet.json');
        $this->assertArrayHasKey('appointments', $cabinet['links']);

        $clinic = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Clinic.json');
        $this->assertArrayHasKey('appointments', $clinic['links']);
        $this->assertArrayHasKey('visits', $clinic['links']);
    }

    public function testStatusLogHasAppointmentBack(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/AppointmentStatusLog.json');
        $this->assertSame('belongsTo', $def['links']['appointment']['type']);
        $this->assertSame('statusLogs', $def['links']['appointment']['foreign']);
    }

    public function testVisitDefinition(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Visit.json');
        $this->assertSame('hasOne', $this->readJson(
            self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json'
        )['links']['visit']['type']);
        $this->assertSame('belongsTo', $def['links']['appointment']['type']);
        $this->assertSame('visit', $def['links']['appointment']['foreign']);

        $statusOpts = $def['fields']['status']['options'];
        $this->assertSame(['in_progress', 'finished', 'cancelled'], $statusOpts);
    }

    public function testHookFilesExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/Appointment/CheckConflicts.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/Appointment/StatusLog.php');
    }

    public function testServiceAndControllerExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Services/AppointmentService.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Controllers/Appointment.php');
    }

    public function testEntityPhpClassesExist(): void
    {
        foreach (self::ENTITIES as $entity) {
            $this->assertFileExists(self::MODULE_ROOT . "/Entities/{$entity}.php");
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

    public function testGlobalScopeNamesExtended(): void
    {
        foreach (self::LOCALES as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            foreach (self::ENTITIES as $entity) {
                $this->assertArrayHasKey(
                    $entity,
                    $global['scopeNames'],
                    "Missing {$entity} in {$locale}/Global.json scopeNames"
                );
                $this->assertArrayHasKey(
                    $entity,
                    $global['scopeNamesPlural'],
                    "Missing {$entity} in {$locale}/Global.json scopeNamesPlural"
                );
            }
        }
    }

    public function testStatusLogHookExposesOrder(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/Appointment/StatusLog.php');
        $this->assertMatchesRegularExpression('/public\s+static\s+int\s+\$order\s*=\s*90/', $code);
    }

    public function testCheckConflictsHookExposesOrder(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/Appointment/CheckConflicts.php');
        $this->assertMatchesRegularExpression('/public\s+static\s+int\s+\$order\s*=\s*9/', $code);
    }

    public function testAppointmentEntityHasBlockingStatusesConstant(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Entities/Appointment.php');
        $this->assertStringContainsString('BLOCKING_STATUSES', $code);
        $this->assertStringContainsString("STATUS_PLANNED", $code);
        $this->assertStringContainsString("STATUS_RESCHEDULED", $code);
        $this->assertStringContainsString("STATUS_ARRIVED", $code);
        $this->assertStringContainsString("STATUS_IN_PROGRESS", $code);
    }

    public function testStartVisitHandlerExists(): void
    {
        $this->assertFileExists(
            __DIR__ .
            '/../src/files/client/custom/modules/espo-dental/src/handlers/appointment/start-visit.js'
        );
    }

    public function testAfterInstallSeedsAppointmentScope(): void
    {
        $code = (string) file_get_contents(__DIR__ . '/../src/scripts/AfterInstall.php');
        $this->assertStringContainsString("'Appointment'", $code);
        $this->assertStringContainsString("'AppointmentStatusLog'", $code);
        $this->assertStringContainsString("'Visit'", $code);
    }
}
