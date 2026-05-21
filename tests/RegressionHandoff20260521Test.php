<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class RegressionHandoff20260521Test extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testResourceCalendarUsesClinicLocalDisplayAndUtcPersistence(): void
    {
        $calendar = (string) file_get_contents(self::MODULE_ROOT . '/Services/CalendarService.php');
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Calendar.php');
        $grid = (string) file_get_contents(self::CLIENT_ROOT . '/lib/resource-grid.js');

        $this->assertStringContainsString('$startLocal = new DateTimeImmutable($date . \' 00:00:00\', $timeZone);', $calendar);
        $this->assertStringContainsString("'localStart' => \$localStart", $calendar);
        $this->assertStringContainsString("'localEnd' => \$localEnd", $calendar);
        $this->assertStringContainsString("'timezone' => \$appointmentTimeZone->getName()", $calendar);
        $this->assertStringContainsString('parseClinicLocalDateTime', $calendar);
        $this->assertStringContainsString('$localStart = isset($data->localStart)', $controller);
        $this->assertStringContainsString('$timeZone = isset($data->timezone)', $controller);

        $this->assertStringContainsString('a.localStart || a.dateStart', $grid);
        $this->assertStringContainsString("card.setAttribute('data-start', displayStart)", $grid);
        $this->assertStringContainsString('localStart: startStr', $grid);
        $this->assertStringContainsString('localEnd: addMinutes(startStr', $grid);
        $this->assertStringContainsString('timezone: self.timezone', $grid);
    }

    public function testGlobalAppointmentQuickCreateIsRemoved(): void
    {
        $seeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $start = strpos($seeder, "'quickCreateList' => [");

        $this->assertIsInt($start);
        $segment = substr($seeder, $start, 220);

        $this->assertStringContainsString("'PreliminaryPatient'", $segment);
        $this->assertStringNotContainsString("'Appointment'", $segment);
    }

    public function testFinishVisitRunsDownstreamBeforeStatusesAndIsIdempotent(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/VisitService.php');

        $this->assertStringContainsString('[Visit::STATUS_IN_PROGRESS, Visit::STATUS_FINISHED]', $code);
        $this->assertStringContainsString('getTransactionManager()->run', $code);
        $this->assertStringContainsString('finishVisitInTransaction', $code);
        $this->assertStringContainsString('markVisitFinished', $code);
        $this->assertStringContainsString('markAppointmentFinished', $code);

        $invoicePosition = strpos($code, 'buildFromVisit($visit)');
        $stockPosition = strpos($code, 'consumeForVisit($visit)');
        $statusPosition = strpos($code, 'markVisitFinished($visit, $total)');

        $this->assertIsInt($invoicePosition);
        $this->assertIsInt($stockPosition);
        $this->assertIsInt($statusPosition);
        $this->assertLessThan($statusPosition, $invoicePosition);
        $this->assertLessThan($statusPosition, $stockPosition);
    }

    public function testPaymentAcceptValidatesInvoiceServerSide(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/PaymentService.php');

        $this->assertStringContainsString('assertInvoicePayable', $code);
        $this->assertStringContainsString('Invoice::STATUS_DRAFT', $code);
        $this->assertStringContainsString('Invoice::STATUS_PAID', $code);
        $this->assertStringContainsString('Invoice::STATUS_STORNO', $code);
        $this->assertStringContainsString('Invoice::STATUS_CANCELLED', $code);
        $this->assertStringContainsString('patientId does not match invoice', $code);
        $this->assertStringContainsString('clinicId does not match invoice', $code);
        $this->assertStringContainsString('Payment amount exceeds invoice balance', $code);
    }

    public function testAppointmentFinalStatusIsFinishedEverywhere(): void
    {
        $appointment = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json');
        $grid = (string) file_get_contents(self::CLIENT_ROOT . '/lib/resource-grid.js');
        $productSpec = (string) file_get_contents(self::ROOT . '/docs/product-spec.md');
        $acceptance = (string) file_get_contents(self::ROOT . '/docs/acceptance-checklist.md');
        $roadmap = (string) file_get_contents(self::ROOT . '/docs/roadmap.md');

        $this->assertContains('finished', $appointment['fields']['status']['options']);
        $this->assertNotContains('completed', $appointment['fields']['status']['options']);
        $this->assertStringContainsString("finished: '#7F7F7F'", $grid);
        $this->assertStringContainsString('appointment status becomes `finished`', $productSpec);
        $this->assertStringContainsString('Confirm appointment status is `finished`', $acceptance);
        $this->assertStringContainsString('appointment status `finished`', $roadmap);
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
