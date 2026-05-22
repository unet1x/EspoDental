<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase31PatientToothChartHistoryTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testPatientToothChartEndpointReturnsRecentSnapshots(): void
    {
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Patient.php');
        $servicePath = self::MODULE_ROOT . '/Services/PatientToothChartService.php';
        $service = (string) file_get_contents($servicePath);

        $this->assertFileExists($servicePath);
        $this->assertStringContainsString('getActionToothCharts', $controller);
        $this->assertStringContainsString('PatientToothChartService', $controller);
        $this->assertStringContainsString("checkScope('Patient', 'read')", $controller);
        $this->assertStringContainsString("checkScope('ToothChartSnapshot', 'read')", $controller);

        $this->assertStringContainsString('getPatientToothCharts', $service);
        $this->assertStringContainsString('ToothChartSnapshot::ENTITY_TYPE', $service);
        $this->assertStringContainsString("->order('recordedAt', 'DESC')", $service);
        $this->assertStringContainsString("'toothCharts'", $service);
        $this->assertStringContainsString("'dentitionType'", $service);
        $this->assertStringContainsString("'visitId'", $service);
        $this->assertStringContainsString("'doctorName'", $service);
        $this->assertStringContainsString("'teethCount'", $service);
        $this->assertStringContainsString('countAnnotatedTeeth', $service);
    }

    public function testPatientDetailRendersToothChartHistoryBeforeFiles(): void
    {
        $viewPath = self::CLIENT_ROOT . '/src/views/patient/record/detail.js';
        $view = (string) file_get_contents($viewPath);

        $this->assertStringContainsString('Patient/action/toothCharts', $view);
        $this->assertStringContainsString('patient-tooth-chart-history-panel', $view);
        $this->assertStringContainsString('patient-tooth-chart-history-body', $view);
        $this->assertStringContainsString('renderToothChartHistoryContent', $view);
        $this->assertStringContainsString('renderToothCharts', $view);
        $this->assertStringContainsString('#ToothChartSnapshot/view/', $view);
        $this->assertStringContainsString('#Visit/view/', $view);
        $this->assertStringContainsString('Annotated Teeth', $view);
        $this->assertStringContainsString('[data-name="patient-care-summary-panel"]', $view);
        $this->assertStringContainsString('[data-name="patient-tooth-chart-history-panel"]', $view);
        $this->assertStringContainsString('[data-name="patient-clinical-files-panel"]', $view);
    }

    public function testPatientToothChartLabelsAreLocalized(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $patient = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Patient.json");

            foreach (['Tooth Chart History', 'Recent Tooth Charts', 'Annotated Teeth'] as $label) {
                $this->assertArrayHasKey($label, $patient['labels']);
            }
        }
    }

    public function testDocsRecordToothChartHistorySlice(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('Tooth Chart History', $currentState);
        $this->assertStringContainsString('Patient/action/toothCharts', $currentState);
        $this->assertStringContainsString('recent tooth-chart snapshots', $releaseNotes);
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
