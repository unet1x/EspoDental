<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase17FrontDeskFlowTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testPreliminaryPatientCarriesQuestionnaireState(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/PreliminaryPatient.json');

        foreach (['questionnaireCompleted', 'lastQuestionnaireAt'] as $field) {
            $this->assertArrayHasKey($field, $def['fields']);
            $this->assertTrue($def['fields'][$field]['readOnly']);
        }

        $this->assertSame('HealthQuestionnaire', $def['links']['healthQuestionnaires']['entity']);
        $this->assertSame('QuestionnaireToken', $def['links']['questionnaireTokens']['entity']);
    }

    public function testQuestionnaireCanBelongToPreliminaryPatientBeforeConversion(): void
    {
        $questionnaire = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/HealthQuestionnaire.json');
        $token = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/QuestionnaireToken.json');

        $this->assertArrayHasKey('preliminaryPatient', $questionnaire['fields']);
        $this->assertFalse($questionnaire['fields']['patient']['required'] ?? false);
        $this->assertSame('PreliminaryPatient', $questionnaire['links']['preliminaryPatient']['entity']);

        $this->assertArrayHasKey('preliminaryPatient', $token['fields']);
        $this->assertFalse($token['fields']['patient']['required'] ?? false);
        $this->assertSame('PreliminaryPatient', $token['links']['preliminaryPatient']['entity']);
    }

    public function testPreliminaryPatientHasIssueQuestionnaireAction(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/PreliminaryPatient.json');
        $buttons = $clientDefs['menu']['detail']['buttons'] ?? [];
        $names = array_column($buttons, 'name');

        $this->assertContains('issueQuestionnaire', $names);
        $this->assertContains('convertToPatient', $names);

        $this->assertFileExists(
            self::CLIENT_ROOT . '/src/handlers/preliminary-patient/issue-questionnaire.js'
        );
        $this->assertFileExists(
            self::CLIENT_ROOT . '/src/views/preliminary-patient/modals/issue-questionnaire.js'
        );
    }

    public function testPatientCreationIsDisabledAndGuarded(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $this->assertTrue($clientDefs['createDisabled']);

        $hook = self::MODULE_ROOT . '/Hooks/Patient/RequireConversionSource.php';
        $this->assertFileExists($hook);
        $code = (string) file_get_contents($hook);
        $this->assertStringContainsString('espodentalAllowPatientCreate', $code);
        $this->assertStringContainsString('Patient must be created from a preliminary patient conversion', $code);
    }

    public function testConversionRequiresQuestionnaireAndReparentsAppointments(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/PreliminaryPatientConversion.php');

        $this->assertStringContainsString('Health questionnaire must be completed before conversion', $code);
        $this->assertStringContainsString('espodentalAllowPatientCreate', $code);
        $this->assertStringContainsString('reparentAppointments', $code);
        $this->assertStringContainsString('questionnaireCompleted', $code);
    }

    public function testQuestionnaireSubmitTriggersConversionForPreliminaryToken(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/HealthQuestionnaireService.php');

        $this->assertStringContainsString('PreliminaryPatientConversion', $code);
        $this->assertStringContainsString('preliminaryPatientId', $code);
        $this->assertStringContainsString('markPreliminaryCompleted', $code);
        $this->assertStringContainsString('converted', (string) file_get_contents(
            self::MODULE_ROOT . '/Controllers/PublicHealthQuestionnaire.php'
        ));
    }

    public function testAppointmentFlowRecordsBookerAndPatientConflict(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/Appointment/FrontDeskFlow.php');

        $conflictCode = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/Appointment/CheckConflicts.php');
        $this->assertStringContainsString('parentType', $conflictCode);
        $this->assertStringContainsString('Patient is already booked at this time', $conflictCode);
    }

    public function testStartVisitRequiresCompletedQuestionnaire(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/AppointmentService.php');

        $this->assertStringContainsString('lastQuestionnaireAt', $code);
        $this->assertStringContainsString('questionnaireExpired', $code);
        $this->assertStringContainsString('Health questionnaire must be completed before the visit can start', $code);
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
