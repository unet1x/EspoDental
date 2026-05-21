<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase20ScheduleAvailabilityTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testDoctorShiftEntityIsRegistered(): void
    {
        foreach ([
            'Resources/metadata/entityDefs/DoctorShift.json',
            'Resources/metadata/scopes/DoctorShift.json',
            'Resources/metadata/clientDefs/DoctorShift.json',
            'Resources/layouts/DoctorShift/detail.json',
            'Resources/layouts/DoctorShift/list.json',
            'Resources/layouts/DoctorShift/filters.json',
            'Resources/i18n/en_US/DoctorShift.json',
            'Resources/i18n/ru_RU/DoctorShift.json',
            'Resources/i18n/es_ES/DoctorShift.json',
        ] as $relative) {
            $path = self::MODULE_ROOT . '/' . $relative;
            $this->assertFileExists($path, "Missing: {$relative}");
            $this->readJson($path);
        }

        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/DoctorShift.json');

        foreach (['doctor', 'assistant', 'clinic', 'cabinet', 'dateStart', 'dateEnd', 'type', 'status'] as $field) {
            $this->assertArrayHasKey($field, $def['fields']);
        }

        $this->assertSame(['regular', 'additional', 'closed'], $def['fields']['type']['options']);
        $this->assertSame(['active', 'cancelled'], $def['fields']['status']['options']);
        $this->assertSame('User', $def['links']['doctor']['entity']);
        $this->assertSame('User', $def['links']['assistant']['entity']);
    }

    public function testDoctorShiftIsVisibleInLocalesRolesAndWorkspace(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");

            $this->assertArrayHasKey('DoctorShift', $global['scopeNames']);
            $this->assertArrayHasKey('DoctorShift', $global['scopeNamesPlural']);
        }

        $roleSeeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');
        $workspaceSeeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        $this->assertStringContainsString(
            "'Appointment', 'DoctorShiftTemplate', 'DoctorShift', 'AppointmentStatusLog'",
            $roleSeeder
        );
        $this->assertStringContainsString("\$manager['DoctorShift']", $roleSeeder);
        $this->assertStringContainsString("'DoctorShift'          => \$row('yes', 'all', 'all', 'no', 'no')", $roleSeeder);
        $this->assertStringContainsString("'DoctorShift'", $workspaceSeeder);
    }

    public function testCalendarFreeSlotsUseDoctorShiftAvailability(): void
    {
        $calendar = (string) file_get_contents(self::MODULE_ROOT . '/Services/CalendarService.php');
        $availability = (string) file_get_contents(self::MODULE_ROOT . '/Tools/DoctorShiftAvailability.php');

        $this->assertStringContainsString('DoctorShiftAvailability', $calendar);
        $this->assertStringContainsString('loadForRange', $calendar);
        $this->assertStringContainsString('evaluateSlot', $calendar);
        $this->assertStringContainsString("'assistantId' => \$assistantId", $calendar);

        $this->assertStringContainsString('TYPE_REGULAR', $availability);
        $this->assertStringContainsString('TYPE_ADDITIONAL', $availability);
        $this->assertStringContainsString('TYPE_CLOSED', $availability);
        $this->assertStringContainsString('hasAvailability', $availability);
        $this->assertStringContainsString('hasAnyAvailability', $availability);
        $this->assertStringContainsString('Doctor has no active shift for this time.', $availability);
    }

    public function testAppointmentSaveValidatesShiftAndDerivesAssistant(): void
    {
        $frontDeskFlow = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/Appointment/FrontDeskFlow.php');
        $conflicts = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/Appointment/CheckConflicts.php');

        $this->assertStringContainsString('applyAssistantFromShift', $frontDeskFlow);
        $this->assertStringContainsString("\$appointment->set('assistantId', \$result['assistantId'])", $frontDeskFlow);
        $this->assertStringContainsString('appointmentLocalDayRange', $frontDeskFlow);

        $this->assertStringContainsString('guardDoctorShift', $conflicts);
        $this->assertStringContainsString('DoctorShiftAvailability', $conflicts);
        $this->assertStringContainsString('Doctor is not available at this time.', $conflicts);
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
