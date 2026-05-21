<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\HealthQuestionnaire;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitPhoto;

class PatientFileService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array{
     *     patientId: string,
     *     photos: list<array<string, mixed>>,
     *     questionnaireFiles: list<array<string, mixed>>
     * }
     */
    public function getPatientFiles(
        string $patientId,
        bool $includePhotos = true,
        bool $includeQuestionnaires = true,
        int $limit = 12
    ): array {
        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $patientId);

        if (!$patient) {
            throw new NotFound("Patient {$patientId} not found");
        }

        $limit = max(1, min(50, $limit));

        return [
            'patientId' => (string) $patient->getId(),
            'photos' => $includePhotos ? $this->getRecentPhotos($patientId, $limit) : [],
            'questionnaireFiles' => $includeQuestionnaires
                ? $this->getQuestionnaireFiles($patientId, $limit)
                : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getRecentPhotos(string $patientId, int $limit): array
    {
        /** @var iterable<VisitPhoto> $photos */
        $photos = $this->entityManager
            ->getRDBRepository(VisitPhoto::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'patientId' => $patientId,
            ])
            ->order('recordedAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($photos as $photo) {
            $visitId = $photo->get('visitId');
            $visitName = $photo->get('visitName');

            $rows[] = [
                'id' => (string) $photo->getId(),
                'name' => (string) $photo->get('name'),
                'stage' => (string) $photo->get('stage'),
                'category' => (string) $photo->get('category'),
                'tooth' => (string) ($photo->get('tooth') ?? ''),
                'recordedAt' => (string) $photo->get('recordedAt'),
                'visitId' => $visitId,
                'visitName' => $visitName ?: $this->resolveVisitName(is_string($visitId) ? $visitId : null),
                'imageId' => $photo->get('imageId'),
                'imageName' => $photo->get('imageName'),
                'orthancStudyUid' => $photo->get('orthancStudyUid'),
                'orthancUrl' => $photo->get('orthancUrl'),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function resolveVisitName(?string $visitId): ?string
    {
        if (!$visitId) {
            return null;
        }

        /** @var Visit|null $visit */
        $visit = $this->entityManager->getEntityById(Visit::ENTITY_TYPE, $visitId);

        return $visit ? (string) $visit->get('name') : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getQuestionnaireFiles(string $patientId, int $limit): array
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
            $rows[] = [
                'id' => (string) $questionnaire->getId(),
                'name' => (string) $questionnaire->get('name'),
                'filledAt' => (string) $questionnaire->get('filledAt'),
                'expiresAt' => (string) $questionnaire->get('expiresAt'),
                'hasAlerts' => (bool) $questionnaire->get('hasAlerts'),
                'isExpired' => (bool) $questionnaire->get('isExpired'),
                'pdfFileId' => $questionnaire->get('pdfFileId'),
                'pdfFileName' => $questionnaire->get('pdfFileName'),
                'signatureAttachmentId' => $questionnaire->get('signatureAttachmentId'),
                'signatureAttachmentName' => $questionnaire->get('signatureAttachmentName'),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }
}
