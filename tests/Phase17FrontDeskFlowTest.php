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

        $this->assertTrue($def['fields']['phone']['required']);
        $this->assertSame('HealthQuestionnaire', $def['links']['healthQuestionnaires']['entity']);
        $this->assertSame('QuestionnaireToken', $def['links']['questionnaireTokens']['entity']);
    }

    public function testPreliminaryPatientIntakeLayoutIsLean(): void
    {
        $detail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/PreliminaryPatient/detail.json');
        $relationships = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/PreliminaryPatient/relationships.json');
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/PreliminaryPatient.json');

        $encoded = json_encode($detail, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('phone', $encoded);
        $this->assertStringContainsString('clinic', $encoded);
        $this->assertStringNotContainsString('status', $encoded);
        $this->assertStringNotContainsString('assignedUser', $encoded);
        $this->assertStringNotContainsString('teams', $encoded);
        $this->assertStringNotContainsString('convertedToPatient', $encoded);
        $this->assertStringNotContainsString('questionnaireCompleted', $encoded);
        $this->assertNotContains('questionnaireTokens', $relationships);
        $this->assertSame(['createdAt', 'modifiedAt'], $clientDefs['defaultSidePanelFieldLists']['detail']);
        $this->assertSame(['createdAt', 'modifiedAt'], $clientDefs['defaultSidePanelFieldLists']['edit']);
    }

    public function testDefaultClinicSettingAndPreliminaryDefaultsHookExist(): void
    {
        $settings = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Settings.json');
        $appSettings = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/settings.json');
        $adminPanel = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/adminPanel.json');

        $this->assertSame('link', $settings['fields']['espoDentalDefaultClinic']['type']);
        $this->assertSame('Clinic', $settings['fields']['espoDentalDefaultClinic']['entity']);
        $this->assertArrayHasKey('espoDentalDefaultClinicId', $appSettings['params']);
        $this->assertSame(
            'espo-dental:views/admin/settings',
            $adminPanel['espoDental']['itemList'][0]['recordView']
        );
        $this->assertFileExists(self::CLIENT_ROOT . '/src/views/admin/settings.js');

        $hook = self::MODULE_ROOT . '/Hooks/PreliminaryPatient/Defaults.php';
        $this->assertFileExists($hook);
        $code = (string) file_get_contents($hook);
        $this->assertStringContainsString('espoDentalDefaultClinicId', $code);
        $this->assertStringContainsString('STATUS_ENTERED', $code);
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

    public function testPreliminaryPatientConvertActionStartsQuestionnaire(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/PreliminaryPatient.json');
        $buttons = $clientDefs['menu']['detail']['buttons'] ?? [];
        $names = array_column($buttons, 'name');

        $this->assertNotContains('issueQuestionnaire', $names);
        $this->assertContains('convertToPatient', $names);

        $convertButton = $buttons[array_search('convertToPatient', $names, true)];
        $this->assertSame(
            'espo-dental:handlers/preliminary-patient/issue-questionnaire',
            $convertButton['handler']
        );
        $this->assertSame('actionIssueQuestionnaire', $convertButton['actionFunction']);

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

    public function testVisitAndInvoiceDirectCreationAreDisabledAndGuarded(): void
    {
        $visitClientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Visit.json');
        $invoiceClientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Invoice.json');

        $this->assertTrue($visitClientDefs['createDisabled']);
        $this->assertTrue($invoiceClientDefs['createDisabled']);

        $visitHook = self::MODULE_ROOT . '/Hooks/Visit/RequireAppointmentSource.php';
        $invoiceHook = self::MODULE_ROOT . '/Hooks/Invoice/RequireVisitSource.php';
        $this->assertFileExists($visitHook);
        $this->assertFileExists($invoiceHook);

        $visitHookCode = (string) file_get_contents($visitHook);
        $invoiceHookCode = (string) file_get_contents($invoiceHook);
        $appointmentServiceCode = (string) file_get_contents(self::MODULE_ROOT . '/Services/AppointmentService.php');
        $invoiceServiceCode = (string) file_get_contents(self::MODULE_ROOT . '/Services/InvoiceService.php');

        $this->assertStringContainsString('espodentalAllowVisitCreate', $visitHookCode);
        $this->assertStringContainsString('Visit must be started from an appointment', $visitHookCode);
        $this->assertStringContainsString('espodentalAllowVisitCreate', $appointmentServiceCode);

        $this->assertStringContainsString('espodentalAllowInvoiceCreate', $invoiceHookCode);
        $this->assertStringContainsString('Invoice must be created from a visit', $invoiceHookCode);
        $this->assertStringContainsString('espodentalAllowInvoiceCreate', $invoiceServiceCode);
    }

    public function testConversionRequiresQuestionnaireAndReparentsAppointments(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/PreliminaryPatientConversion.php');

        $this->assertStringContainsString('Health questionnaire must be completed before conversion', $code);
        $this->assertStringContainsString('espodentalAllowPatientCreate', $code);
        $this->assertStringContainsString('reparentAppointments', $code);
        $this->assertStringContainsString('questionnaireCompleted', $code);
        $this->assertStringContainsString('espodentalAllowPreliminaryPatientRemove', $code);
        $this->assertStringContainsString('createdById', $code);
    }

    public function testQuestionnaireSubmitTriggersConversionForPreliminaryToken(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/HealthQuestionnaireService.php');
        $defs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/HealthQuestionnaire.json');

        $this->assertStringContainsString('PreliminaryPatientConversion', $code);
        $this->assertStringContainsString('preliminaryPatientId', $code);
        $this->assertStringContainsString('markPreliminaryCompleted', $code);
        $this->assertStringContainsString('validateRequiredAnswers', $code);
        $this->assertStringContainsString('buildPdf($questionnaire, $convertedPatient', $code);
        $this->assertSame('espo-dental:views/fields/json-value', $defs['fields']['alertItems']['view']);
        $this->assertFileExists(self::CLIENT_ROOT . '/src/views/fields/json-value.js');
        $this->assertStringContainsString('converted', (string) file_get_contents(
            self::MODULE_ROOT . '/Controllers/PublicHealthQuestionnaire.php'
        ));
    }

    public function testQuestionnaireFormRequiresEveryBoolAnswerAndPdfUsesTwoColumns(): void
    {
        $template = (string) file_get_contents(
            self::MODULE_ROOT . '/Resources/templates/public/healthQuestionnaire.html.tpl'
        );
        $pdfBuilder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/QuestionnairePdfBuilder.php');

        $this->assertStringContainsString('requiredBoolIds', $template);
        $this->assertStringContainsString('allRequired', $template);
        $this->assertStringContainsString('item.missing', $template);
        $this->assertStringContainsString('table class="answers"', $pdfBuilder);
        $this->assertStringContainsString('buildAnswerColumns', $pdfBuilder);
        $this->assertStringContainsString("'Yes' =>", $pdfBuilder);
        $this->assertStringContainsString("'No' =>", $pdfBuilder);
    }

    public function testPatientQuestionnaireActionIsShownOnlyWhenExpired(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $buttons = $clientDefs['menu']['detail']['buttons'] ?? [];
        $names = array_column($buttons, 'name');

        $this->assertContains('issueQuestionnaire', $names);

        $button = $buttons[array_search('issueQuestionnaire', $names, true)];
        $this->assertSame('isIssueQuestionnaireAvailable', $button['checkVisibilityFunction']);
        $this->assertSame('espo-dental:handlers/patient/issue-questionnaire', $button['handler']);
        $this->assertFileExists(self::CLIENT_ROOT . '/src/handlers/patient/issue-questionnaire.js');
        $this->assertFileExists(self::CLIENT_ROOT . '/src/views/patient/modals/issue-questionnaire.js');
    }

    public function testPatientLayoutHidesTechnicalOwnershipAndTokens(): void
    {
        $detail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Patient/detail.json');
        $relationships = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Patient/relationships.json');
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');

        $encodedDetail = json_encode($detail, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('assignedUser', $encodedDetail);
        $this->assertStringNotContainsString('teams', $encodedDetail);
        $this->assertStringNotContainsString('convertedFromPreliminary', $encodedDetail);
        $this->assertNotContains('questionnaireTokens', $relationships);
        $this->assertSame(['createdAt', 'createdBy'], $clientDefs['defaultSidePanelFieldLists']['detail']);
        $this->assertSame(['createdAt', 'createdBy'], $clientDefs['defaultSidePanelFieldLists']['detailSmall']);
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
