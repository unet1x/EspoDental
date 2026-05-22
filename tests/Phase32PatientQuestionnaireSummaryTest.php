<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase32PatientQuestionnaireSummaryTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testPatientQuestionnaireEndpointReturnsLatestSummary(): void
    {
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Patient.php');
        $servicePath = self::MODULE_ROOT . '/Services/PatientQuestionnaireService.php';
        $service = (string) file_get_contents($servicePath);

        $this->assertFileExists($servicePath);
        $this->assertStringContainsString('getActionQuestionnaireSummary', $controller);
        $this->assertStringContainsString('PatientQuestionnaireService', $controller);
        $this->assertStringContainsString("checkScope('Patient', 'read')", $controller);
        $this->assertStringContainsString("checkScope('HealthQuestionnaire', 'read')", $controller);

        $this->assertStringContainsString('getPatientQuestionnaireSummary', $service);
        $this->assertStringContainsString('HealthQuestionnaire::ENTITY_TYPE', $service);
        $this->assertStringContainsString('QuestionnaireSchemaProvider', $service);
        $this->assertStringContainsString("'latestQuestionnaire'", $service);
        $this->assertStringContainsString("'recentQuestionnaires'", $service);
        $this->assertStringContainsString("'schemaVersion'", $service);
        $this->assertStringContainsString("'answerGroups'", $service);
        $this->assertStringContainsString("'extraAnswers'", $service);
        $this->assertStringContainsString("'activeAlert'", $service);
        $this->assertStringContainsString("'pdfFileId'", $service);
        $this->assertStringContainsString("'signatureAttachmentId'", $service);
        $this->assertStringContainsString("->order('filledAt', 'DESC')", $service);
    }

    public function testPatientDetailRendersQuestionnaireSummaryBeforeHistory(): void
    {
        $viewPath = self::CLIENT_ROOT . '/src/views/patient/record/detail.js';
        $view = (string) file_get_contents($viewPath);

        $this->assertStringContainsString('Patient/action/questionnaireSummary', $view);
        $this->assertStringContainsString('patient-questionnaire-summary-panel', $view);
        $this->assertStringContainsString('patient-questionnaire-summary-body', $view);
        $this->assertStringContainsString('renderQuestionnaireSummaryContent', $view);
        $this->assertStringContainsString('renderQuestionnaireOverview', $view);
        $this->assertStringContainsString('renderQuestionnaireAnswerGroups', $view);
        $this->assertStringContainsString('formatQuestionnaireAnswerValue', $view);
        $this->assertStringContainsString('#HealthQuestionnaire/view/', $view);
        $this->assertStringContainsString('Latest Answers', $view);
        $this->assertStringContainsString('Recent Questionnaires', $view);
        $this->assertStringContainsString('[data-name="patient-questionnaire-summary-panel"]', $view);
        $this->assertStringContainsString('[data-name="patient-history-panel"]', $view);
    }

    public function testPatientQuestionnaireLabelsAreLocalized(): void
    {
        $labels = [
            'Questionnaire Summary',
            'Latest Answers',
            'Recent Questionnaires',
            'Questionnaire Version',
            'Files',
        ];

        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $patient = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Patient.json");

            foreach ($labels as $label) {
                $this->assertArrayHasKey($label, $patient['labels']);
            }
        }
    }

    public function testDocsRecordQuestionnaireSummarySlice(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');
        $acceptance = (string) file_get_contents(self::ROOT . '/docs/acceptance-checklist.md');

        $this->assertStringContainsString('Questionnaire Summary', $currentState);
        $this->assertStringContainsString('Patient/action/questionnaireSummary', $currentState);
        $this->assertStringContainsString('latest questionnaire answers grouped by schema', $releaseNotes);
        $this->assertStringContainsString('Questionnaire Summary', $acceptance);
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
