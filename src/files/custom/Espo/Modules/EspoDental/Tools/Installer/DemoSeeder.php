<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Installer;

// phpcs:disable Generic.Files.LineLength.TooLong

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * Optional local-demo data set for the SimpleStom migration acceptance script.
 * This is intentionally separate from WorkspaceSeeder so production bootstrap
 * does not create demo patients, appointments or payments.
 */
class DemoSeeder
{
    /** @var list<array{key: string, userName: string, firstName: string, lastName: string, title: string}> */
    private const USERS = [
        ['key' => 'manager', 'userName' => 'demo.manager', 'firstName' => 'Мария', 'lastName' => 'Менеджер', 'title' => 'SimpleStom demo manager'],
        ['key' => 'administrator', 'userName' => 'demo.admin', 'firstName' => 'Анна', 'lastName' => 'Администратор', 'title' => 'SimpleStom demo administrator'],
        ['key' => 'doctor', 'userName' => 'demo.doctor', 'firstName' => 'Илья', 'lastName' => 'Стоматолог', 'title' => 'SimpleStom demo doctor'],
        ['key' => 'assistant', 'userName' => 'demo.assistant', 'firstName' => 'Ольга', 'lastName' => 'Ассистент', 'title' => 'SimpleStom demo assistant'],
        ['key' => 'stock', 'userName' => 'demo.stock', 'firstName' => 'Павел', 'lastName' => 'Склад', 'title' => 'SimpleStom demo stock manager'],
    ];

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array<string, int>
     */
    public function seed(): array
    {
        $clinic = $this->requireClinic();
        $usersResult = $this->ensureUsers();
        $users = $usersResult['users'];
        $patientsResult = $this->ensurePatients($clinic);
        $patients = $patientsResult['patients'];
        $appointmentsResult = $this->ensureAppointments($clinic, $users, $patients);
        $appointments = $appointmentsResult['appointments'];
        $visitResult = $this->ensureVisitFinanceAndClinicalData($clinic, $users, $patients, $appointments);

        return [
            'users' => $usersResult['created'],
            'doctorShifts' => $this->ensureDoctorShifts($clinic, $users),
            'patients' => $patientsResult['created'],
            'appointments' => $appointmentsResult['created'],
            'waitlistEntries' => $this->ensureWaitlist($clinic, $users, $patients, $appointments),
            'questionnaires' => $this->ensureQuestionnaireAndPortal($clinic, $users, $patients, $appointments),
            'visits' => $visitResult['visits'],
            'clinicalLines' => $visitResult['clinicalLines'],
            'invoices' => $visitResult['invoices'],
            'payments' => $visitResult['payments'],
            'inventoryRecords' => $this->ensureInventoryScenario($clinic, $users, $visitResult['materialLine']),
            'payrollRecords' => $this->ensurePayroll($clinic, $users),
            'integrationRecords' => $this->ensureIntegrationDemo($clinic),
        ];
    }

    private function requireClinic(): Entity
    {
        $clinic = $this->findOneByCode('Clinic', 'MAIN');

        if (!$clinic) {
            throw new \RuntimeException('Run espo-dental-bootstrap before espo-dental-demo-seed.');
        }

        return $clinic;
    }

    /**
     * @return array{created: int, users: array<string, Entity>}
     */
    private function ensureUsers(): array
    {
        $created = 0;
        $users = [];

        foreach (self::USERS as $cfg) {
            $user = $this->entityManager
                ->getRDBRepository(User::ENTITY_TYPE)
                ->where(['userName' => $cfg['userName']])
                ->findOne();

            if (!$user) {
                $user = $this->entityManager->getRDBRepository(User::ENTITY_TYPE)->getNew();
                $user->set('userName', $cfg['userName']);
                $user->set('firstName', $cfg['firstName']);
                $user->set('lastName', $cfg['lastName']);
                $user->set('name', $cfg['firstName'] . ' ' . $cfg['lastName']);
                $user->set('type', User::TYPE_REGULAR);
                $user->set('isActive', true);
                $user->set('title', $cfg['title']);
                $user->set('emailAddress', $cfg['userName'] . '@demo.local');
                $this->entityManager->saveEntity($user);
                $created++;
            }

            $users[$cfg['key']] = $user;
        }

        return ['created' => $created, 'users' => $users];
    }

    private function ensureDoctorShifts(Entity $clinic, array $users): int
    {
        $created = 0;
        $cabinet = $this->findOneByCode('Cabinet', 'CAB-1') ?? $this->first('Cabinet');

        foreach (['today', 'tomorrow'] as $offset) {
            $day = (new DateTimeImmutable($offset))->format('Y-m-d');
            $existing = $this->entityManager
                ->getRDBRepository('DoctorShift')
                ->where([
                    'doctorId' => $users['doctor']->getId(),
                    'dateStart' => $day . ' 08:00:00',
                    'deleted' => false,
                ])
                ->findOne();

            if ($existing) {
                continue;
            }

            $shift = $this->entityManager->getRDBRepository('DoctorShift')->getNew();
            $this->setValues($shift, [
                'doctorId' => $users['doctor']->getId(),
                'assistantId' => $users['assistant']->getId(),
                'clinicId' => $clinic->getId(),
                'cabinetId' => $cabinet?->getId(),
                'dateStart' => $day . ' 08:00:00',
                'dateEnd' => $day . ' 18:00:00',
                'type' => 'regular',
                'status' => 'active',
                'description' => 'DEMO SimpleStom doctor shift',
            ]);
            $this->entityManager->saveEntity($shift);
            $created++;
        }

        return $created;
    }

