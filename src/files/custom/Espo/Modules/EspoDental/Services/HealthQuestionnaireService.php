<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Entities\Attachment;
use Espo\Modules\EspoDental\Entities\HealthQuestionnaire;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\PreliminaryPatient;
use Espo\Modules\EspoDental\Entities\QuestionnaireToken;
use Espo\Modules\EspoDental\Tools\QuestionnairePdfBuilder;
use Espo\Modules\EspoDental\Tools\QuestionnaireSchemaProvider;
use Espo\ORM\Entity;

class HealthQuestionnaireService
{
    private const QUESTIONNAIRE_TTL_DAYS = 365;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly QuestionnaireSchemaProvider $schemaProvider,
        private readonly QuestionnairePdfBuilder $pdfBuilder,
        private readonly FileManager $fileManager,
        private readonly PreliminaryPatientConversion $conversion
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(string $language): array
    {
        return $this->schemaProvider->get($language);
    }

    public function findValidToken(string $tokenString): QuestionnaireToken
    {
        /** @var QuestionnaireToken|null $token */
        $token = $this->entityManager
            ->getRDBRepository(QuestionnaireToken::ENTITY_TYPE)
            ->where(['token' => $tokenString])
            ->findOne();

        if (!$token) {
            throw new NotFound('Token not found');
        }

        if ($token->isUsed()) {
            throw new Conflict('Token already used');
        }

        if ($token->isExpired()) {
            throw new Conflict('Token expired');
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $items
     * @param array{ip?: string, userAgent?: string} $audit
     */
    public function submit(
        string $tokenString,
        array $items,
        ?string $signatureDataUri,
        array $audit = []
    ): HealthQuestionnaire {
        $token = $this->findValidToken($tokenString);

        $patientId = $token->getPatientId();
        $preliminaryPatientId = $token->getPreliminaryPatientId();
        $language = $token->getLanguage();

        $patient = $patientId
            ? $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $patientId)
            : null;

        $prelim = $preliminaryPatientId
            ? $this->entityManager->getEntityById(PreliminaryPatient::ENTITY_TYPE, $preliminaryPatientId)
            : null;

        if (!$patient && !$prelim) {
            throw new NotFound('Patient or preliminary patient not found for this token');
        }

        $alertItems = $this->collectAlerts($items, $language);
        $filledAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $expiresAt = (new DateTimeImmutable())
            ->modify('+' . self::QUESTIONNAIRE_TTL_DAYS . ' days')
            ->format('Y-m-d H:i:s');

        $signatureAttachment = $signatureDataUri
            ? $this->storeSignature($signatureDataUri, (string) ($patientId ?: $preliminaryPatientId))
            : null;

        /** @var HealthQuestionnaire $questionnaire */
        $questionnaire = $this->entityManager->getNewEntity(HealthQuestionnaire::ENTITY_TYPE);
        $questionnaire->set('name', $this->buildName($patient ?: $prelim, $filledAt));
        if ($patientId) {
            $questionnaire->set('patientId', $patientId);
        }
        if ($preliminaryPatientId) {
            $questionnaire->set('preliminaryPatientId', $preliminaryPatientId);
        }
        $questionnaire->set('language', $language);
        $questionnaire->set('items', (object) $items);
        $questionnaire->set('alertItems', $alertItems);
        $questionnaire->set('hasAlerts', count($alertItems) > 0);
        $questionnaire->set('filledAt', $filledAt);
        $questionnaire->set('expiresAt', $expiresAt);
        $questionnaire->set('isExpired', false);
        if ($signatureAttachment) {
            $questionnaire->set('signatureAttachmentId', $signatureAttachment->getId());
        }
        $questionnaire->set('submittedFromIp', substr((string) ($audit['ip'] ?? ''), 0, 64));
        $questionnaire->set('submittedUserAgent', substr((string) ($audit['userAgent'] ?? ''), 0, 512));

        $this->entityManager->saveEntity($questionnaire);

        $pdf = $patient instanceof Patient
            ? $this->pdfBuilder->build($questionnaire, $patient, $signatureAttachment)
            : null;
        if ($pdf) {
            $questionnaire->set('pdfFileId', $pdf->getId());
            $this->entityManager->saveEntity($questionnaire);
        }

        if ($patient instanceof Patient) {
            $this->markPatientUpToDate($patient, $filledAt, count($alertItems) > 0);
        }

        if ($prelim instanceof PreliminaryPatient && !$patient) {
            $this->markPreliminaryCompleted($prelim, $filledAt);
            $conversionResult = $this->conversion->convert(
                (string) $prelim->getId(),
                (string) $questionnaire->getId()
            );
            $questionnaire->set('patientId', $conversionResult['patientId']);
        }

        $token->set('isUsed', true);
        $token->set('usedAt', $filledAt);
        $token->set('questionnaireId', $questionnaire->getId());
        if ($questionnaire->getPatientId()) {
            $token->set('patientId', $questionnaire->getPatientId());
        }
        $this->entityManager->saveEntity($token);

        return $questionnaire;
    }

    public function markPatientUpToDate(Patient $patient, string $filledAt, bool $hasAlerts): void
    {
        $patient->set('lastQuestionnaireAt', $filledAt);
        $patient->set('questionnaireExpired', false);
        $patient->set('questionnaireHasAlerts', $hasAlerts);
        $this->entityManager->saveEntity($patient);
    }

    public function markPreliminaryCompleted(PreliminaryPatient $prelim, string $filledAt): void
    {
        $prelim->set('questionnaireCompleted', true);
        $prelim->set('lastQuestionnaireAt', $filledAt);
        $this->entityManager->saveEntity($prelim);
    }

    /**
     * @param array<string, mixed> $items
     * @return array<int, string>
     */
    private function collectAlerts(array $items, string $language): array
    {
        $alertIds = $this->schemaProvider->getAlertItemIds();
        $alerts = [];
        $schema = $this->schemaProvider->get($language);
        $labels = [];
        foreach ($schema['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $labels[$item['id']] = $item['label'] ?? $item['id'];
            }
        }

        foreach ($alertIds as $id) {
            if (!array_key_exists($id, $items)) {
                continue;
            }
            $value = $items[$id];
            if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                $alerts[] = $labels[$id] ?? $id;
            }
        }
        return $alerts;
    }

