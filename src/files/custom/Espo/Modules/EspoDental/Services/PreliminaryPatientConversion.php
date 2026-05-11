<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\PreliminaryPatient;
use Espo\Modules\EspoDental\Entities\QuestionnaireToken;
use Espo\ORM\Entity;

class PreliminaryPatientConversion
{
    private const COPYABLE_FIELDS = [
        'lastName',
        'firstName',
        'middleName',
        'gender',
        'phoneNumber',
        'phoneNumberData',
        'emailAddress',
        'emailAddressData',
        'dateOfBirth',
        'clinicId',
        'assignedUserId',
        'description',
    ];

    private const TOKEN_TTL_HOURS = 24;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{patientId: string, tokenId: string, tokenUrl: string, expiresAt: string}
     */
    public function convert(string $preliminaryId, ?string $language = null, bool $issueToken = true): array
    {
        $prelim = $this->entityManager->getEntityById(PreliminaryPatient::ENTITY_TYPE, $preliminaryId);

        if (!$prelim) {
            throw new NotFound("Preliminary patient {$preliminaryId} not found");
        }

        if ($prelim->get('convertedToPatientId')) {
            throw new Conflict('Preliminary patient is already converted');
        }

        $patient = $this->entityManager->getNewEntity(Patient::ENTITY_TYPE);
        $this->copyFields($prelim, $patient);
        $patient->set('status', 'active');
        $patient->set('questionnaireExpired', true);
        $this->entityManager->saveEntity($patient);

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $prelim->set('convertedToPatientId', $patient->getId());
        $prelim->set('convertedAt', $now);
        $prelim->set('status', PreliminaryPatient::STATUS_PROCESSED);
        $this->entityManager->saveEntity($prelim);

        if (!$issueToken) {
            return [
                'patientId' => (string) $patient->getId(),
                'tokenId' => '',
                'tokenUrl' => '',
                'expiresAt' => '',
            ];
        }

        $token = $this->issueToken($patient, $prelim, $language);

        return [
            'patientId' => (string) $patient->getId(),
            'tokenId' => (string) $token->getId(),
            'tokenUrl' => $this->buildPublicUrl((string) $token->getToken()),
            'expiresAt' => (string) $token->get('expiresAt'),
        ];
    }

    public function issueToken(Patient $patient, ?PreliminaryPatient $prelim, ?string $language): QuestionnaireToken
    {
        $tokenString = bin2hex(random_bytes(24));
        $expiresAt = (new DateTimeImmutable())
            ->modify('+' . self::TOKEN_TTL_HOURS . ' hours')
            ->format('Y-m-d H:i:s');

        /** @var QuestionnaireToken $token */
        $token = $this->entityManager->getNewEntity(QuestionnaireToken::ENTITY_TYPE);
        $token->set('token', $tokenString);
        $token->set('name', substr($tokenString, 0, 16));
        $token->set('patientId', $patient->getId());
        if ($prelim) {
            $token->set('preliminaryPatientId', $prelim->getId());
        }
        $token->set('language', $language ?: $this->resolveDefaultLanguage());
        $token->set('expiresAt', $expiresAt);
        $token->set('isUsed', false);

        $this->entityManager->saveEntity($token);

        return $token;
    }

    public function buildPublicUrl(string $token): string
    {
        $siteUrl = rtrim((string) $this->config->get('siteUrl'), '/');
        return $siteUrl . '/?entryPoint=healthQuestionnaire&token=' . rawurlencode($token);
    }

    private function copyFields(Entity $from, Entity $to): void
    {
        foreach (self::COPYABLE_FIELDS as $field) {
            $value = $from->get($field);
            if ($value !== null && $value !== '' && $value !== []) {
                $to->set($field, $value);
            }
        }
    }

    private function resolveDefaultLanguage(): string
    {
        $lang = (string) $this->config->get('defaultLanguage', 'ru_RU');
        return in_array($lang, ['ru_RU', 'en_US', 'es_ES'], true) ? $lang : 'ru_RU';
    }
}
