<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomCalendarFeedbackTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testWaitlistEntityContractExists(): void
    {
        foreach (
            [
                '/Entities/AppointmentWaitlistEntry.php',
                '/Controllers/AppointmentWaitlistEntry.php',
                '/Resources/metadata/scopes/AppointmentWaitlistEntry.json',
                '/Resources/metadata/entityDefs/AppointmentWaitlistEntry.json',
                '/Resources/metadata/clientDefs/AppointmentWaitlistEntry.json',
                '/Resources/layouts/AppointmentWaitlistEntry/detail.json',
                '/Resources/layouts/AppointmentWaitlistEntry/list.json',
                '/Resources/i18n/en_US/AppointmentWaitlistEntry.json',
                '/Resources/i18n/ru_RU/AppointmentWaitlistEntry.json',
                '/Resources/i18n/es_ES/AppointmentWaitlistEntry.json',
            ] as $relative
        ) {
            $this->assertFileExists(self::MODULE_ROOT . $relative);
        }

        $entity = $this->readFile(self::MODULE_ROOT . '/Entities/AppointmentWaitlistEntry.php');
        $defs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/AppointmentWaitlistEntry.json');

        $this->assertStringContainsString('STATUS_WAITING', $entity);
        $this->assertStringContainsString('PRIORITY_URGENT', $entity);
        $this->assertSame('linkParent', $defs['fields']['parent']['type']);
        $this->assertSame(
            ['waiting', 'offered', 'booked', 'cancelled', 'expired'],
            $defs['fields']['status']['options']
        );
        $this->assertSame(['normal', 'high', 'urgent'], $defs['fields']['priority']['options']);
        $this->assertSame('belongsToParent', $defs['links']['parent']['type']);
    }

    public function testCalendarFeedbackBackendContractExists(): void
    {
        $calendar = $this->readFile(self::MODULE_ROOT . '/Services/CalendarService.php');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/CalendarFeedbackService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Calendar.php');
        $routes = $this->readFile(self::MODULE_ROOT . '/Resources/routes.json');

        $this->assertStringContainsString('resolveParentName', $calendar);
        $this->assertStringContainsString("'patientName' => \$parentName", $calendar);

        foreach (
            [
                'class CalendarFeedbackService',
                'getFeedbackPanel',
                'getWaitlist',
                'getCancelledAppointments',
                'resolveParentName',
                "'patientName' => \$parentName",
                'AppointmentWaitlistEntry::STATUS_WAITING',
                'AppointmentRescheduleRequest::ACTIVE_STATUSES',
                'getRescheduleRequests',
                "'rescheduleRequests'",
                'Appointment::STATUS_CANCELLED',
                'Appointment::STATUS_NO_SHOW',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }

        $this->assertStringContainsString('CalendarFeedbackService', $controller);
        $this->assertStringContainsString('getActionFeedbackPanel', $controller);
        $this->assertStringContainsString('/EspoDental/Calendar/feedbackPanel', $routes);
    }

    public function testFeedbackCalendarDashletUsesNewScopedView(): void
    {
        $metadata = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/dashlets/ResourceCalendar.json');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/resource-calendar-feedback.js');

        $this->assertStringContainsString('espo-dental:views/dashlets/resource-calendar-feedback', $metadata);
        $this->assertStringContainsString('displayRecords', $metadata);
        $this->assertStringContainsString('espo-dental:lib/resource-grid', $view);
        $this->assertStringContainsString('espo-dental:lib/simple-stom-ui', $view);
        $this->assertStringContainsString('EspoDental/Calendar/appointments', $view);
        $this->assertStringContainsString('EspoDental/Calendar/feedbackPanel', $view);
        $this->assertStringContainsString('renderWaitlist', $view);
        $this->assertStringContainsString('renderCancelled', $view);
        $this->assertStringContainsString('openSlotBooking', $view);
    }

    public function testCalendarPostParityFiltersAndRightPanelToggleExist(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/CalendarService.php');
        $feedbackService = $this->readFile(self::MODULE_ROOT . '/Services/CalendarFeedbackService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Calendar.php');
        $dashletView = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/resource-calendar-feedback.js');
        $listView = $this->readFile(self::CLIENT_ROOT . '/views/appointment/record/list.js');

        foreach (
            [
                '?string $doctorId = null',
                "\$appointmentWhere['doctorId'] = \$doctorId",
                'loadDoctorFilterOptions',
                "'filters' =>",
                "'doctors' => \$doctorFilterData",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }

        foreach (
            [
                'doctorId',
                'cabinetId',
                "\$where['requestedDoctorId'] = \$doctorId",
                "\$where['preferredCabinetId'] = \$cabinetId",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $feedbackService);
        }

        foreach (['doctorId', 'cabinetId', 'getQueryParam', 'getFeedbackPanel('] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }

        foreach ([$dashletView, $listView] as $view) {
            foreach (
                [
                    'data-name="mini-calendar"',
                    'doctor-filter',
                    'cabinet-filter',
                    'service-filter',
                    'toggle-side-panel',
                    'sidePanelVisible',
                    'sidePanelMode',
                    'calendar-panel-mode',
                    'renderFeedbackModeButtons',
                    'renderRescheduleRequests',
                    'rescheduleRequests',
                    'Заявки на перенос',
                    'buildCalendarRequestData',
                ] as $needle
            ) {
                $this->assertStringContainsString($needle, $view);
            }
        }
    }

    public function testAppointmentListOpensAsCalendarWorkspace(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Appointment.json');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/appointment/record/list.js');

        $this->assertSame(
            'espo-dental:views/appointment/record/list',
            $clientDefs['recordViews']['list'] ?? null
        );
        $this->assertStringContainsString("'views/record/list'", $view);
        $this->assertStringContainsString('data-name="appointment-calendar-workspace"', $view);
        $this->assertStringContainsString('EspoDental/Calendar/appointments', $view);
        $this->assertStringContainsString('EspoDental/Calendar/feedbackPanel', $view);
        $this->assertStringContainsString('EspoDental/Calendar/move', $view);
        $this->assertStringContainsString('espo-dental:lib/resource-grid', $view);
        $this->assertStringContainsString('openSlotBooking', $view);
    }

    public function testRoleSeederAndRelationshipsIncludeWaitlist(): void
    {
        $roleSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');
        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $patient = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $preliminary = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/entityDefs/PreliminaryPatient.json');
        $appointment = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json');

        $this->assertStringContainsString('AppointmentWaitlistEntry', $roleSeeder);
        $this->assertStringContainsString('AppointmentWaitlistEntry', $workspaceSeeder);
        $this->assertStringContainsString('"waitlistEntries"', $patient);
        $this->assertStringContainsString('"waitlistEntries"', $preliminary);
        $this->assertStringContainsString('"waitlistEntries"', $appointment);
    }

    public function testDocsTrackCalendarFeedbackStage(): void
    {
        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-calendar-feedback.md');

        $this->assertStringContainsString('docs/simple-stom-calendar-feedback.md', $readme);
        $this->assertStringContainsString('| 4. Calendar feedback UX | Completed |', $plan);
        $this->assertStringContainsString('AppointmentWaitlistEntry', $doc);
        $this->assertStringContainsString('GET /EspoDental/Calendar/feedbackPanel', $doc);
        $this->assertStringContainsString('resource-calendar-feedback', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded, "Invalid JSON: {$path}");

        return $decoded;
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
