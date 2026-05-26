<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomSlotBookingTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testAppointmentServiceExposesSlotBookingContract(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/AppointmentService.php');

        foreach (
            [
                'searchBookingCandidates',
                'bookFromSlot',
                'durationMinutes must be between 15 and 180',
                'resolveBookingParent',
                'PreliminaryPatient::ENTITY_TYPE',
                'Appointment::STATUS_PLANNED',
                'lastName and firstName are required',
                'phone is required',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testAppointmentControllerAndRoutesExposeBookingActions(): void
    {
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Appointment.php');
        $routes = $this->readFile(self::MODULE_ROOT . '/Resources/routes.json');

        $this->assertStringContainsString('getActionBookingCandidates', $controller);
        $this->assertStringContainsString('postActionBookFromSlot', $controller);
        $this->assertStringContainsString('/EspoDental/Appointment/bookingCandidates', $routes);
        $this->assertStringContainsString('/EspoDental/Appointment/bookFromSlot', $routes);
    }

    public function testSlotBookingModalSearchesAndBooksFromCalendarCell(): void
    {
        $modal = $this->readFile(self::CLIENT_ROOT . '/views/appointment/modals/slot-booking.js');
        $calendar = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/resource-calendar-feedback.js');
        $list = $this->readFile(self::CLIENT_ROOT . '/views/appointment/record/list.js');
        $grid = $this->readFile(self::CLIENT_ROOT . '/lib/resource-grid.js');

        foreach (
            [
                'EspoDental/Appointment/bookingCandidates',
                'EspoDental/Appointment/bookFromSlot',
                'buildDurationOptions',
                'freeWindowMinutes',
                'ensureContainer',
                'selectCandidate',
                'Новый предварительный пациент',
                'durationMinutes',
                'serviceId',
                'applySelectedServiceDuration',
                'Услуга',
                'durationHint',
                'getBookingErrorMessage',
                'Этот кабинет не подходит для выбранной услуги',
                'У врача уже есть запись на это время',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $modal);
        }

        $this->assertStringContainsString('calculateFreeWindowMinutes', $grid);
        $this->assertStringContainsString('sameDoctor', $grid);
        $this->assertStringContainsString('self.calculateFreeWindowMinutes(cabinetId, start)', $grid);
        $this->assertStringContainsString('espo-dental:views/appointment/modals/slot-booking', $calendar);
        $this->assertStringContainsString('createView', $calendar);
        $this->assertStringContainsString('openSlotBooking', $calendar);
        $this->assertStringContainsString('serviceOptions', $calendar);
        $this->assertStringContainsString('serviceId: this.serviceId', $calendar);
        $this->assertStringContainsString('doctorId: self.doctorId', $calendar);
        $this->assertStringContainsString('doctorId: self.doctorId', $list);
        $this->assertStringNotContainsString('freeWindowMinutes: 180', $calendar);
        $this->assertStringNotContainsString('freeWindowMinutes: 180', $list);
    }

    public function testSlotBookingRespectsServiceCabinetRequirements(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/AppointmentService.php');
        $calendar = $this->readFile(self::MODULE_ROOT . '/Services/CalendarService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Calendar.php');
        $matcher = $this->readFile(self::MODULE_ROOT . '/Tools/CabinetRequirementMatcher.php');
        $appointment = json_decode(
            $this->readFile(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        foreach (
            [
                'resolveBookingService',
                'assertCabinetMatchesServiceRequirements',
                'CabinetRequirementMatcher',
                'Selected cabinet does not match service requirements',
                "set('serviceId'",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }

        foreach (
            [
                '?string $serviceId = null',
                'loadServiceFilterOptions',
                'filterCabinetsByService',
                "'services' => \$serviceFilterData",
                'CabinetRequirementMatcher',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $calendar);
        }

        $this->assertStringContainsString('getQueryParam(\'serviceId\')', $controller);
        $this->assertStringContainsString('equipmentAny', $matcher);
        $this->assertStringContainsString('cabinetCodes', $matcher);
        $this->assertStringContainsString('cabinetIds', $matcher);

        $this->assertSame('link', $appointment['fields']['service']['type']);
        $this->assertSame('belongsTo', $appointment['links']['service']['type']);
        $this->assertSame('Service', $appointment['links']['service']['entity']);
    }

    public function testDocsTrackSlotBookingStage(): void
    {
        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-slot-booking.md');

        $this->assertStringContainsString('docs/simple-stom-slot-booking.md', $readme);
        $this->assertStringContainsString('| 5. Slot booking wizard | Completed |', $plan);
        $this->assertStringContainsString('GET /EspoDental/Appointment/bookingCandidates', $doc);
        $this->assertStringContainsString('POST /EspoDental/Appointment/bookFromSlot', $doc);
        $this->assertStringContainsString('15-minute', $doc);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
