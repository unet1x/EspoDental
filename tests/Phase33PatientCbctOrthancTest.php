<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase33PatientCbctOrthancTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testPatientCbctOrthancEndpointReturnsImagingSources(): void
    {
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Patient.php');
        $servicePath = self::MODULE_ROOT . '/Services/PatientImagingService.php';
        $service = (string) file_get_contents($servicePath);

        $this->assertFileExists($servicePath);
        $this->assertStringContainsString('getActionCbctOrthanc', $controller);
        $this->assertStringContainsString('PatientImagingService', $controller);
        $this->assertStringContainsString("checkScope('Patient', 'read')", $controller);
        $this->assertStringContainsString("checkScope('VisitPhoto', 'read')", $controller);
        $this->assertStringContainsString("checkScope('OrthoPhoto', 'read')", $controller);
        $this->assertStringContainsString("checkScope('OrthodonticCard', 'read')", $controller);

        $this->assertStringContainsString('getPatientCbctOrthanc', $service);
        $this->assertStringContainsString('VISIT_IMAGING_CATEGORIES', $service);
        $this->assertStringContainsString('VisitPhoto::ENTITY_TYPE', $service);
        $this->assertStringContainsString('OrthoPhoto::ENTITY_TYPE', $service);
        $this->assertStringContainsString('OrthodonticCard::ENTITY_TYPE', $service);
        $this->assertStringContainsString("'visitStudies'", $service);
        $this->assertStringContainsString("'orthodonticStudies'", $service);
        $this->assertStringContainsString("'orthancStudyUid'", $service);
        $this->assertStringContainsString("'orthancUrl'", $service);
        $this->assertStringContainsString("'orthancUid'", $service);
        $this->assertStringContainsString("->order('recordedAt', 'DESC')", $service);
        $this->assertStringContainsString("->order('takenAt', 'DESC')", $service);
    }

    public function testPatientDetailRendersCbctOrthancBeforeClinicalFiles(): void
    {
        $viewPath = self::CLIENT_ROOT . '/src/views/patient/record/detail.js';
        $view = (string) file_get_contents($viewPath);

        $this->assertStringContainsString('Patient/action/cbctOrthanc', $view);
        $this->assertStringContainsString('patient-cbct-orthanc-panel', $view);
        $this->assertStringContainsString('patient-cbct-orthanc-body', $view);
        $this->assertStringContainsString('renderCbctOrthancContent', $view);
        $this->assertStringContainsString('renderVisitImagingStudies', $view);
        $this->assertStringContainsString('renderOrthodonticImagingStudies', $view);
        $this->assertStringContainsString('renderOrthancLinks', $view);
        $this->assertStringContainsString('#VisitPhoto/view/', $view);
        $this->assertStringContainsString('#OrthoPhoto/view/', $view);
        $this->assertStringContainsString('#OrthodonticCard/view/', $view);
        $this->assertStringContainsString('[data-name="patient-tooth-chart-history-panel"]', $view);
        $this->assertStringContainsString('[data-name="patient-cbct-orthanc-panel"]', $view);
        $this->assertStringContainsString('[data-name="patient-clinical-files-panel"]', $view);
    }

    public function testPatientCbctOrthancLabelsAreLocalized(): void
    {
        $labels = [
            'CBCT / Orthanc',
            'Visit Imaging',
            'Orthodontic Imaging',
            'Open Orthanc',
            'Study UID',
        ];

        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $patient = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Patient.json");

            foreach ($labels as $label) {
                $this->assertArrayHasKey($label, $patient['labels']);
            }
        }
    }

    public function testDocsRecordCbctOrthancSlice(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');
        $acceptance = (string) file_get_contents(self::ROOT . '/docs/acceptance-checklist.md');

        $this->assertStringContainsString('CBCT / Orthanc', $currentState);
        $this->assertStringContainsString('Patient/action/cbctOrthanc', $currentState);
        $this->assertStringContainsString('visit and orthodontic imaging studies', $releaseNotes);
        $this->assertStringContainsString('CBCT / Orthanc', $acceptance);
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
