<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\HealthQuestionnaire;
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
     * @return array{
     *     patientId: string,
     *     questionnaireId: string,
     *     appointmentsUpdated: int
     * }
     */
    public function convert(string $preliminaryId, ?string $questionnaireId = null): array
    {
        /** @var PreliminaryPatient|null $prelim */
        $prelim = $this->entityManager->getEntityById(PreliminaryPatient::ENTITY_TYPE, $preliminaryId);

        if (!$prelim) {
            throw new NotFound("Preliminary patient {$preliminaryId} not found");
        }

        if ($prelim->get('convertedToPatientId')) {
            throw new Conflict('Preliminary patient is already converted');
        }

        $questionnaire = $questionnaireId
            ? $this->getQuestionnaireForPreliminary($preliminaryId, $questionnaireId)
            : $this->findLatestQuestionnaireForPreliminary($preliminaryId);

        if (!$questionnaire) {
            throw new Conflict('Health questionnaire must be completed before conversion');
        }

        if ($questionnaire->getPatientId()) {
            throw new Conflict('Health questionnaire is already linked to a patient');
        }

        $patient = $this->entityManager->getNewEntity(Patient::ENTITY_TYPE);
        $this->copyFields($prelim, $patient);
        $patient->set('status', 'active');
        $patient->set('balance', 0.0);
        $patient->set('convertedFromPreliminaryId', $prelim->getId());
        $patient->set('lastQuestionnaireAt', $questionnaire->get('filledAt'));
        $patient->set('questionnaireExpired', false);
        $patient->set('questionnaireHasAlerts', $questionnaire->hasAlerts());
        $this->entityManager->saveEntity($patient, ['espodentalAllowPatientCreate' => true]);

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $questionnaire->set('patientId', $patient->getId());
        $this->entityManager->saveEntity($questionnaire);

        $this->linkTokensToPatient($prelim, $patient, $questionnaire);

        $prelim->set('convertedToPatientId', $patient->getId());
        $prelim->set('convertedAt', $now);
        $prelim->set('questionnaireCompleted', true);
        $prelim->set('lastQuestionnaireAt', $questionnaire->get('filledAt'));
        $prelim->set('status', PreliminaryPatient::STATUS_PROCESSED);
        $this->entityManager->saveEntity($prelim);

        $appointmentsUpdated = $this->reparentAppointments($prelim, $patient);

        return [
            'patientId' => (string) $patient->getId(),
            'questionnaireId' => (string) $questionnaire->getId(),
            'appointmentsUpdated' => $appointmentsUpdated,
        ];
    }

    /**
     * @return array{tokenId: string, tokenUrl: string, expiresAt: string}
     */
    public function issueQuestionnaireToken(string $preliminaryId, ?string $language = null): array
    {
        /** @var PreliminaryPatient|null $prelim */
        $prelim = $this->entityManager->getEntityById(PreliminaryPatient::ENTITY_TYPE, $preliminaryId);

        if (!$prelim) {
            throw new NotFound("Preliminary patient {$preliminaryId} not found");
        }

        if ($prelim->get('convertedToPatientId')) {
            throw new Conflict('Preliminary patient is already converted');
        }

        $token = $this->issueToken($prelim, $language);

        return [
            'tokenId' => (string) $token->getId(),
            'tokenUrl' => $this->buildPublicUrl((string) $token->getToken()),
            'expiresAt' => (string) $token->get('expiresAt'),
        ];
    }

    public function issueToken(PreliminaryPatient $prelim, ?string $language): QuestionnaireToken
    {
        $tokenString = bin2hex(random_bytes(24));
        $expiresAt = (new DateTimeImmutable())
            ->modify('+' . self::TOKEN_TTL_HOURS . ' hours')
            ->format('Y-m-d H:i:s');

        /** @var QuestionnaireToken $token */
        $token = $this->entityManager->getNewEntity(QuestionnaireToken::ENTITY_TYPE);
        $token->set('token', $tokenString);
        $token->set('name', substr($tokenString, 0, 16));
        $token->set('preliminaryPatientId', $prelim->getId());
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

    private function getQuestionnaireForPreliminary(string $preliminaryId, string $questionnaireId): HealthQuestionnaire
    {
        /** @var HealthQuestionnaire|null $questionnaire */
        $questionnaire = $this->entityManager->getEntityById(HealthQuestionnaire::ENTITY_TYPE, $questionnaireId);

        if (!$questionnaire) {
            throw new NotFound("Health questionnaire {$questionnaireId} not found");
        }

        if ($questionnaire->getPreliminaryPatientId() !== $preliminaryId) {
            throw new Conflict('Health questionnaire does not belong to the preliminary patient');
        }

        return $questionnaire;
    }

    private function findLatestQuestionnaireForPreliminary(string $preliminaryId): ?HealthQuestionnaire
    {
        /** @var HealthQuestionnaire|null $questionnaire */
        $questionnaire = $this->entityManager
            ->getRDBRepository(HealthQuestionnaire::ENTITY_TYPE)
            ->where(['preliminaryPatientId' => $preliminaryId])
            ->order('filledAt', 'DESC')
            ->findOne();

        return $questionnaire;
    }

    private function linkTokensToPatient(
        PreliminaryPatient $prelim,
        Patient $patient,
        HealthQuestionnaire $questionnaire
    ): void {
        /** @var iterable<QuestionnaireToken> $tokens */
        $tokens = $this->entityManager
            ->getRDBRepository(QuestionnaireToken::ENTITY_TYPE)
            ->where(['preliminaryPatientId' => $prelim->getId()])
            ->find();

        foreach ($tokens as $token) {
            $token->set('patientId', $patient->getId());
            if ($token->get('questionnaireId') === $questionnaire->getId()) {
                $token->set('questionnaireId', $questionnaire->getId());
            }
            $this->entityManager->saveEntity($token);
        }
    }

    private function reparentAppointments(PreliminaryPatient $prelim, Patient $patient): int
    {
        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where([
                'parentType' => PreliminaryPatient::ENTITY_TYPE,
                'parentId' => $prelim->getId(),
            ])
            ->find();

        $count = 0;
        foreach ($appointments as $appointment) {
            $appointment->set('parentType', Patient::ENTITY_TYPE);
            $appointment->set('parentId', $patient->getId());
            $this->entityManager->saveEntity($appointment, ['skipConflictCheck' => true]);
            $count++;
        }

        return $count;
    }

    private function resolveDefaultLanguage(): string
    {
        $lang = (string) $this->config->get('defaultLanguage', 'ru_RU');
        return in_array($lang, ['ru_RU', 'en_US', 'es_ES'], true) ? $lang : 'ru_RU';
    }
}