    /**
     * @return array{created: int, patients: array<string, Entity>}
     */
    private function ensurePatients(Entity $clinic): array
    {
        $created = 0;
        $adult = $this->findOne('Patient', ['cardNumber' => 'DEMO-SS-001'])
            ?? $this->createPatient($clinic, [
                'lastName' => 'Смирнов',
                'firstName' => 'Алексей',
                'middleName' => 'Петрович',
                'gender' => 'male',
                'phoneNumber' => '+79990001001',
                'emailAddress' => 'alexey.smirnov@demo.local',
                'dateOfBirth' => '1984-04-12',
                'cardNumber' => 'DEMO-SS-001',
                'vip' => true,
                'restrictions' => true,
                'preferredChannel' => 'whatsapp',
                'whatsapp' => '+79990001001',
                'description' => 'DEMO SimpleStom adult patient with alerts, finance and visit history.',
            ], $created);

        $child = $this->findOne('Patient', ['cardNumber' => 'DEMO-SS-002'])
            ?? $this->createPatient($clinic, [
                'lastName' => 'Смирнова',
                'firstName' => 'Ева',
                'middleName' => 'Алексеевна',
                'gender' => 'female',
                'phoneNumber' => '+79990001002',
                'emailAddress' => 'eva.smirnova@demo.local',
                'dateOfBirth' => '2016-09-20',
                'cardNumber' => 'DEMO-SS-002',
                'isChild' => true,
                'parentPatientId' => $adult->getId(),
                'parentRelation' => 'father',
                'description' => 'DEMO SimpleStom child patient with linked parent.',
            ], $created);

        $preliminary = $this->findOne('PreliminaryPatient', ['emailAddress' => 'lead.demo@demo.local'])
            ?? $this->createPreliminaryPatient($clinic, $created);

        return [
            'created' => $created,
            'patients' => [
                'adult' => $adult,
                'child' => $child,
                'preliminary' => $preliminary,
            ],
        ];
    }

    private function createPatient(Entity $clinic, array $values, int &$created): Entity
    {
        $patient = $this->entityManager->getRDBRepository('Patient')->getNew();
        $values['clinicId'] = $clinic->getId();
        $values['status'] = 'active';
        $values['balance'] = 0.0;
        $this->setValues($patient, $values);
        if (isset($values['phoneNumber'])) {
            $patient->set('phone', $values['phoneNumber']);
        }
        $this->entityManager->saveEntity($patient, ['espodentalAllowPatientCreate' => true]);
        $created++;

        return $patient;
    }

    private function createPreliminaryPatient(Entity $clinic, int &$created): Entity
    {
        $preliminary = $this->entityManager->getRDBRepository('PreliminaryPatient')->getNew();
        $this->setValues($preliminary, [
            'lastName' => 'Лидова',
            'firstName' => 'Наталья',
            'gender' => 'female',
            'phoneNumber' => '+79990001003',
            'phone' => '+79990001003',
            'emailAddress' => 'lead.demo@demo.local',
            'dateOfBirth' => '1992-02-03',
            'status' => 'entered',
            'source' => 'online',
            'clinicId' => $clinic->getId(),
            'description' => 'DEMO SimpleStom preliminary patient for slot booking.',
        ]);
        $this->entityManager->saveEntity($preliminary);
        $created++;

        return $preliminary;
    }

    /**
     * @return array{created: int, appointments: array<string, Entity>}
     */
    private function ensureAppointments(Entity $clinic, array $users, array $patients): array
    {
        $created = 0;
        $cabinet1 = $this->findOneByCode('Cabinet', 'CAB-1') ?? $this->first('Cabinet');
        $cabinet2 = $this->findOneByCode('Cabinet', 'CAB-2') ?? $cabinet1;

        $finished = $this->ensureAppointment('finished-visit', 'Patient', $clinic, $users, $patients['adult'], $cabinet1, [
            'dateStart' => (new DateTimeImmutable('yesterday 10:00:00'))->format('Y-m-d H:i:s'),
            'dateEnd' => (new DateTimeImmutable('yesterday 11:00:00'))->format('Y-m-d H:i:s'),
            'duration' => 3600,
            'status' => 'finished',
            'complaints' => 'Боль при накусывании на 36 зуб.',
            'description' => 'DEMO SimpleStom finished appointment for visit, invoice and payroll.',
        ], $created);

        $future = $this->ensureAppointment('future-reschedule', 'Patient', $clinic, $users, $patients['adult'], $cabinet1, [
            'dateStart' => (new DateTimeImmutable('tomorrow 11:00:00'))->format('Y-m-d H:i:s'),
            'dateEnd' => (new DateTimeImmutable('tomorrow 12:00:00'))->format('Y-m-d H:i:s'),
            'duration' => 3600,
            'status' => 'planned',
            'complaints' => 'Контроль после лечения.',
            'description' => 'DEMO SimpleStom future appointment for portal reschedule request.',
        ], $created);

        $preliminaryAppointment = $this->ensureAppointment('slot-booking-lead', 'PreliminaryPatient', $clinic, $users, $patients['preliminary'], $cabinet2, [
            'dateStart' => (new DateTimeImmutable('today 14:00:00'))->format('Y-m-d H:i:s'),
            'dateEnd' => (new DateTimeImmutable('today 14:30:00'))->format('Y-m-d H:i:s'),
            'duration' => 1800,
            'status' => 'planned',
            'complaints' => 'Первичная консультация из свободного слота.',
            'description' => 'DEMO SimpleStom slot booking wizard appointment.',
        ], $created);

        $cancelled = $this->ensureAppointment('cancelled-panel', 'Patient', $clinic, $users, $patients['child'], $cabinet2, [
            'dateStart' => (new DateTimeImmutable('today 16:00:00'))->format('Y-m-d H:i:s'),
            'dateEnd' => (new DateTimeImmutable('today 16:30:00'))->format('Y-m-d H:i:s'),
            'duration' => 1800,
            'status' => 'cancelled',
            'complaints' => 'Детский осмотр, отмена для правой панели календаря.',
            'description' => 'DEMO SimpleStom cancelled appointment for feedback panel.',
        ], $created);

        return [
            'created' => $created,
            'appointments' => [
                'finished' => $finished,
                'future' => $future,
                'preliminary' => $preliminaryAppointment,
                'cancelled' => $cancelled,
            ],
        ];
    }

