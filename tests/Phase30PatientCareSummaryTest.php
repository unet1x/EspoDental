<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase30PatientCareSummaryTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testPatientCareSummaryEndpointIncludesFamilyAndOrthodontics(): void
    {
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Patient.php');
        $servicePath = self::MODULE_ROOT . '/Services/PatientCareSummaryService.php';
        $service = (string) file_get_contents($servicePath);

        $this->assertFileExists($servicePath);
        $this->assertStringContainsString('getActionCareSummary', $controller);
        $this->assertStringContainsString('PatientCareSummaryService', $controller);
        $this->assertStringContainsString("checkScope('Patient', 'read')", $controller);
        $this->assertStringContainsString("checkScope('OrthodonticCard', 'read')", $controller);

        $this->assertStringContainsString('getPatientCareSummary', $service);
        $this->assertStringContainsString("'family'", $service);
        $this->assertStringContainsString("'orthodonticCards'", $service);
        $this->assertStringContainsString("'parentPatient'", $service);
        $this->assertStringContainsString("'manualGuardian'", $service);
        $this->assertStringContainsString("'childPatients'", $service);
        $this->assertStringContainsString('OrthodonticCard::ENTITY_TYPE', $service);
        $this->assertStringContainsString("'isActive' => \$card->isActive()", $service);
    }

    public function testPatientDetailRendersCareSummaryBetweenFinancialsAndFiles(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $viewPath = self::CLIENT_ROOT . '/src/views/patient/record/detail.js';
        $view = (string) file_get_contents($viewPath);

        $this->assertSame(
            'espo-dental:views/patient/record/detail',
            $clientDefs['recordViews']['detail']
        );

        $this->assertStringContainsString('Patient/action/careSummary', $view);
        $this->assertStringContainsString('patient-care-summary-panel', $view);
        $this->assertStringContainsString('patient-care-summary-body', $view);
        $this->assertStringContainsString('renderCareSummaryContent', $view);
        $this->assertStringContainsString('renderFamilyLinks', $view);
        $this->assertStringContainsString('renderOrthodonticCards', $view);
        $this->assertStringContainsString('#Patient/view/', $view);
        $this->assertStringContainsString('#OrthodonticCard/view/', $view);
        $this->assertStringContainsString('[data-name="patient-financials-panel"]', $view);
        $this->assertStringContainsString('[data-name="patient-care-summary-panel"]', $view);
        $this->assertStringContainsString('[data-name="patient-clinical-files-panel"]', $view);
    }

    public function testPatientRelationshipPanelsExposeFamilyAndOrthodontics(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $relationships = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Patient/relationships.json');
        $names = array_map(
            static fn ($item): string => is_array($item) ? (string) $item['name'] : (string) $item,
            $relationships
        );

        $this->assertContains('childPatients', $names);
        $this->assertContains('orthodonticCards', $names);

        $this->assertArrayHasKey('childPatients', $clientDefs['relationshipPanels']);
        $this->assertFalse($clientDefs['relationshipPanels']['childPatients']['create']);
        $this->assertFalse($clientDefs['relationshipPanels']['childPatients']['select']);
        $this->assertTrue($clientDefs['relationshipPanels']['childPatients']['createDisabled']);
        $this->assertTrue($clientDefs['relationshipPanels']['childPatients']['selectDisabled']);
    }

    public function testPatientCareSummaryLabelsAreLocalized(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $patient = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Patient.json");

            foreach (['Care Summary', 'Family Links', 'Linked Parent', 'Manual Guardian', 'Orthodontics'] as $label) {
                $this->assertArrayHasKey($label, $patient['labels']);
            }

            foreach (['childPatients', 'orthodonticCards'] as $link) {
                $this->assertArrayHasKey($link, $patient['links']);
            }
        }
    }

    public function testDocsRecordPatientCareSummarySlice(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('Care Summary', $currentState);
        $this->assertStringContainsString('family links', $currentState);
        $this->assertStringContainsString('orthodontic cards', $currentState);
        $this->assertStringContainsString('Care Summary', $releaseNotes);
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
