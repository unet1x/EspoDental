<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase23QuestionnairePatientUxTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testQuestionnaireAnswersRenderFromSchema(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/HealthQuestionnaire.json');
        $detail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/HealthQuestionnaire/detail.json');
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/HealthQuestionnaire.php');
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/HealthQuestionnaireService.php');
        $fieldViewPath = self::CLIENT_ROOT . '/src/views/health-questionnaire/fields/items-table.js';

        $this->assertSame(
            'espo-dental:views/health-questionnaire/fields/items-table',
            $def['fields']['items']['view']
        );
        $this->assertStringContainsString('items', json_encode($detail, JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('getActionAnswers', $controller);
        $this->assertStringContainsString('getAnswerTable', $service);
        $this->assertStringContainsString('QuestionnaireSchemaProvider', $service);
        $this->assertStringContainsString("'groups'", $service);
        $this->assertStringContainsString("'extraAnswers'", $service);

        $this->assertFileExists($fieldViewPath);
        $fieldView = (string) file_get_contents($fieldViewPath);
        $this->assertStringContainsString('HealthQuestionnaire/action/answers', $fieldView);
        $this->assertStringContainsString('Requires attention', $fieldView);
        $this->assertStringContainsString('table class="table table-condensed table-bordered"', $fieldView);
    }

    public function testPatientDetailShowsQuestionnaireAlertBanner(): void
    {
        $patientClient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $viewPath = self::CLIENT_ROOT . '/src/views/patient/record/detail.js';

        $this->assertSame(
            'espo-dental:views/patient/record/detail',
            $patientClient['recordViews']['detail']
        );

        $view = (string) file_get_contents($viewPath);
        $this->assertStringContainsString('renderQuestionnaireAlert', $view);
        $this->assertStringContainsString('questionnaireExpired', $view);
        $this->assertStringContainsString('questionnaireHasAlerts', $view);
        $this->assertStringContainsString('patient-questionnaire-alert', $view);
        $this->assertStringContainsString('Questionnaire expired warning', $view);
        $this->assertStringContainsString('Questionnaire has alerts warning', $view);
    }

    public function testLocalesContainQuestionnaireAnswerAndAlertLabels(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $questionnaire = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/HealthQuestionnaire.json");
            $patient = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Patient.json");

            foreach (['Answers', 'Question', 'Answer', 'Other Answers', 'Requires attention'] as $label) {
                $this->assertArrayHasKey($label, $questionnaire['labels']);
            }

            foreach (['Questionnaire expired warning', 'Questionnaire has alerts warning'] as $message) {
                $this->assertArrayHasKey($message, $patient['messages']);
            }
        }
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
