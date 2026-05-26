<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomQuestionnairePortalTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testQuestionnaireSchemaAndRendererExposeSimpleStomContract(): void
    {
        $schema = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/dental/questionnaireSchema.json');
        $provider = $this->readFile(self::MODULE_ROOT . '/Tools/QuestionnaireSchemaProvider.php');
        $renderer = $this->readFile(self::MODULE_ROOT . '/Tools/HealthQuestionnaireRenderer.php');
        $template = $this->readFile(self::MODULE_ROOT . '/Resources/templates/public/healthQuestionnaire.html.tpl');

        foreach (
            [
                '"version": 2',
                '"childDental"',
                '"representative"',
                '"confirmation"',
                '"acknowledge.questionnaire_update_period"',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $schema);
        }

        foreach (['getAll', 'getTemplateType', 'pdfLanguageMode', 'isChild'] as $needle) {
            $this->assertStringContainsString($needle, $provider);
        }

        foreach (['stringsByLanguage', 'patientIsChild', 'schemas'] as $needle) {
            $this->assertStringContainsString($needle, $renderer);
        }

        foreach (['language-switch', 'progress-bar', 'setLanguage', 'renderGroups'] as $needle) {
            $this->assertStringContainsString($needle, $template);
        }
    }

    public function testQuestionnaireServiceAndPdfPersistTemplateAndEsRuRules(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/HealthQuestionnaireService.php');
        $pdf = $this->readFile(self::MODULE_ROOT . '/Tools/QuestionnairePdfBuilder.php');
        $defs = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/entityDefs/HealthQuestionnaire.json');

        foreach (
            [
                'templateType',
                'templateVersion',
                'formLanguage',
                'pdfLanguageMode',
                "'es_ru'",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
            $this->assertStringContainsString(trim($needle, "'"), $defs);
        }

        foreach (
            [
                "get('es_ES'",
                "get('ru_RU'",
                'bilingualLabel',
                'Signature Date',
                'Cuestionario de salud / Анкета здоровья',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $pdf);
        }
    }

    public function testPatientPortalPublicApiUsesHashOnlySessionAndSafeAppointmentView(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/PatientPortalService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/PublicPatientPortal.php');
        $routes = $this->readFile(self::MODULE_ROOT . '/Resources/routes.json');
        $entryPoints = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/app/entryPoints.json');
        $template = $this->readFile(self::MODULE_ROOT . '/Resources/templates/public/patientPortal.html.tpl');

        foreach (
            [
                'requestCode',
                'verifyCode',
                'getAppointments',
                'createRescheduleRequest',
                'cancelRescheduleRequest',
                'hashSecret',
                'tokenHash',
                'otpHash',
                'X-Patient-Portal-Token',
                'plannedServices',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service . $controller . $template);
        }

        foreach (
            [
                '/EspoDental/Public/PatientPortal/requestCode',
                '/EspoDental/Public/PatientPortal/verifyCode',
                '/EspoDental/Public/PatientPortal/appointments',
                '/EspoDental/Public/PatientPortal/rescheduleRequests',
                '/EspoDental/Public/PatientPortal/logout',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $routes);
        }

        $this->assertStringContainsString('EntryPoints\\\\PatientPortal', $entryPoints);
        $this->assertStringNotContainsString('occupiedSlots', $service . $routes . $template);
    }

    public function testPortalEntitiesMetadataRolesAndDocsAreRegistered(): void
    {
        foreach (
            [
                'AppointmentRescheduleRequest',
                'PatientPortalSession',
                'PatientPortalEvent',
            ] as $entity
        ) {
            $this->assertFileExists(self::MODULE_ROOT . '/Entities/' . $entity . '.php');
            $this->assertFileExists(self::MODULE_ROOT . '/Resources/metadata/entityDefs/' . $entity . '.json');
            $this->assertFileExists(self::MODULE_ROOT . '/Resources/metadata/scopes/' . $entity . '.json');
            $this->assertFileExists(self::MODULE_ROOT . '/Resources/metadata/clientDefs/' . $entity . '.json');
            $this->assertFileExists(self::MODULE_ROOT . '/Resources/layouts/' . $entity . '/detail.json');
            $this->assertFileExists(self::MODULE_ROOT . '/Resources/layouts/' . $entity . '/list.json');
        }

        $patientDefs = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $appointmentDefs = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json');
        $roles = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');

        foreach (['portalSessions', 'portalEvents', 'rescheduleRequests'] as $needle) {
            $this->assertStringContainsString($needle, $patientDefs . $appointmentDefs);
        }

        foreach (
            [
                'AppointmentRescheduleRequest',
                'PatientPortalSession',
                'PatientPortalEvent',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $roles);
        }

        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-questionnaire-portal.md');

        $this->assertStringContainsString('docs/simple-stom-questionnaire-portal.md', $readme);
        $this->assertStringContainsString('| 7. Questionnaire and portal | Completed |', $plan);
        $this->assertStringContainsString('GET /EspoDental/Public/PatientPortal/appointments', $doc);
        $this->assertStringContainsString('does not expose occupied slots', $doc);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
