<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase25NextAppointmentLoopTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testVisitAndInvoiceExposeBookNextAppointmentAction(): void
    {
        $visitClient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Visit.json');
        $invoiceClient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Invoice.json');

        $visitButton = $this->findButton($visitClient, 'bookNextAppointment');
        $invoiceButton = $this->findButton($invoiceClient, 'bookNextAppointment');

        $this->assertSame('Book Next Appointment', $visitButton['label']);
        $this->assertSame('espo-dental:handlers/visit/book-next-appointment', $visitButton['handler']);
        $this->assertSame('actionBookNextAppointment', $visitButton['actionFunction']);
        $this->assertSame('isBookNextAppointmentAvailable', $visitButton['checkVisibilityFunction']);

        $this->assertSame('Book Next Appointment', $invoiceButton['label']);
        $this->assertSame('espo-dental:handlers/invoice/book-next-appointment', $invoiceButton['handler']);
        $this->assertSame('actionBookNextAppointment', $invoiceButton['actionFunction']);
        $this->assertSame('isBookNextAppointmentAvailable', $invoiceButton['checkVisibilityFunction']);
    }

    public function testBookNextHandlersOpenShortAppointmentModalWithContext(): void
    {
        $visitHandler = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/handlers/visit/book-next-appointment.js'
        );
        $invoiceHandler = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/handlers/invoice/book-next-appointment.js'
        );

        foreach ([$visitHandler, $invoiceHandler] as $handler) {
            $this->assertStringContainsString('helpers/record-modal', $handler);
            $this->assertStringContainsString("entityType: 'Appointment'", $handler);
            $this->assertStringContainsString("parentType: 'Patient'", $handler);
            $this->assertStringContainsString("fullFormDisabled: true", $handler);
            $this->assertStringContainsString("layoutName: 'detailSmall'", $handler);
            $this->assertStringContainsString('Appointment booked for', $handler);
            $this->assertStringContainsString('Patient is required', $handler);
        }

        $this->assertStringContainsString("visit.get('patientId')", $visitHandler);
        $this->assertStringContainsString("this.copyLink(attributes, visit, 'clinic')", $visitHandler);
        $this->assertStringContainsString("this.copyLink(attributes, visit, 'doctor')", $visitHandler);
        $this->assertStringContainsString("this.copyLink(attributes, visit, 'cabinet')", $visitHandler);
        $this->assertStringContainsString("visit.get('recommendations')", $visitHandler);
        $this->assertStringContainsString("visit.get('complaints')", $visitHandler);

        $this->assertStringContainsString("invoice.get('patientId')", $invoiceHandler);
        $this->assertStringContainsString("Espo.Ajax.getRequest('Visit/' + visitId)", $invoiceHandler);
        $this->assertStringContainsString("this.copyLinkFromData(attributes, visit, 'doctor', true)", $invoiceHandler);
        $this->assertStringContainsString("this.copyLinkFromData(attributes, visit, 'cabinet', true)", $invoiceHandler);
        $this->assertStringContainsString('visit.recommendations', $invoiceHandler);
        $this->assertStringContainsString('visit.complaints', $invoiceHandler);
    }

    public function testPatientCardRemainsAContextualBookingEntryPoint(): void
    {
        $patientClient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $button = $this->findButton($patientClient, 'bookAppointment');

        $this->assertSame('Book Appointment', $button['label']);
        $this->assertSame('espo-dental:handlers/patient/book-appointment', $button['handler']);
        $this->assertSame('actionBookAppointment', $button['actionFunction']);
    }

    public function testBookNextLabelsAreLocalized(): void
    {
        $expected = [
            'en_US' => 'Book Next Appointment',
            'ru_RU' => 'Записать следующий приём',
            'es_ES' => 'Reservar siguiente cita',
        ];

        foreach ($expected as $locale => $label) {
            $visit = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Visit.json");
            $invoice = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Invoice.json");
            $appointment = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Appointment.json");

            $this->assertSame($label, $visit['labels']['Book Next Appointment']);
            $this->assertSame($label, $invoice['labels']['Book Next Appointment']);
            $this->assertArrayHasKey('Patient is required', $appointment['messages']);
        }
    }

    /**
     * @param array<string, mixed> $clientDefs
     * @return array<string, mixed>
     */
    private function findButton(array $clientDefs, string $name): array
    {
        $buttons = $clientDefs['menu']['detail']['buttons'] ?? [];

        foreach ($buttons as $button) {
            if (($button['name'] ?? null) === $name) {
                $this->assertIsArray($button);

                return $button;
            }
        }

        $this->fail("Missing detail button: {$name}");
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: {$path}");

        return $data;
    }
}
