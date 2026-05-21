<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase26PatientHistoryTimelineTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testPatientHistoryEndpointReturnsFutureAppointmentsBeforePastVisits(): void
    {
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Patient.php');
        $servicePath = self::MODULE_ROOT . '/Services/PatientHistoryService.php';
        $service = (string) file_get_contents($servicePath);

        $this->assertFileExists($servicePath);
        $this->assertStringContainsString('getActionHistory', $controller);
        $this->assertStringContainsString('PatientHistoryService', $controller);
        $this->assertStringContainsString("checkScope('Appointment', 'read')", $controller);
        $this->assertStringContainsString("checkScope('Visit', 'read')", $controller);

        $this->assertStringContainsString('getPatientHistory', $service);
        $this->assertStringContainsString("'futureAppointments'", $service);
        $this->assertStringContainsString("'pastVisits'", $service);
        $this->assertStringContainsString("'parentType' => Patient::ENTITY_TYPE", $service);
        $this->assertStringContainsString("'status' => Appointment::BLOCKING_STATUSES", $service);
        $this->assertStringContainsString("'dateStart>=' => \$nowUtc", $service);
        $this->assertStringContainsString("->order('dateStart', 'ASC')", $service);
        $this->assertStringContainsString("'startedAt<=' => \$nowUtc", $service);
        $this->assertStringContainsString("->order('startedAt', 'DESC')", $service);
        $this->assertStringContainsString('resolveTimeZone', $service);
        $this->assertStringContainsString('localStart', $service);
        $this->assertStringContainsString('localStartedAt', $service);
    }

    public function testPatientDetailRendersTimelinePanelAboveClinicalFiles(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $viewPath = self::CLIENT_ROOT . '/src/views/patient/record/detail.js';
        $view = (string) file_get_contents($viewPath);

        $this->assertSame(
            'espo-dental:views/patient/record/detail',
            $clientDefs['recordViews']['detail']
        );
        $this->assertStringContainsString('Patient/action/history', $view);
        $this->assertStringContainsString('patient-history-panel', $view);
        $this->assertStringContainsString('renderFutureAppointments', $view);
        $this->assertStringContainsString('renderPastVisits', $view);
        $this->assertStringContainsString('#Appointment/view/', $view);
        $this->assertStringContainsString('#Visit/view/', $view);
        $this->assertStringContainsString('Future Appointments', $view);
        $this->assertStringContainsString('Past Visits', $view);
        $this->assertStringContainsString('patient-clinical-files-panel', $view);
        $this->assertStringContainsString('[data-name="patient-history-panel"]', $view);
    }

    public function testPatientHistoryLabelsAreLocalized(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $labels = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Patient.json")['labels'];

            foreach (['Patient History', 'Future Appointments', 'Past Visits'] as $label) {
                $this->assertArrayHasKey($label, $labels);
            }
        }
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
