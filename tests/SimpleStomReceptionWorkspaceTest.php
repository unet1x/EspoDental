<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomReceptionWorkspaceTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testVisitServiceExposesReceptionWorkspaceAutosaveAndTemplates(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/VisitService.php');

        foreach (
            [
                'getReceptionWorkspace',
                'autosaveReceptionNotes',
                'createNoteTemplate',
                'allowedSections',
                'treatmentPlan',
                'buildReceptionChecklist',
                'VisitNoteTemplate',
                'Visit::STATUS_FINISHED',
                "['treatmentPlan']",
                'getPersonalTemplates',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testVisitControllerAndRoutesExposeReceptionWorkspace(): void
    {
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Visit.php');
        $routes = $this->readFile(self::MODULE_ROOT . '/Resources/routes.json');

        foreach (
            [
                'getActionReceptionWorkspace',
                'postActionAutosaveReception',
                'postActionNoteTemplate',
                '/EspoDental/Visit/receptionWorkspace',
                '/EspoDental/Visit/autosaveReception',
                '/EspoDental/Visit/noteTemplate',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $controller . $routes);
        }
    }

    public function testVisitDetailRendersSimpleStomReceptionWorkspace(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/visit/record/detail.js');

        foreach (
            [
                'espo-dental:lib/simple-stom-ui',
                'renderReceptionWorkspace',
                'Рабочее место приема',
                'Чеклист завершения',
                'EspoDental/Visit/receptionWorkspace',
                'EspoDental/Visit/autosaveReception',
                'EspoDental/Visit/noteTemplate',
                'saveNoteTemplate',
                'План лечения',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }
    }

    public function testVisitNoteTemplateEntityAndVisitMetadataAreRegistered(): void
    {
        foreach (
            [
                'Entities/VisitNoteTemplate.php',
                'Resources/metadata/entityDefs/VisitNoteTemplate.json',
                'Resources/metadata/scopes/VisitNoteTemplate.json',
                'Resources/metadata/clientDefs/VisitNoteTemplate.json',
                'Resources/layouts/VisitNoteTemplate/detail.json',
                'Resources/layouts/VisitNoteTemplate/list.json',
                'Resources/i18n/en_US/VisitNoteTemplate.json',
                'Resources/i18n/ru_RU/VisitNoteTemplate.json',
                'Resources/i18n/es_ES/VisitNoteTemplate.json',
            ] as $relative
        ) {
            $this->assertFileExists(self::MODULE_ROOT . '/' . $relative);
        }

        $visitDefs = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Visit.json');
        $visitLayout = $this->readFile(self::MODULE_ROOT . '/Resources/layouts/Visit/detail.json');
        $roles = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');

        $this->assertStringContainsString('treatmentPlan', $visitDefs);
        $this->assertStringContainsString('treatmentPlan', $visitLayout);
        $this->assertStringContainsString('VisitNoteTemplate', $roles);
    }

    public function testDocsTrackReceptionWorkspaceStage(): void
    {
        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-reception-workspace.md');

        $this->assertStringContainsString('docs/simple-stom-reception-workspace.md', $readme);
        $this->assertStringContainsString('| 8. Doctor reception workspace | Completed |', $plan);
        $this->assertStringContainsString('GET /EspoDental/Visit/receptionWorkspace', $doc);
        $this->assertStringContainsString('Finished visits are treated as locked', $doc);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
