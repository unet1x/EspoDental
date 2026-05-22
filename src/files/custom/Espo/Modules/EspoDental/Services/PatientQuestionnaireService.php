<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\HealthQuestionnaire;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Tools\QuestionnaireSchemaProvider;

class PatientQuestionnaireService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly QuestionnaireSchemaProvider $schemaProvider
    ) {
    }

    /**
     * @return array{
     *     patientId: string,
     *     latestQuestionnaire: array<string, mixed>|null,
     *     recentQuestionnaires: list<array<string, mixed>>
     * }
     */
    public function getPatientQuestionnaireSummary(
        string $patientId,
        bool $includeQuestionnaires = true,
        int $limit = 5
    ): array {
        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $patientId);

        if (!$patient) {
            throw new NotFound("Patient {$patientId} not found");
        }

        $limit = max(1, min(20, $limit));

        if (!$includeQuestionnaires) {
            return [
                'patientId' => (string) $patient->getId(),
                'latestQuestionnaire' => null,
                'recentQuestionnaires' => [],
            ];
        }

        $questionnaires = $this->getRecentQuestionnaires($patientId, $limit);
        $latest = $questionnaires[0] ?? null;

        $recent = [];
        foreach ($questionnaires as $questionnaire) {
            $recent[] = $this->buildQuestionnaireRow($questionnaire, false);
        }

        return [
            'patientId' => (string) $patient->getId(),
            'latestQuestionnaire' => $latest ? $this->buildQuestionnaireRow($latest, true) : null,
            'recentQuestionnaires' => $recent,
        ];
    }

    /**
     * @return list<HealthQuestionnaire>
     */
    private function getRecentQuestionnaires(string $patientId, int $limit): array
    {
        /** @var iterable<HealthQuestionnaire> $questionnaires */
        $questionnaires = $this->entityManager
            ->getRDBRepository(HealthQuestionnaire::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'patientId' => $patientId,
            ])
            ->order('filledAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($questionnaires as $questionnaire) {
            $rows[] = $questionnaire;

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuestionnaireRow(HealthQuestionnaire $questionnaire, bool $includeAnswers): array
    {
        $language = $questionnaire->getLanguage();
        $schema = $this->schemaProvider->get($language);

        $row = [
            'id' => (string) $questionnaire->getId(),
            'name' => (string) ($questionnaire->get('name') ?? ''),
            'language' => $language,
            'schemaVersion' => (int) ($schema['version'] ?? 1),
            'filledAt' => (string) ($questionnaire->get('filledAt') ?? ''),
            'expiresAt' => (string) ($questionnaire->get('expiresAt') ?? ''),
            'hasAlerts' => $questionnaire->hasAlerts(),
            'isExpired' => $questionnaire->isExpired(),
            'alertItems' => $questionnaire->getAlertItems(),
            'pdfFileId' => $questionnaire->get('pdfFileId'),
            'pdfFileName' => $questionnaire->get('pdfFileName'),
            'signatureAttachmentId' => $questionnaire->get('signatureAttachmentId'),
            'signatureAttachmentName' => $questionnaire->get('signatureAttachmentName'),
        ];

        if ($includeAnswers) {
            $answers = $this->buildAnswerGroups($questionnaire, $schema);
            $row['answerGroups'] = $answers['groups'];
            $row['extraAnswers'] = $answers['extraAnswers'];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array{
     *     groups: list<array<string, mixed>>,
     *     extraAnswers: list<array<string, mixed>>
     * }
     */
    private function buildAnswerGroups(HealthQuestionnaire $questionnaire, array $schema): array
    {
        $items = $questionnaire->getItems();
        $usedIds = [];
        $groups = [];

        foreach ($schema['groups'] ?? [] as $group) {
            $answers = [];

            foreach ($group['items'] ?? [] as $item) {
                $id = (string) ($item['id'] ?? '');
                if ($id === '' || !array_key_exists($id, $items)) {
                    continue;
                }

                $isAlert = (bool) ($item['alert'] ?? false);
                $answers[] = [
                    'id' => $id,
                    'label' => (string) ($item['label'] ?? $id),
                    'type' => (string) ($item['type'] ?? 'bool'),
                    'value' => $items[$id],
                    'alert' => $isAlert,
                    'activeAlert' => $isAlert && $this->isPositiveAnswer($items[$id]),
                ];
                $usedIds[$id] = true;
            }

            if ($answers === []) {
                continue;
            }

            $groups[] = [
                'id' => (string) ($group['id'] ?? ''),
                'label' => (string) ($group['label'] ?? ''),
                'answers' => $answers,
            ];
        }

        $extraAnswers = [];
        foreach ($items as $id => $value) {
            if (isset($usedIds[$id])) {
                continue;
            }

            $extraAnswers[] = [
                'id' => (string) $id,
                'label' => (string) $id,
                'type' => is_bool($value) ? 'bool' : 'text',
                'value' => $value,
                'alert' => false,
                'activeAlert' => false,
            ];
        }

        return [
            'groups' => $groups,
            'extraAnswers' => $extraAnswers,
        ];
    }

    private function isPositiveAnswer(mixed $value): bool
    {
        return $value === true ||
            $value === 1 ||
            $value === '1' ||
            $value === 'true' ||
            $value === 'yes';
    }
}
