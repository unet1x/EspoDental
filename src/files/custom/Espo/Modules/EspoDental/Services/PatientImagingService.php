<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\OrthoPhoto;
use Espo\Modules\EspoDental\Entities\OrthodonticCard;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitPhoto;

class PatientImagingService
{
    private const VISIT_IMAGING_CATEGORIES = ['xray', 'panoramic', 'ct'];

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array{
     *     patientId: string,
     *     visitStudies: list<array<string, mixed>>,
     *     orthodonticStudies: list<array<string, mixed>>
     * }
     */
    public function getPatientCbctOrthanc(
        string $patientId,
        bool $includeVisitPhotos = true,
        bool $includeOrthoPhotos = true,
        int $limit = 8
    ): array {
        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $patientId);

        if (!$patient) {
            throw new NotFound("Patient {$patientId} not found");
        }

        $limit = max(1, min(30, $limit));

        return [
            'patientId' => (string) $patient->getId(),
            'visitStudies' => $includeVisitPhotos ? $this->getVisitStudies($patientId, $limit) : [],
            'orthodonticStudies' => $includeOrthoPhotos ? $this->getOrthodonticStudies($patientId, $limit) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getVisitStudies(string $patientId, int $limit): array
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
            $category = (string) ($photo->get('category') ?? '');

            if (!$this->isVisitImagingPhoto($photo, $category)) {
                continue;
            }

            $visitId = $photo->get('visitId');

            $rows[] = [
                'id' => (string) $photo->getId(),
                'entityType' => VisitPhoto::ENTITY_TYPE,
                'name' => (string) ($photo->get('name') ?? ''),
                'category' => $category,
                'stage' => (string) ($photo->get('stage') ?? ''),
                'tooth' => (string) ($photo->get('tooth') ?? ''),
                'recordedAt' => (string) ($photo->get('recordedAt') ?? ''),
                'visitId' => $visitId,
                'visitName' => $photo->get('visitName')
                    ?: $this->resolveVisitName(is_string($visitId) ? $visitId : null),
                'imageId' => $photo->get('imageId'),
                'imageName' => $photo->get('imageName'),
                'orthancStudyUid' => $photo->get('orthancStudyUid'),
                'orthancUrl' => $photo->get('orthancUrl'),
                'source' => 'visit',
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getOrthodonticStudies(string $patientId, int $limit): array
    {
        $cards = $this->getOrthodonticCardMap($patientId);

        if ($cards === []) {
            return [];
        }

        /** @var iterable<OrthoPhoto> $photos */
        $photos = $this->entityManager
            ->getRDBRepository(OrthoPhoto::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'cardId' => array_keys($cards),
            ])
            ->order('takenAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($photos as $photo) {
            $type = (string) ($photo->get('type') ?? '');
            $orthancUid = (string) ($photo->get('orthancUid') ?? '');

            if (!$photo->isXray() && $orthancUid === '') {
                continue;
            }

            $cardId = (string) ($photo->get('cardId') ?? '');
            $card = $cards[$cardId] ?? null;

            $rows[] = [
                'id' => (string) $photo->getId(),
                'entityType' => OrthoPhoto::ENTITY_TYPE,
                'name' => (string) ($photo->get('name') ?? ''),
                'type' => $type,
                'phase' => (string) ($photo->get('phase') ?? ''),
                'takenAt' => (string) ($photo->get('takenAt') ?? ''),
                'cardId' => $cardId,
                'cardName' => $card['name'] ?? '',
                'cardNumber' => $card['cardNumber'] ?? '',
                'fileId' => $photo->get('fileId'),
                'fileName' => $photo->get('fileName'),
                'orthancUid' => $orthancUid,
                'source' => 'orthodontic',
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, array{name: string, cardNumber: string}>
     */
    private function getOrthodonticCardMap(string $patientId): array
    {
        /** @var iterable<OrthodonticCard> $cards */
        $cards = $this->entityManager
            ->getRDBRepository(OrthodonticCard::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'patientId' => $patientId,
            ])
            ->order('dateOpen', 'DESC')
            ->find();

        $map = [];
        foreach ($cards as $card) {
            $map[(string) $card->getId()] = [
                'name' => (string) ($card->get('name') ?? ''),
                'cardNumber' => (string) ($card->get('cardNumber') ?? ''),
            ];
        }

        return $map;
    }

    private function isVisitImagingPhoto(VisitPhoto $photo, string $category): bool
    {
        return in_array($category, self::VISIT_IMAGING_CATEGORIES, true) ||
            (string) ($photo->get('orthancStudyUid') ?? '') !== '' ||
            (string) ($photo->get('orthancUrl') ?? '') !== '';
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
}
