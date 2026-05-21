<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase22PatientFilesVisibilityTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testPatientDetailHasClinicalFilesPanel(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $viewPath = self::CLIENT_ROOT . '/src/views/patient/record/detail.js';
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Patient.php');
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/PatientFileService.php');

        $this->assertSame(
            'espo-dental:views/patient/record/detail',
            $clientDefs['recordViews']['detail']
        );
        $this->assertFileExists($viewPath);

        $view = (string) file_get_contents($viewPath);
        $this->assertStringContainsString('Patient/action/files', $view);
        $this->assertStringContainsString('patient-clinical-files-panel', $view);
        $this->assertStringContainsString('?entryPoint=download&id=', $view);
        $this->assertStringContainsString('#VisitPhoto/view/', $view);
        $this->assertStringContainsString('#HealthQuestionnaire/view/', $view);
        $this->assertStringContainsString('#Visit/view/', $view);

        $this->assertStringContainsString('getActionFiles', $controller);
        $this->assertStringContainsString('PatientFileService', $controller);
        $this->assertStringContainsString('VisitPhoto', $controller);
        $this->assertStringContainsString('HealthQuestionnaire', $controller);

        $this->assertStringContainsString('getPatientFiles', $service);
        $this->assertStringContainsString("'photos'", $service);
        $this->assertStringContainsString("'questionnaireFiles'", $service);
        $this->assertStringContainsString("'imageId'", $service);
        $this->assertStringContainsString("'visitName'", $service);
        $this->assertStringContainsString("'pdfFileId'", $service);
        $this->assertStringContainsString("'signatureAttachmentId'", $service);
    }

    public function testPatientRelationshipPanelsAreReadOrientedForFiles(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $relationships = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Patient/relationships.json');

        foreach (['photos', 'healthQuestionnaires'] as $relationship) {
            $this->assertArrayHasKey($relationship, $clientDefs['relationshipPanels']);
            $this->assertFalse($clientDefs['relationshipPanels'][$relationship]['create']);
            $this->assertFalse($clientDefs['relationshipPanels'][$relationship]['select']);
            $this->assertTrue($clientDefs['relationshipPanels'][$relationship]['createDisabled']);
            $this->assertTrue($clientDefs['relationshipPanels'][$relationship]['selectDisabled']);

            $panel = $this->findRelationship($relationships, $relationship);
            $this->assertIsArray($panel);
            $this->assertTrue($panel['createDisabled']);
            $this->assertTrue($panel['selectDisabled']);
        }
    }

    public function testStandardRelationshipListsShowFileAndVisitContext(): void
    {
        $photoList = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitPhoto/list.json');
        $photoSmall = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitPhoto/listSmall.json');
        $questionnaireSmall = $this->readJson(
            self::MODULE_ROOT . '/Resources/layouts/HealthQuestionnaire/listSmall.json'
        );

        foreach (['image', 'visit', 'recordedAt', 'stage', 'category'] as $field) {
            $this->assertContains($field, $this->fieldNames($photoList));
            $this->assertContains($field, $this->fieldNames($photoSmall));
        }

        foreach (['filledAt', 'hasAlerts', 'isExpired', 'pdfFile', 'signatureAttachment'] as $field) {
            $this->assertContains($field, $this->fieldNames($questionnaireSmall));
        }
    }

    public function testPatientLocalesContainClinicalFileLabels(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $labels = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Patient.json")['labels'];

            foreach (['Clinical Files', 'Recent Visit Photos', 'Questionnaire Files', 'Alerts', 'Expired'] as $label) {
                $this->assertArrayHasKey($label, $labels);
            }
        }
    }

    /**
     * @param array<int, mixed> $relationships
     * @return array<string, mixed>|null
     */
    private function findRelationship(array $relationships, string $name): ?array
    {
        foreach ($relationships as $relationship) {
            if (is_array($relationship) && ($relationship['name'] ?? null) === $name) {
                return $relationship;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $layout
     * @return list<string>
     */
    private function fieldNames(array $layout): array
    {
        return array_values(array_map(
            static fn (array $item): string => (string) $item['name'],
            $layout
        ));
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