    private function ensureAppointment(
        string $key,
        string $parentType,
        Entity $clinic,
        array $users,
        Entity $parent,
        ?Entity $cabinet,
        array $values,
        int &$created
    ): Entity {
        $marker = 'DEMO SimpleStom appointment: ' . $key;
        $appointment = $this->findOne('Appointment', ['description' => $marker]);

        if ($appointment) {
            return $appointment;
        }

        $appointment = $this->entityManager->getRDBRepository('Appointment')->getNew();
        $this->setValues($appointment, [
            'parentType' => $parentType,
            'parentId' => $parent->getId(),
            'doctorId' => $users['doctor']->getId(),
            'assistantId' => $users['assistant']->getId(),
            'cabinetId' => $cabinet?->getId(),
            'clinicId' => $clinic->getId(),
            'bookedById' => $users['administrator']->getId(),
            'dateStart' => $values['dateStart'],
            'dateEnd' => $values['dateEnd'],
            'duration' => $values['duration'],
            'status' => $values['status'],
            'complaints' => $values['complaints'],
            'description' => $marker,
        ]);
        $this->entityManager->saveEntity($appointment, [
            'skipConflictCheck' => true,
            'espodentalAllowAppointmentSystemStatus' => true,
        ]);
        $created++;

        return $appointment;
    }

    private function ensureWaitlist(Entity $clinic, array $users, array $patients, array $appointments): int
    {
        $created = 0;
        $waitlist = $this->findOne('AppointmentWaitlistEntry', ['name' => 'DEMO SimpleStom waitlist urgent']);

        if (!$waitlist) {
            $entry = $this->entityManager->getRDBRepository('AppointmentWaitlistEntry')->getNew();
            $this->setValues($entry, [
                'name' => 'DEMO SimpleStom waitlist urgent',
                'parentType' => 'PreliminaryPatient',
                'parentId' => $patients['preliminary']->getId(),
                'clinicId' => $clinic->getId(),
                'requestedDoctorId' => $users['doctor']->getId(),
                'preferredCabinetId' => $this->findOneByCode('Cabinet', 'CAB-2')?->getId(),
                'appointmentId' => $appointments['preliminary']->getId(),
                'requestedDate' => (new DateTimeImmutable('tomorrow'))->format('Y-m-d'),
                'earliestDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
                'latestDate' => (new DateTimeImmutable('+10 days'))->format('Y-m-d'),
                'status' => 'waiting',
                'priority' => 'urgent',
                'reason' => 'Пациент просит ближайший освободившийся слот.',
                'notes' => 'DEMO SimpleStom right calendar panel.',
            ]);
            $this->entityManager->saveEntity($entry);
            $created++;
        }

        $proposal = $this->findOne('AssistantActionProposal', ['name' => 'DEMO SimpleStom reschedule proposal']);
        if (!$proposal) {
            $proposal = $this->entityManager->getRDBRepository('AssistantActionProposal')->getNew();
            $this->setValues($proposal, [
                'name' => 'DEMO SimpleStom reschedule proposal',
                'source' => 'manual',
                'actionType' => 'propose_appointment',
                'riskLevel' => 'medium',
                'status' => 'pending_review',
                'requiresApproval' => true,
                'patientId' => $patients['adult']->getId(),
                'appointmentId' => $appointments['future']->getId(),
                'targetType' => 'AppointmentRescheduleRequest',
                'targetId' => $appointments['future']->getId(),
                'summary' => 'Пациент запросил перенос визита через портал.',
                'payload' => (object) ['requestedStartAt' => (new DateTimeImmutable('+2 days 12:00:00'))->format('Y-m-d H:i:s')],
            ]);
            $this->entityManager->saveEntity($proposal);
            $created++;
        }

        return $created;
    }