    private function storeSignature(string $dataUri, string $patientId): ?Attachment
    {
        if (!preg_match('#^data:(image/(?:png|jpeg));base64,(.+)$#', $dataUri, $m)) {
            throw new Error('Invalid signature format');
        }

        $mime = $m[1];
        $binary = base64_decode($m[2], true);

        if ($binary === false || strlen($binary) < 200) {
            throw new Error('Empty signature');
        }

        if (strlen($binary) > 2_000_000) {
            throw new Error('Signature too large');
        }

        /** @var Attachment $attachment */
        $attachment = $this->entityManager->getNewEntity(Attachment::ENTITY_TYPE);
        $attachment->set('name', 'signature-' . $patientId . '.' . ($mime === 'image/png' ? 'png' : 'jpg'));
        $attachment->set('type', $mime);
        $attachment->set('contents', $binary);
        $attachment->set('role', 'Attachment');
        $attachment->set('relatedType', HealthQuestionnaire::ENTITY_TYPE);

        $this->entityManager->saveEntity($attachment);

        return $attachment;
    }

    private function buildName(?Entity $patient, string $filledAt): string
    {
        $patientName = $patient
            ? trim(((string) $patient->get('lastName')) . ' ' . ((string) $patient->get('firstName')))
            : '';
        $date = substr($filledAt, 0, 10);
        return $patientName ? "{$patientName} — {$date}" : "Questionnaire {$date}";
    }
}
