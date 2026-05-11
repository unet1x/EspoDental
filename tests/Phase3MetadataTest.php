<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Phase3MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const ENTITIES = ['HealthQuestionnaire', 'QuestionnaireToken'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function entityProvider(): iterable
    {
        foreach (self::ENTITIES as $entity) {
            yield $entity => [$entity];
        }
    }

    #[DataProvider('entityProvider')]
    public function testPhase3EntitiesHaveRequiredFiles(string $entity): void
    {
        foreach ([
            "Resources/metadata/scopes/{$entity}.json",
            "Resources/metadata/entityDefs/{$entity}.json",
            "Resources/metadata/clientDefs/{$entity}.json",
            "Resources/layouts/{$entity}/detail.json",
            "Resources/layouts/{$entity}/list.json",
        ] as $relative) {
            $path = self::MODULE_ROOT . '/' . $relative;
            $this->assertFileExists($path, "Missing: {$relative}");
            $this->readJson($path);
        }
    }

    public function testEntryPointRegistered(): void
    {
        $entryPoints = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/entryPoints.json');
        $this->assertArrayHasKey('healthQuestionnaire', $entryPoints);
        $this->assertFalse($entryPoints['healthQuestionnaire']['authRequired']);
        $this->assertStringContainsString('EntryPoints\\HealthQuestionnaire', $entryPoints['healthQuestionnaire']['className']);
    }

    public function testRoutesHaveSubmitNoAuth(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $submit = array_filter($routes, fn ($r) => str_contains($r['route'], 'submit'));
        $this->assertNotEmpty($submit);
        $route = array_values($submit)[0];
        $this->assertSame('post', $route['method']);
        $this->assertTrue($route['noAuth']);
    }

    public function testScheduledJobRegistered(): void
    {
        $jobs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/scheduledJobs.json');
        $this->assertArrayHasKey('EspoDentalCheckExpiredQuestionnaires', $jobs);
        $this->assertStringContainsString('Jobs\\CheckExpiredQuestionnaires', $jobs['EspoDentalCheckExpiredQuestionnaires']['jobClassName']);
    }

    public function testPatientHasNewQuestionnaireFields(): void
    {
        $patient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        foreach (['lastQuestionnaireAt', 'questionnaireExpired', 'questionnaireHasAlerts'] as $field) {
            $this->assertArrayHasKey($field, $patient['fields'], "Patient missing field: {$field}");
        }
        $this->assertArrayHasKey('healthQuestionnaires', $patient['links']);
        $this->assertSame('HealthQuestionnaire', $patient['links']['healthQuestionnaires']['entity']);
    }

    public function testEntityClassesExist(): void
    {
        foreach (['HealthQuestionnaire', 'QuestionnaireToken'] as $entity) {
            $this->assertFileExists(self::MODULE_ROOT . "/Entities/{$entity}.php");
        }
    }

    public function testServiceFilesExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Services/PreliminaryPatientConversion.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Services/HealthQuestionnaireService.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/QuestionnaireSchemaProvider.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/QuestionnairePdfBuilder.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/HealthQuestionnaireRenderer.php');
        $this->assertFileExists(self::MODULE_ROOT . '/EntryPoints/HealthQuestionnaire.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Controllers/PreliminaryPatient.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Controllers/PublicHealthQuestionnaire.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Jobs/CheckExpiredQuestionnaires.php');
    }

    public function testHtmlTemplatePresent(): void
    {
        $path = self::MODULE_ROOT . '/Resources/templates/public/healthQuestionnaire.html.tpl';
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        $this->assertStringContainsString('{{bootstrapJson}}', $contents);
        $this->assertStringContainsString('signature', strtolower($contents));
    }

    public function testClientFilesPresent(): void
    {
        $clientRoot = __DIR__ . '/../src/files/client/custom/modules/espo-dental';
        foreach ([
            'src/handlers/preliminary-patient/convert-to-patient.js',
            'src/views/preliminary-patient/modals/convert.js',
            'src/views/health-questionnaire/qr-modal.js',
            'src/lib/qr-svg.js',
        ] as $relative) {
            $this->assertFileExists($clientRoot . '/' . $relative);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);
        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