    private function ensureQuestionnaireAndPortal(Entity $clinic, array $users, array $patients, array $appointments): int
    {
        $created = 0;
        $questionnaire = $this->findOne('HealthQuestionnaire', ['notes' => 'DEMO SimpleStom adult questionnaire']);

        if (!$questionnaire) {
            $questionnaire = $this->entityManager->getRDBRepository('HealthQuestionnaire')->getNew();
            $filledAt = (new DateTimeImmutable('-20 days 10:00:00'))->format('Y-m-d H:i:s');
            $this->setValues($questionnaire, [
                'patientId' => $patients['adult']->getId(),
                'language' => 'ru_RU',
                'formLanguage' => 'ru_RU',
                'templateType' => 'adult',
                'templateVersion' => 1,
                'pdfLanguageMode' => 'es_ru',
                'items' => (object) [
                    'allergies' => ['value' => true, 'comment' => 'Пенициллин'],
                    'chronicDiseases' => ['value' => true, 'comment' => 'Гипертония'],
                    'pregnancy' => ['value' => false],
                ],
                'alertItems' => [['key' => 'allergies', 'label' => 'Аллергия']],
                'hasAlerts' => true,
                'filledAt' => $filledAt,
                'expiresAt' => (new DateTimeImmutable($filledAt))->modify('+1 year')->format('Y-m-d H:i:s'),
                'isExpired' => false,
                'submittedFromIp' => '127.0.0.1',
                'submittedUserAgent' => 'EspoDental demo seeder',
                'notes' => 'DEMO SimpleStom adult questionnaire',
            ]);
            $this->entityManager->saveEntity($questionnaire);
            $patients['adult']->set('lastQuestionnaireAt', $filledAt);
            $patients['adult']->set('questionnaireExpired', false);
            $patients['adult']->set('questionnaireHasAlerts', true);
            $this->entityManager->saveEntity($patients['adult']);
            $created++;
        }

        $token = $this->findOne('QuestionnaireToken', ['token' => 'demo-simple-stom-questionnaire-token']);
        if (!$token) {
            $token = $this->entityManager->getRDBRepository('QuestionnaireToken')->getNew();
            $this->setValues($token, [
                'name' => 'demo-simple-stom',
                'token' => 'demo-simple-stom-questionnaire-token',
                'patientId' => $patients['adult']->getId(),
                'questionnaireId' => $questionnaire->getId(),
                'language' => 'ru_RU',
                'expiresAt' => (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s'),
                'isUsed' => false,
            ]);
            $this->entityManager->saveEntity($token);
            $created++;
        }

        $session = $this->findOne('PatientPortalSession', ['tokenHash' => hash('sha256', 'demo-simple-stom-portal')]);
        if (!$session) {
            $session = $this->entityManager->getRDBRepository('PatientPortalSession')->getNew();
            $this->setValues($session, [
                'name' => 'DEMO SimpleStom portal session',
                'patientId' => $patients['adult']->getId(),
                'contactMethod' => 'email',
                'contactValueSnapshot' => 'alexey.smirnov@demo.local',
                'tokenHash' => hash('sha256', 'demo-simple-stom-portal'),
                'otpHash' => hash('sha256', '000000'),
                'otpAttemptCount' => 0,
                'expiresAt' => (new DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s'),
                'lastSeenAt' => (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'),
                'ipAddress' => '127.0.0.1',
                'userAgent' => 'EspoDental demo portal',
            ]);
            $this->entityManager->saveEntity($session);
            $created++;
        }

        $reschedule = $this->findOne('AppointmentRescheduleRequest', ['patientComment' => 'DEMO SimpleStom portal reschedule']);
        if (!$reschedule) {
            $reschedule = $this->entityManager->getRDBRepository('AppointmentRescheduleRequest')->getNew();
            $requestedStart = (new DateTimeImmutable('+2 days 12:00:00'))->format('Y-m-d H:i:s');
            $requestedEnd = (new DateTimeImmutable('+2 days 13:00:00'))->format('Y-m-d H:i:s');
            $this->setValues($reschedule, [
                'appointmentId' => $appointments['future']->getId(),
                'patientId' => $patients['adult']->getId(),
                'requestedBy' => 'patient',
                'source' => 'patient_portal',
                'requestedStartAt' => $requestedStart,
                'requestedEndAt' => $requestedEnd,
                'requestedDoctorId' => $users['doctor']->getId(),
                'requestedCabinetId' => $appointments['future']->get('cabinetId'),
                'requestedClinicId' => $clinic->getId(),
                'previousAppointmentStatus' => 'planned',
                'status' => 'pending_clinic_confirmation',
                'patientComment' => 'DEMO SimpleStom portal reschedule',
                'internalComment' => 'Review from dashboard/calendar feedback panel.',
            ]);
            $this->entityManager->saveEntity($reschedule);
            $created++;
        }

        $event = $this->findOne('PatientPortalEvent', ['errorDetail' => 'DEMO SimpleStom portal event']);
        if (!$event) {
            $event = $this->entityManager->getRDBRepository('PatientPortalEvent')->getNew();
            $this->setValues($event, [
                'name' => 'DEMO SimpleStom portal event',
                'patientId' => $patients['adult']->getId(),
                'sessionId' => $session->getId(),
                'appointmentId' => $appointments['future']->getId(),
                'rescheduleRequestId' => $reschedule->getId(),
                'action' => 'create_reschedule_request',
                'status' => 'success',
                'contactMethod' => 'email',
                'contactValueSnapshot' => 'alexey.smirnov@demo.local',
                'ipAddress' => '127.0.0.1',
                'userAgent' => 'EspoDental demo portal',
                'metadata' => (object) ['publicSlotsVisible' => false],
                'errorDetail' => 'DEMO SimpleStom portal event',
            ]);
            $this->entityManager->saveEntity($event);
            $created++;
        }

        return $created;
    }

    /**
     * @return array{visits: int, clinicalLines: int, invoices: int, payments: int, materialLine: ?Entity}
     */
    private function ensureVisitFinanceAndClinicalData(Entity $clinic, array $users, array $patients, array $appointments): array
    {
        $createdVisits = 0;
        $createdLines = 0;
        $createdInvoices = 0;
        $createdPayments = 0;

        $visit = $this->findOne('Visit', ['appointmentId' => $appointments['finished']->getId()]);
        if (!$visit) {
            $visit = $this->entityManager->getRDBRepository('Visit')->getNew();
            $this->setValues($visit, [
                'appointmentId' => $appointments['finished']->getId(),
                'patientId' => $patients['adult']->getId(),
                'doctorId' => $users['doctor']->getId(),
                'assistantId' => $users['assistant']->getId(),
                'cabinetId' => $appointments['finished']->get('cabinetId'),
                'clinicId' => $clinic->getId(),
                'status' => 'in_progress',
                'complaints' => 'Боль при накусывании на 36 зуб.',
                'performed' => 'Лечение кариеса 36, постановка пломбы.',
                'recommendations' => 'Контроль через 7 дней.',
                'treatmentPlan' => 'Профгигиена и контрольные снимки.',
                'startedAt' => $appointments['finished']->get('dateStart'),
            ]);
            $this->entityManager->saveEntity($visit, ['espodentalAllowVisitCreate' => true]);
            $createdVisits++;
        }

        $service = $this->findOneByCode('Service', 'THE-001') ?? $this->first('Service');
        $serviceLine = $service ? $this->findOne('VisitServiceLine', [
            'visitId' => $visit->getId(),
            'serviceId' => $service->getId(),
        ]) : null;
        if (!$serviceLine && $service) {
            $serviceLine = $this->entityManager->getRDBRepository('VisitServiceLine')->getNew();
            $this->setValues($serviceLine, [
                'visitId' => $visit->getId(),
                'serviceId' => $service->getId(),
                'doctorId' => $users['doctor']->getId(),
                'teethNumbers' => '36',
                'quantity' => 1,
                'discount' => 0,
                'notes' => 'DEMO SimpleStom service line.',
            ]);
            $this->entityManager->saveEntity($serviceLine, ['espodentalAllowFinishedVisitCorrection' => true]);
            $createdLines++;
        }

        $material = $this->findOneByCode('Material', 'REST-COMP') ?? $this->first('Material');
        $materialLine = $material ? $this->findOne('VisitMaterialLine', [
            'visitId' => $visit->getId(),
            'materialId' => $material->getId(),
        ]) : null;
        if (!$materialLine && $material) {
            $materialLine = $this->entityManager->getRDBRepository('VisitMaterialLine')->getNew();
            $this->setValues($materialLine, [
                'visitId' => $visit->getId(),
                'visitServiceLineId' => $serviceLine?->getId(),
                'serviceId' => $service?->getId(),
                'materialId' => $material->getId(),
                'plannedQuantity' => 0.2,
                'quantity' => 0.2,
                'isAutoCreated' => true,
                'notes' => 'DEMO SimpleStom FEFO consumption line.',
            ]);
            $this->entityManager->saveEntity($materialLine, ['espodentalAllowFinishedVisitCorrection' => true]);
            $createdLines++;
        }

        if ((string) $visit->get('status') !== 'finished') {
            $visit->set('status', 'finished');
            $visit->set('finishedAt', $appointments['finished']->get('dateEnd'));
            $this->entityManager->saveEntity($visit);
        }

        if ((string) $appointments['finished']->get('status') !== 'finished') {
            $appointments['finished']->set('status', 'finished');
            $this->entityManager->saveEntity($appointments['finished'], [
                'skipConflictCheck' => true,
                'espodentalAllowAppointmentSystemStatus' => true,
            ]);
        }

        $snapshot = $this->findOne('ToothChartSnapshot', ['visitId' => $visit->getId()]);
        if (!$snapshot) {
            $snapshot = $this->entityManager->getRDBRepository('ToothChartSnapshot')->getNew();
            $this->setValues($snapshot, [
                'patientId' => $patients['adult']->getId(),
                'visitId' => $visit->getId(),
                'doctorId' => $users['doctor']->getId(),
                'dentitionType' => 'adult',
                'teeth' => (object) [
                    '36' => [
                        'condition' => 'filling',
                        'surfaces' => ['o' => 'filling', 'm' => 'caries'],
                    ],
                    '46' => ['condition' => 'healthy'],
                ],
                'recordedAt' => $appointments['finished']->get('dateEnd'),
                'notes' => 'DEMO SimpleStom tooth chart snapshot.',
            ]);
            $this->entityManager->saveEntity($snapshot);
            $createdLines++;
        }

        $invoice = $this->findOne('Invoice', ['visitId' => $visit->getId()]);
        if (!$invoice) {
            $invoice = $this->entityManager->getRDBRepository('Invoice')->getNew();
            $this->setValues($invoice, [
                'patientId' => $patients['adult']->getId(),
                'clinicId' => $clinic->getId(),
                'visitId' => $visit->getId(),
                'status' => 'issued',
                'issuedAt' => $appointments['finished']->get('dateEnd'),
                'dueDate' => (new DateTimeImmutable('+7 days'))->format('Y-m-d'),
                'currency' => 'RUB',
                'notes' => 'DEMO SimpleStom invoice for cash desk.',
            ]);
            $this->entityManager->saveEntity($invoice, ['espodentalAllowInvoiceCreate' => true]);
            $createdInvoices++;
        }

        $invoiceLine = $serviceLine ? $this->findOne('InvoiceLine', [
            'invoiceId' => $invoice->getId(),
            'sourceVisitServiceLineId' => $serviceLine->getId(),
        ]) : null;
        if (!$invoiceLine && $serviceLine && $service) {
            $invoiceLine = $this->entityManager->getRDBRepository('InvoiceLine')->getNew();
            $this->setValues($invoiceLine, [
                'name' => (string) $service->get('name'),
                'invoiceId' => $invoice->getId(),
                'serviceId' => $service->getId(),
                'doctorId' => $users['doctor']->getId(),
                'teethNumbers' => '36',
                'quantity' => 1,
                'unitPrice' => (float) $service->get('price'),
                'discount' => 0,
                'vatRate' => 0,
                'sourceVisitServiceLineId' => $serviceLine->getId(),
                'notes' => 'DEMO SimpleStom invoice line.',
            ]);
            $this->entityManager->saveEntity($invoiceLine);
            $createdInvoices++;
        }

        $cashShift = $this->ensureCashShift($clinic, $users, $createdInvoices);
        $payment = $this->findOne('Payment', ['notes' => 'DEMO SimpleStom card payment']);
        if (!$payment) {
            $payment = $this->entityManager->getRDBRepository('Payment')->getNew();
            $this->setValues($payment, [
                'patientId' => $patients['adult']->getId(),
                'clinicId' => $clinic->getId(),
                'invoiceId' => $invoice->getId(),
                'method' => 'card',
                'amount' => 6000,
                'currency' => 'RUB',
                'status' => 'completed',
                'direction' => 'in',
                'paidAt' => $appointments['finished']->get('dateEnd'),
                'receivedById' => $users['administrator']->getId(),
                'cashShiftId' => $cashShift->getId(),
                'externalReference' => 'DEMO-CARD-001',
                'notes' => 'DEMO SimpleStom card payment',
            ]);
            $this->entityManager->saveEntity($payment);
            $createdPayments++;
        }

        $advance = $this->findOne('Payment', ['notes' => 'DEMO SimpleStom advance payment']);
        if (!$advance) {
            $advance = $this->entityManager->getRDBRepository('Payment')->getNew();
            $this->setValues($advance, [
                'patientId' => $patients['child']->getId(),
                'clinicId' => $clinic->getId(),
                'method' => 'advance',
                'amount' => 3000,
                'currency' => 'RUB',
                'status' => 'completed',
                'direction' => 'in',
                'paidAt' => (new DateTimeImmutable('today 09:30:00'))->format('Y-m-d H:i:s'),
                'receivedById' => $users['administrator']->getId(),
                'cashShiftId' => $cashShift->getId(),
                'notes' => 'DEMO SimpleStom advance payment',
            ]);
            $this->entityManager->saveEntity($advance);
            $createdPayments++;
        }

        return [
            'visits' => $createdVisits,
            'clinicalLines' => $createdLines,
            'invoices' => $createdInvoices,
            'payments' => $createdPayments,
            'materialLine' => $materialLine,
        ];
    }

    private function ensureCashShift(Entity $clinic, array $users, int &$created): Entity
    {
        $cashShift = $this->findOne('CashShift', ['notes' => 'DEMO SimpleStom closed cash shift']);

        if ($cashShift) {
            return $cashShift;
        }

        $cashShift = $this->entityManager->getRDBRepository('CashShift')->getNew();
        $this->setValues($cashShift, [
            'clinicId' => $clinic->getId(),
            'cashierId' => $users['administrator']->getId(),
            'status' => 'closed',
            'openedAt' => (new DateTimeImmutable('today 08:00:00'))->format('Y-m-d H:i:s'),
            'closedAt' => (new DateTimeImmutable('today 18:00:00'))->format('Y-m-d H:i:s'),
            'periodFrom' => (new DateTimeImmutable('today 08:00:00'))->format('Y-m-d H:i:s'),
            'periodTo' => (new DateTimeImmutable('today 18:00:00'))->format('Y-m-d H:i:s'),
            'cashTotal' => 3000,
            'cardTotal' => 6000,
            'cryptoTotal' => 0,
            'advanceTotal' => 3000,
            'invoiceTotal' => 6000,
            'notes' => 'DEMO SimpleStom closed cash shift',
        ]);
        $this->entityManager->saveEntity($cashShift);
        $created++;

        return $cashShift;
    }

    private function ensureInventoryScenario(Entity $clinic, array $users, ?Entity $materialLine): int
    {
        $created = 0;
        $material = $this->findOneByCode('Material', 'REST-COMP') ?? $this->first('Material');
        $mainWarehouse = $this->findOne('InventoryWarehouse', [
            'clinicId' => $clinic->getId(),
            'warehouseType' => 'main',
        ]);
        $cabinetWarehouse = $this->findOne('InventoryWarehouse', [
            'clinicId' => $clinic->getId(),
            'warehouseType' => 'satellite',
        ]);

        if (!$material || !$mainWarehouse) {
            return 0;
        }

        if (!$material->get('trackExpiration')) {
            $material->set('trackExpiration', true);
            $this->entityManager->saveEntity($material);
        }

        $receipt = $this->findOne('StockMovement', ['reason' => 'DEMO SimpleStom FEFO receipt']);
        if (!$receipt) {
            $receipt = $this->entityManager->getRDBRepository('StockMovement')->getNew();
            $this->setValues($receipt, [
                'materialId' => $material->getId(),
                'clinicId' => $clinic->getId(),
                'type' => 'receipt',
                'quantity' => 12,
                'unitPrice' => (float) $material->get('price'),
                'performedAt' => (new DateTimeImmutable('-3 days 09:00:00'))->format('Y-m-d H:i:s'),
                'performedById' => $users['stock']->getId(),
                'targetWarehouseId' => $mainWarehouse->getId(),
                'batch' => 'DEMO-FEFO',
                'expiryDate' => (new DateTimeImmutable('+6 months'))->format('Y-m-d'),
                'reason' => 'DEMO SimpleStom FEFO receipt',
            ]);
            $this->entityManager->saveEntity($receipt);
            $created++;
        }

        $lot = $this->findOne('InventoryStockLot', [
            'warehouseId' => $mainWarehouse->getId(),
            'materialId' => $material->getId(),
            'lotNumber' => 'DEMO-FEFO',
        ]);
        if (!$lot) {
            $lot = $this->entityManager->getRDBRepository('InventoryStockLot')->getNew();
            $this->setValues($lot, [
                'warehouseId' => $mainWarehouse->getId(),
                'materialId' => $material->getId(),
                'quantityInPurchasingUnits' => 12,
                'lotNumber' => 'DEMO-FEFO',
                'expiresAt' => (new DateTimeImmutable('+6 months'))->format('Y-m-d'),
                'receivedAt' => (new DateTimeImmutable('-3 days'))->format('Y-m-d'),
                'sourceTransactionId' => $receipt->getId(),
            ]);
            $this->entityManager->saveEntity($lot);
            $created++;
        }

        if ($cabinetWarehouse && !$this->findOne('StockMovement', ['reason' => 'DEMO SimpleStom issue to cabinet'])) {
            $transfer = $this->entityManager->getRDBRepository('StockMovement')->getNew();
            $this->setValues($transfer, [
                'materialId' => $material->getId(),
                'clinicId' => $clinic->getId(),
                'type' => 'transfer_out',
                'quantity' => 2,
                'unitPrice' => (float) $material->get('price'),
                'performedAt' => (new DateTimeImmutable('-2 days 10:00:00'))->format('Y-m-d H:i:s'),
                'performedById' => $users['stock']->getId(),
                'sourceWarehouseId' => $mainWarehouse->getId(),
                'targetWarehouseId' => $cabinetWarehouse->getId(),
                'stockLotId' => $lot->getId(),
                'reason' => 'DEMO SimpleStom issue to cabinet',
            ]);
            $this->entityManager->saveEntity($transfer);
            $created++;
        }

        if (!$this->findOne('StockMovement', ['reason' => 'DEMO SimpleStom FEFO visit consumption'])) {
            $usage = $this->entityManager->getRDBRepository('StockMovement')->getNew();
            $this->setValues($usage, [
                'materialId' => $material->getId(),
                'clinicId' => $clinic->getId(),
                'type' => 'reception_usage',
                'quantity' => 0.2,
                'unitPrice' => (float) $material->get('price'),
                'performedAt' => (new DateTimeImmutable('yesterday 11:00:00'))->format('Y-m-d H:i:s'),
                'performedById' => $users['assistant']->getId(),
                'sourceVisitMaterialLineId' => $materialLine?->getId(),
                'sourceWarehouseId' => $cabinetWarehouse?->getId() ?? $mainWarehouse->getId(),
                'stockLotId' => $lot->getId(),
                'reason' => 'DEMO SimpleStom FEFO visit consumption',
            ]);
            $this->entityManager->saveEntity($usage);
            $created++;
        }

        $alertMaterial = $this->findOneByCode('Material', 'DIS-MASK') ?? $material;
        if (!$this->findOne('LowStockAlert', ['name' => 'DEMO SimpleStom low stock alert'])) {
            $alert = $this->entityManager->getRDBRepository('LowStockAlert')->getNew();
            $this->setValues($alert, [
                'name' => 'DEMO SimpleStom low stock alert',
                'materialId' => $alertMaterial->getId(),
                'clinicId' => $clinic->getId(),
                'level' => 'low',
                'currentStock' => 18,
                'threshold' => 200,
                'status' => 'open',
                'raisedAt' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
                'assignedUserId' => $users['stock']->getId(),
                'notes' => 'DEMO SimpleStom inventory feedback alert.',
            ]);
            $this->entityManager->saveEntity($alert);
            $created++;
        }

        return $created;
    }

    private function ensurePayroll(Entity $clinic, array $users): int
    {
        $created = 0;
        $profile = $this->findOne('SalaryProfile', ['name' => 'DEMO SimpleStom doctor payroll profile']);

        if (!$profile) {
            $profile = $this->entityManager->getRDBRepository('SalaryProfile')->getNew();
            $this->setValues($profile, [
                'name' => 'DEMO SimpleStom doctor payroll profile',
                'userId' => $users['doctor']->getId(),
                'clinicId' => $clinic->getId(),
                'roleType' => 'doctor',
                'rateType' => 'fixed_monthly',
                'baseRate' => 80000,
                'currency' => 'RUB',
                'revenuePercent' => 10,
                'assistantPercent' => 0,
                'dateStart' => (new DateTimeImmutable('first day of january this year'))->format('Y-m-d'),
                'isActive' => true,
                'notes' => 'DEMO SimpleStom payroll profile.',
            ]);
            $this->entityManager->saveEntity($profile);
            $created++;
        }

        if (!$this->findOne('SalaryBonus', ['name' => 'DEMO SimpleStom manual bonus'])) {
            $bonus = $this->entityManager->getRDBRepository('SalaryBonus')->getNew();
            $this->setValues($bonus, [
                'name' => 'DEMO SimpleStom manual bonus',
                'userId' => $users['doctor']->getId(),
                'kind' => 'bonus',
                'amount' => 5000,
                'currency' => 'RUB',
                'reason' => 'Премия за выполнение плана.',
                'dateApplied' => (new DateTimeImmutable('today'))->format('Y-m-d'),
                'status' => 'pending',
            ]);
            $this->entityManager->saveEntity($bonus);
            $created++;
        }

        $periodFrom = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $periodTo = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');
        if (!$this->findOne('SalaryEntry', ['name' => 'DEMO SimpleStom payroll source breakdown'])) {
            $entry = $this->entityManager->getRDBRepository('SalaryEntry')->getNew();
            $this->setValues($entry, [
                'name' => 'DEMO SimpleStom payroll source breakdown',
                'userId' => $users['doctor']->getId(),
                'profileId' => $profile->getId(),
                'clinicId' => $clinic->getId(),
                'periodFrom' => $periodFrom,
                'periodTo' => $periodTo,
                'status' => 'approved',
                'baseAmount' => 80000,
                'revenueAmount' => 600,
                'assistantAmount' => 0,
                'bonusAmount' => 5000,
                'deductionAmount' => 1000,
                'currency' => 'RUB',
                'revenueBasis' => 6000,
                'visitsCount' => 1,
                'hoursWorked' => 160,
                'sourceBreakdown' => (object) [
                    'doctor' => ['sourceType' => 'reception', 'revenueBasis' => 6000, 'visitsCount' => 1],
                    'assistant' => ['sourceType' => 'reception', 'revenueBasis' => 0],
                    'manualAdjustments' => ['sourceType' => 'manual_adjustment', 'bonus' => 5000, 'deduction' => 1000],
                    'rule' => ['profileId' => $profile->getId(), 'rateType' => 'fixed_monthly'],
                ],
                'approvedAt' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'approvedById' => $users['manager']->getId(),
                'notes' => 'DEMO SimpleStom payroll line with source breakdown.',
            ]);
            $this->entityManager->saveEntity($entry);
            $created++;
        }

        return $created;
    }

    private function ensureIntegrationDemo(Entity $clinic): int
    {
        $created = 0;

        foreach (['smtp', 'telegram', 'whatsapp'] as $type) {
            if ($this->findOne('IntegrationSettings', ['clinicId' => $clinic->getId(), 'integrationType' => $type])) {
                continue;
            }
            $settings = $this->entityManager->getRDBRepository('IntegrationSettings')->getNew();
            $this->setValues($settings, [
                'clinicId' => $clinic->getId(),
                'integrationType' => $type,
                'isEnabled' => false,
                'settings' => (object) ['demo' => true, 'externalCallsDisabled' => true],
                'secretsReference' => 'demo-' . $type,
            ]);
            $this->entityManager->saveEntity($settings);
            $created++;
        }

        if (!$this->findOne('IntegrationSecret', ['clinicId' => $clinic->getId(), 'name' => 'demo-smtp'])) {
            $secret = $this->entityManager->getRDBRepository('IntegrationSecret')->getNew();
            $this->setValues($secret, [
                'clinicId' => $clinic->getId(),
                'name' => 'demo-smtp',
                'secretKind' => 'smtp_password',
                'secretValue' => 'demo-secret-not-for-production',
            ]);
            $this->entityManager->saveEntity($secret);
            $created++;
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $where
     */
    private function findOne(string $entityType, array $where): ?Entity
    {
        /** @var Entity|null */
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->where($where)
            ->findOne();
    }

    private function findOneByCode(string $entityType, string $code): ?Entity
    {
        return $this->findOne($entityType, ['code' => $code]);
    }

    private function first(string $entityType): ?Entity
    {
        /** @var Entity|null */
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->where(['deleted' => false])
            ->findOne();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function setValues(Entity $entity, array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value !== null) {
                $entity->set($key, $value);
            }
        }
    }
}
