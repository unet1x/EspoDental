<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\AppointmentRescheduleRequest;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\PatientPortalEvent;
use Espo\Modules\EspoDental\Entities\PatientPortalSession;

class PatientPortalService
{
    private const OTP_TTL_MINUTES = 10;
    private const ACCESS_TTL_HOURS = 24;
    private const MAX_OTP_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    /**
     * @param array{ip?: string, userAgent?: string} $audit
     * @return array{ok: true, expiresAt: string, debugOtp?: string|null}
     */
    public function requestCode(string $email, array $audit = []): array
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            throw new BadRequest('email is required');
        }

        $patient = $this->findPatientByEmail($email);
        if (!$patient) {
            $this->recordEvent(
                PatientPortalEvent::ACTION_REQUEST_CODE,
                PatientPortalEvent::STATUS_FAILURE,
                null,
                null,
                null,
                null,
                $audit,
                ['contact' => $email],
                'Patient not found'
            );

            return [
                'ok' => true,
                'expiresAt' => (new DateTimeImmutable())
                    ->modify('+' . self::OTP_TTL_MINUTES . ' minutes')
                    ->format('Y-m-d H:i:s'),
                'debugOtp' => null,
            ];
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = (new DateTimeImmutable())
            ->modify('+' . self::OTP_TTL_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s');

        /** @var PatientPortalSession $session */
        $session = $this->entityManager->getNewEntity(PatientPortalSession::ENTITY_TYPE);
        $session->set('name', 'Portal OTP ' . substr($email, 0, 32));
        $session->set('patientId', $patient->getId());
        $session->set('contactMethod', PatientPortalSession::CONTACT_EMAIL);
        $session->set('contactValueSnapshot', $email);
        $session->set('otpHash', $this->hashSecret($otp));
        $session->set('otpAttemptCount', 0);
        $session->set('expiresAt', $expiresAt);
        $session->set('ipAddress', substr((string) ($audit['ip'] ?? ''), 0, 64));
        $session->set('userAgent', substr((string) ($audit['userAgent'] ?? ''), 0, 512));

        $this->entityManager->saveEntity($session);

        $this->recordEvent(
            PatientPortalEvent::ACTION_REQUEST_CODE,
            PatientPortalEvent::STATUS_SUCCESS,
            (string) $patient->getId(),
            (string) $session->getId(),
            null,
            null,
            $audit,
            ['contact' => $email]
        );

        return [
            'ok' => true,
            'expiresAt' => $expiresAt,
            'debugOtp' => $this->isDeveloperMode() ? $otp : null,
        ];
    }

    /**
     * @param array{ip?: string, userAgent?: string} $audit
     * @return array{token: string, expiresAt: string, patient: array<string, mixed>}
     */
    public function verifyCode(string $email, string $code, array $audit = []): array
    {
        $email = $this->normalizeEmail($email);
        $code = trim($code);

        if ($email === '' || !preg_match('/^\d{6}$/', $code)) {
            throw new BadRequest('email and 6-digit code are required');
        }

        $session = $this->findLatestOtpSession($email);
        if (!$session || $session->isExpired()) {
            $this->recordEvent(
                PatientPortalEvent::ACTION_VERIFY_CODE,
                PatientPortalEvent::STATUS_FAILURE,
                null,
                $session ? (string) $session->getId() : null,
                null,
                null,
                $audit,
                ['contact' => $email],
                'OTP session expired'
            );
            throw new Forbidden('Invalid or expired code');
        }

        if ((int) $session->get('otpAttemptCount') >= self::MAX_OTP_ATTEMPTS) {
            $session->set('otpLockedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
            $this->entityManager->saveEntity($session);
            $this->recordEvent(
                PatientPortalEvent::ACTION_VERIFY_CODE,
                PatientPortalEvent::STATUS_BLOCKED,
                $session->getPatientId(),
                (string) $session->getId(),
                null,
                null,
                $audit,
                ['contact' => $email],
                'Too many OTP attempts'
            );
            throw new Conflict('Too many code attempts');
        }

        if (!hash_equals($session->getOtpHash(), $this->hashSecret($code))) {
            $session->set('otpAttemptCount', ((int) $session->get('otpAttemptCount')) + 1);
            $this->entityManager->saveEntity($session);
            $this->recordEvent(
                PatientPortalEvent::ACTION_VERIFY_CODE,
                PatientPortalEvent::STATUS_FAILURE,
                $session->getPatientId(),
                (string) $session->getId(),
                null,
                null,
                $audit,
                ['contact' => $email],
                'Invalid OTP'
            );
            throw new Forbidden('Invalid or expired code');
        }

        $accessToken = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable())
            ->modify('+' . self::ACCESS_TTL_HOURS . ' hours')
            ->format('Y-m-d H:i:s');

        $session->set('tokenHash', $this->hashSecret($accessToken));
        $session->set('otpHash', '');
        $session->set('usedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $session->set('lastSeenAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $session->set('expiresAt', $expiresAt);
        $this->entityManager->saveEntity($session);

        $this->recordEvent(
            PatientPortalEvent::ACTION_VERIFY_CODE,
            PatientPortalEvent::STATUS_SUCCESS,
            $session->getPatientId(),
            (string) $session->getId(),
            null,
            null,
            $audit,
            ['contact' => $email]
        );

        return [
            'token' => $accessToken,
            'expiresAt' => $expiresAt,
            'patient' => $this->buildPatientSummary($this->getSessionPatient($session)),
        ];
    }

    /**
     * @param array{ip?: string, userAgent?: string} $audit
     * @return array{patient: array<string, mixed>, appointments: list<array<string, mixed>>}
     */
    public function getAppointments(string $accessToken, array $audit = []): array
    {
        $session = $this->authenticate($accessToken);
        $patient = $this->getSessionPatient($session);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where([
                'parentType' => Patient::ENTITY_TYPE,
                'parentId' => $patient->getId(),
                'dateStart>=' => $now,
            ])
            ->order('dateStart', 'ASC')
            ->find();

        $rows = [];
        foreach ($appointments as $appointment) {
            $status = (string) ($appointment->get('status') ?? '');
            if (in_array($status, ['cancelled', 'finished', 'no_show'], true)) {
                continue;
            }

            $rows[] = $this->buildAppointmentView($appointment, (string) $patient->getId());
        }

        $this->touchSession($session);
        $this->recordEvent(
            PatientPortalEvent::ACTION_LIST_APPOINTMENTS,
            PatientPortalEvent::STATUS_SUCCESS,
            (string) $patient->getId(),
            (string) $session->getId(),
            null,
            null,
            $audit,
            ['count' => count($rows)]
        );

        return [
            'patient' => $this->buildPatientSummary($patient),
            'appointments' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array{ip?: string, userAgent?: string} $audit
     * @return array<string, mixed>
     */
    public function createRescheduleRequest(string $accessToken, array $data, array $audit = []): array
    {
        $session = $this->authenticate($accessToken);
        $patientId = (string) $session->getPatientId();
        $appointment = $this->getPortalAppointment((string) ($data['appointmentId'] ?? ''), $patientId);
        $active = $this->findActiveRescheduleRequest((string) $appointment->getId(), $patientId);

        if ($active) {
            throw new Conflict('Active reschedule request already exists');
        }

        $requestedStart = $this->parseRequestedStart((string) ($data['requestedStartAt'] ?? ''));
        $durationSeconds = (int) ($appointment->get('duration') ?: 1800);
        $requestedEnd = $requestedStart->modify('+' . max(900, $durationSeconds) . ' seconds');

        /** @var AppointmentRescheduleRequest $request */
        $request = $this->entityManager->getNewEntity(AppointmentRescheduleRequest::ENTITY_TYPE);
        $request->set('name', 'Reschedule ' . $requestedStart->format('Y-m-d H:i'));
        $request->set('appointmentId', $appointment->getId());
        $request->set('patientId', $patientId);
        $request->set('requestedBy', 'patient');
        $request->set('source', 'patient_portal');
        $request->set('requestedStartAt', $requestedStart->format('Y-m-d H:i:s'));
        $request->set('requestedEndAt', $requestedEnd->format('Y-m-d H:i:s'));
        $request->set('requestedDoctorId', $data['requestedDoctorId'] ?? $appointment->get('doctorId'));
        $request->set('requestedCabinetId', $data['requestedCabinetId'] ?? $appointment->get('cabinetId'));
        $request->set('requestedClinicId', $data['requestedClinicId'] ?? $appointment->get('clinicId'));
        $request->set('previousAppointmentStatus', (string) ($appointment->get('status') ?? ''));
        $request->set('status', AppointmentRescheduleRequest::STATUS_PENDING);
        $request->set('patientComment', trim((string) ($data['patientComment'] ?? '')));

        $this->entityManager->saveEntity($request);

        $this->touchSession($session);
        $this->recordEvent(
            PatientPortalEvent::ACTION_CREATE_RESCHEDULE_REQUEST,
            PatientPortalEvent::STATUS_SUCCESS,
            $patientId,
            (string) $session->getId(),
            (string) $appointment->getId(),
            (string) $request->getId(),
            $audit,
            [
                'requestedStartAt' => $request->get('requestedStartAt'),
                'requestedDoctorId' => $request->get('requestedDoctorId'),
                'requestedCabinetId' => $request->get('requestedCabinetId'),
            ]
        );

        return $this->buildRescheduleRequestSummary($request);
    }

    /**
     * @param array{ip?: string, userAgent?: string} $audit
     * @return array<string, mixed>
     */
    public function cancelRescheduleRequest(string $accessToken, string $requestId, array $audit = []): array
    {
        $session = $this->authenticate($accessToken);
        $patientId = (string) $session->getPatientId();

        /** @var AppointmentRescheduleRequest|null $request */
        $request = $this->entityManager->getEntityById(AppointmentRescheduleRequest::ENTITY_TYPE, $requestId);
        if (!$request || $request->getPatientId() !== $patientId || !$request->isActive()) {
            throw new NotFound('Active reschedule request not found');
        }

        $request->set('status', AppointmentRescheduleRequest::STATUS_CANCELLED_BY_PATIENT);
        $this->entityManager->saveEntity($request);

        $this->touchSession($session);
        $this->recordEvent(
            PatientPortalEvent::ACTION_CANCEL_RESCHEDULE_REQUEST,
            PatientPortalEvent::STATUS_SUCCESS,
            $patientId,
            (string) $session->getId(),
            $request->getAppointmentId(),
            (string) $request->getId(),
            $audit
        );

        return $this->buildRescheduleRequestSummary($request);
    }

    /**
     * @param array{ip?: string, userAgent?: string} $audit
     * @return array{ok: true}
     */
    public function logout(string $accessToken, array $audit = []): array
    {
        $session = $this->authenticate($accessToken);
        $session->set('revokedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->entityManager->saveEntity($session);

        $this->recordEvent(
            PatientPortalEvent::ACTION_LOGOUT,
            PatientPortalEvent::STATUS_SUCCESS,
            $session->getPatientId(),
            (string) $session->getId(),
            null,
            null,
            $audit
        );

        return ['ok' => true];
    }

    private function authenticate(string $accessToken): PatientPortalSession
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            throw new Forbidden('Portal token is required');
        }

        /** @var PatientPortalSession|null $session */
        $session = $this->entityManager
            ->getRDBRepository(PatientPortalSession::ENTITY_TYPE)
            ->where(['tokenHash' => $this->hashSecret($accessToken)])
            ->findOne();

        if (!$session || $session->isRevoked() || $session->isExpired()) {
            throw new Forbidden('Invalid or expired portal session');
        }

        return $session;
    }

    private function getPortalAppointment(string $appointmentId, string $patientId): Appointment
    {
        if ($appointmentId === '') {
            throw new BadRequest('appointmentId is required');
        }

        /** @var Appointment|null $appointment */
        $appointment = $this->entityManager->getEntityById(Appointment::ENTITY_TYPE, $appointmentId);
        if (
            !$appointment ||
            $appointment->get('parentType') !== Patient::ENTITY_TYPE ||
            $appointment->get('parentId') !== $patientId
        ) {
            throw new NotFound('Appointment not found');
        }

        if ((string) $appointment->get('dateStart') < (new DateTimeImmutable())->format('Y-m-d H:i:s')) {
            throw new Conflict('Only future appointments can be rescheduled');
        }

        if (in_array((string) $appointment->get('status'), ['cancelled', 'finished', 'no_show'], true)) {
            throw new Conflict('Appointment status cannot be rescheduled');
        }

        return $appointment;
    }

    private function getSessionPatient(PatientPortalSession $session): Patient
    {
        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, (string) $session->getPatientId());
        if (!$patient) {
            throw new NotFound('Patient not found');
        }

        return $patient;
    }

    private function findPatientByEmail(string $email): ?Patient
    {
        /** @var Patient|null $patient */
        $patient = $this->entityManager
            ->getRDBRepository(Patient::ENTITY_TYPE)
            ->where(['emailAddress' => $email])
            ->findOne();

        return $patient;
    }

    private function findLatestOtpSession(string $email): ?PatientPortalSession
    {
        /** @var PatientPortalSession|null $session */
        $session = $this->entityManager
            ->getRDBRepository(PatientPortalSession::ENTITY_TYPE)
            ->where([
                'contactMethod' => PatientPortalSession::CONTACT_EMAIL,
                'contactValueSnapshot' => $email,
                'tokenHash' => null,
                'revokedAt' => null,
            ])
            ->order('createdAt', 'DESC')
            ->findOne();

        return $session;
    }

    private function findActiveRescheduleRequest(
        string $appointmentId,
        string $patientId
    ): ?AppointmentRescheduleRequest {
        /** @var AppointmentRescheduleRequest|null $request */
        $request = $this->entityManager
            ->getRDBRepository(AppointmentRescheduleRequest::ENTITY_TYPE)
            ->where([
                'appointmentId' => $appointmentId,
                'patientId' => $patientId,
                'status' => AppointmentRescheduleRequest::ACTIVE_STATUSES,
            ])
            ->order('createdAt', 'DESC')
            ->findOne();

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAppointmentView(Appointment $appointment, string $patientId): array
    {
        $activeRequest = $this->findActiveRescheduleRequest((string) $appointment->getId(), $patientId);

        return [
            'appointmentId' => (string) $appointment->getId(),
            'startAt' => (string) $appointment->get('dateStart'),
            'endAt' => (string) $appointment->get('dateEnd'),
            'status' => (string) $appointment->get('status'),
            'clinicName' => (string) ($appointment->get('clinicName') ?? ''),
            'clinicAddress' => '',
            'doctorDisplayName' => (string) ($appointment->get('doctorName') ?? ''),
            'cabinetName' => (string) ($appointment->get('cabinetName') ?? ''),
            'plannedServices' => [],
            'canRequestReschedule' => $activeRequest === null,
            'activeRescheduleRequestStatus' => $activeRequest ? $activeRequest->getStatus() : null,
            'activeRescheduleRequestId' => $activeRequest ? (string) $activeRequest->getId() : null,
            'activeRescheduleRequestedStartAt' => $activeRequest
                ? (string) $activeRequest->get('requestedStartAt')
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRescheduleRequestSummary(AppointmentRescheduleRequest $request): array
    {
        return [
            'id' => (string) $request->getId(),
            'appointmentId' => (string) $request->getAppointmentId(),
            'status' => $request->getStatus(),
            'requestedStartAt' => (string) $request->get('requestedStartAt'),
            'requestedEndAt' => (string) $request->get('requestedEndAt'),
            'requestedDoctorId' => (string) $request->get('requestedDoctorId'),
            'requestedCabinetId' => (string) $request->get('requestedCabinetId'),
            'requestedClinicId' => (string) $request->get('requestedClinicId'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPatientSummary(Patient $patient): array
    {
        return [
            'id' => (string) $patient->getId(),
            'name' => trim(
                (string) $patient->get('lastName') . ' ' .
                (string) $patient->get('firstName') . ' ' .
                (string) $patient->get('middleName')
            ),
            'emailAddress' => (string) ($patient->get('emailAddress') ?? ''),
        ];
    }

    /**
     * @param array{ip?: string, userAgent?: string} $audit
     * @param array<string, mixed> $metadata
     */
    private function recordEvent(
        string $action,
        string $status,
        ?string $patientId,
        ?string $sessionId,
        ?string $appointmentId,
        ?string $rescheduleRequestId,
        array $audit = [],
        array $metadata = [],
        ?string $errorDetail = null
    ): void {
        /** @var PatientPortalEvent $event */
        $event = $this->entityManager->getNewEntity(PatientPortalEvent::ENTITY_TYPE);
        $event->set('name', $action . ' ' . $status);
        $event->set('patientId', $patientId);
        $event->set('sessionId', $sessionId);
        $event->set('appointmentId', $appointmentId);
        $event->set('rescheduleRequestId', $rescheduleRequestId);
        $event->set('action', $action);
        $event->set('status', $status);
        $event->set('contactMethod', PatientPortalSession::CONTACT_EMAIL);
        $event->set('contactValueSnapshot', (string) ($metadata['contact'] ?? ''));
        $event->set('ipAddress', substr((string) ($audit['ip'] ?? ''), 0, 64));
        $event->set('userAgent', substr((string) ($audit['userAgent'] ?? ''), 0, 512));
        $event->set('metadata', (object) $metadata);
        $event->set('errorDetail', $errorDetail);

        $this->entityManager->saveEntity($event);
    }

    private function touchSession(PatientPortalSession $session): void
    {
        $session->set('lastSeenAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->entityManager->saveEntity($session);
    }

    private function parseRequestedStart(string $value): DateTimeImmutable
    {
        if (trim($value) === '') {
            throw new BadRequest('requestedStartAt is required');
        }

        try {
            $start = new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new BadRequest('Invalid requestedStartAt');
        }

        if ($start <= new DateTimeImmutable()) {
            throw new BadRequest('requestedStartAt must be in the future');
        }

        return $start;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function hashSecret(string $value): string
    {
        return hash_hmac('sha256', $value, $this->getSecret());
    }

    private function getSecret(): string
    {
        foreach (['cryptKey', 'secretKey', 'passwordSalt', 'siteUrl'] as $key) {
            $value = (string) $this->config->get($key, '');
            if ($value !== '') {
                return $value;
            }
        }

        return 'espo-dental-patient-portal';
    }

    private function isDeveloperMode(): bool
    {
        return (bool) $this->config->get('isDeveloperMode', false);
    }
}
