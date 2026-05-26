<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomPatientWorkspaceTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testPatientWorkspaceServiceExposesSplitWorkspaceContract(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/PatientWorkspaceService.php');

        foreach (
            [
                'getWorkspace',
                'getPatientList',
                'getSelectedPatient',
                'quickActions',
                'bookAppointment',
                'uploadFile',
                'basicData',
                'toothChart',
                'clinicalHistory',
                'files',
                'finance',
                'family',
                'calculateAge',
                'getNextAppointment',
                'getRecentAppointments',
                'getRecentVisits',
                'getRecentQuestionnaires',
                'getOpenInvoices',
                'getRecentPayments',
                'buildPatientAlerts',
                'preferredChannel',
                'questionnaireHasAlerts',
                'nextAppointment',
                'recentAppointments',
                'recentQuestionnaires',
                'openInvoices',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testControllerAndRouteExposeWorkspaceEndpoint(): void
    {
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Patient.php');
        $routes = $this->readFile(self::MODULE_ROOT . '/Resources/routes.json');

        $this->assertStringContainsString('getActionWorkspace', $controller);
        $this->assertStringContainsString('PatientWorkspaceService', $controller);
        $this->assertStringContainsString('/EspoDental/Patient/workspace', $routes);
    }

    public function testDashletUsesSimpleStomUiAndKeepsClinicalFinanceSeparated(): void
    {
        $metadata = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/dashlets/PatientWorkspace.json');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/patient-workspace.js');

        $this->assertStringContainsString('espo-dental:views/dashlets/patient-workspace', $metadata);

        foreach (
            [
                'EspoDental/Patient/workspace',
                'espo-dental:lib/simple-stom-ui',
                'renderPatientList',
                'renderPatientDetail',
                'renderTabs',
                'renderPatientHighlights',
                'renderAlertBadges',
                'renderLinkedRows',
                'renderClinicalHistoryTab',
                'renderFinanceTab',
                'formatAppointment',
                'formatChannel',
                'Только клиническая история',
                'Только расчеты и оплаты',
                'Открытые счета',
                'Анкеты',
                'Записаться на прием',
                'Ближайшая запись',
                'Канал связи',
                'bookAppointment',
                'uploadFile',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }
    }

    public function testRoleWorkspacesAndLabelsIncludePatientWorkspace(): void
    {
        $seeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        foreach (
            [
                'PatientWorkspace',
                'ed-patient-workspace',
                'ed-admin-patient-workspace',
                'ed-doctor-patient-workspace',
                'ed-assistant-patient-workspace',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $seeder);
        }

        foreach (['en_US', 'es_ES', 'ru_RU'] as $locale) {
            $global = $this->readFile(self::MODULE_ROOT . '/Resources/i18n/' . $locale . '/Global.json');
            $this->assertStringContainsString('PatientWorkspace', $global);
        }
    }

    public function testDocsTrackPatientWorkspaceStage(): void
    {
        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-patient-workspace.md');

        $this->assertStringContainsString('docs/simple-stom-patient-workspace.md', $readme);
        $this->assertStringContainsString('| 6. Patients workspace | Completed |', $plan);
        $this->assertStringContainsString('GET /EspoDental/Patient/workspace', $doc);
        $this->assertStringContainsString('basicData', $doc);
        $this->assertStringContainsString('clinicalHistory', $doc);
        $this->assertStringContainsString('finance', $doc);
        $this->assertStringContainsString('views/patient/record/detail.js', $doc);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
