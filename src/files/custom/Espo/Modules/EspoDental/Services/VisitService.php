<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\ToothChartSnapshot;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitMaterialLine;
use Espo\Modules\EspoDental\Entities\VisitNoteTemplate;
use Espo\Modules\EspoDental\Entities\VisitPhoto;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;

class VisitService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InvoiceService $invoiceService,
        private readonly StockService $stockService,
        private readonly User $user
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getReceptionWorkspace(string $visitId): array
    {
        $visit = $this->getVisitOrFail($visitId);
        $isFinished = $visit->getStatus() === Visit::STATUS_FINISHED;
        $counts = $this->getReceptionCounts($visit);

        return [
            'visitId' => (string) $visit->getId(),
            'status' => $visit->getStatus(),
            'isLocked' => $isFinished,
            'allowedSections' => [
                'complaints' => !$isFinished,
                'performed' => !$isFinished,
                'recommendations' => !$isFinished,
                'treatmentPlan' => true,
                'photos' => true,
            ],
            'notes' => [
                'complaints' => (string) ($visit->get('complaints') ?? ''),
                'performed' => (string) ($visit->get('performed') ?? ''),
                'recommendations' => (string) ($visit->get('recommendations') ?? ''),
                'treatmentPlan' => (string) ($visit->get('treatmentPlan') ?? ''),
            ],
            'counts' => $counts,
            'checklist' => $this->buildReceptionChecklist($visit, $counts),
            'templates' => $this->getPersonalTemplates(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: true, savedFields: list<string>, isLocked: bool}
     */
    public function autosaveReceptionNotes(string $visitId, array $data): array
    {
        $visit = $this->getVisitOrFail($visitId);
        $isFinished = $visit->getStatus() === Visit::STATUS_FINISHED;
        $editable = $isFinished
            ? ['treatmentPlan']
            : ['complaints', 'performed', 'recommendations', 'treatmentPlan'];

        $saved = [];
        foreach ($editable as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $visit->set($field, trim((string) $data[$field]));
            $saved[] = $field;
        }

        if ($saved !== []) {
            $this->entityManager->saveEntity($visit);
        }

        return [
            'ok' => true,
            'savedFields' => $saved,
            'isLocked' => $isFinished,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{templateId: string}
     */
    public function createNoteTemplate(array $data): array
    {
        $section = (string) ($data['section'] ?? VisitNoteTemplate::SECTION_PERFORMED);
        if (
            !in_array($section, [
                VisitNoteTemplate::SECTION_COMPLAINTS,
                VisitNoteTemplate::SECTION_PERFORMED,
                VisitNoteTemplate::SECTION_RECOMMENDATIONS,
                VisitNoteTemplate::SECTION_TREATMENT_PLAN,
            ], true)
        ) {
            $section = VisitNoteTemplate::SECTION_PERFORMED;
        }

        /** @var VisitNoteTemplate $template */
        $template = $this->entityManager->getNewEntity(VisitNoteTemplate::ENTITY_TYPE);
        $template->set('name', trim((string) ($data['name'] ?? 'Visit template')));
        $template->set('ownerUserId', $this->user->getId());
        $template->set('section', $section);
        $template->set('body', trim((string) ($data['body'] ?? '')));
        $template->set('isShared', (bool) ($data['isShared'] ?? false));

        $this->entityManager->saveEntity($template);

        return ['templateId' => (string) $template->getId()];
    }

    /**
     * @return array{visitId: string, total: float, lineCount: int, invoiceId: ?string}
     */
    public function finishVisit(string $visitId): array
    {
        /** @var array{visitId: string, total: float, lineCount: int, invoiceId: ?string} $result */
        $result = $this->entityManager->getTransactionManager()->run(
            fn (): array => $this->finishVisitInTransaction($visitId)
        );

        return $result;
    }

    /**
     * @return array{visitId: string, total: float, lineCount: int, invoiceId: ?string}
     */
    private function finishVisitInTransaction(string $visitId): array
    {
        $visit = $this->getVisitOrFail($visitId);

        if (!in_array($visit->getStatus(), [Visit::STATUS_IN_PROGRESS, Visit::STATUS_FINISHED], true)) {
            throw new Conflict('Visit is not in progress');
        }

        $total = $this->recalculateTotal($visit);
        $lineCount = $this->countLines($visit);

        $invoiceId = null;
        if ($lineCount > 0) {
            $invoice = $this->invoiceService->buildFromVisit($visit);
            $invoiceId = (string) $invoice->getId();
        }

        $this->stockService->consumeForVisit($visit);

        $this->markVisitFinished($visit, $total);
        $this->markAppointmentFinished($visit);

        return [
            'visitId' => (string) $visit->getId(),
            'total' => $total,
            'lineCount' => $lineCount,
            'invoiceId' => $invoiceId,
        ];
    }

    private function markVisitFinished(Visit $visit, float $total): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if (!$visit->get('finishedAt')) {
            $visit->set('finishedAt', $now);
        }

        $visit->set('status', Visit::STATUS_FINISHED);
        $visit->set('totalAmount', $total);
        $this->entityManager->saveEntity($visit);
    }

    private function markAppointmentFinished(Visit $visit): void
    {
        if ($visit->getAppointmentId()) {
            /** @var Appointment|null $appointment */
            $appointment = $this->entityManager->getEntityById(
                Appointment::ENTITY_TYPE,
                $visit->getAppointmentId()
            );
            if ($appointment && $appointment->getStatus() === Appointment::STATUS_IN_PROGRESS) {
                $appointment->set('status', Appointment::STATUS_FINISHED);
                $this->entityManager->saveEntity($appointment, [
                    'skipConflictCheck' => true,
                    'espodentalAllowAppointmentSystemStatus' => true,
                ]);
            }
        }
    }

    public function recalculateTotal(Visit $visit): float
    {
        /** @var iterable<VisitServiceLine> $lines */
        $lines = $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->find();

        $total = 0.0;
        foreach ($lines as $line) {
            $total += $line->getAmount();
        }
        return round($total, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function getToothChartData(string $visitId): array
    {
        $visit = $this->getVisitOrFail($visitId);

        /** @var ToothChartSnapshot|null $snapshot */
        $snapshot = $this->entityManager
            ->getRDBRepository(ToothChartSnapshot::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->order('recordedAt', 'DESC')
            ->findOne();

        if (!$snapshot) {
            $snapshot = $this->createToothChartSnapshot($visit);
        }

        if (!$snapshot) {
            return ['id' => null];
        }

        return [
            'id' => $snapshot->getId(),
            'dentitionType' => $snapshot->get('dentitionType'),
            'teeth' => $snapshot->getTeeth(),
            'recordedAt' => $snapshot->get('recordedAt'),
        ];
    }

    private function createToothChartSnapshot(Visit $visit): ?ToothChartSnapshot
    {
        if (!$visit->getPatientId()) {
            return null;
        }

        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $visit->getPatientId());

        if (!$patient) {
            return null;
        }

        /** @var ToothChartSnapshot $snapshot */
        $snapshot = $this->entityManager->getNewEntity(ToothChartSnapshot::ENTITY_TYPE);
        $snapshot->set('name', 'Зубная формула — ' . (string) $visit->get('name'));
        $snapshot->set('patientId', $patient->getId());
        $snapshot->set('visitId', $visit->getId());
        $snapshot->set('doctorId', $visit->getDoctorId());
        $snapshot->set(
            'dentitionType',
            $patient->isChild() ? ToothChartSnapshot::DENTITION_MIXED : ToothChartSnapshot::DENTITION_ADULT
        );
        $snapshot->set('teeth', (object) []);
        $snapshot->set('recordedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));

        $this->entityManager->saveEntity($snapshot);

        return $snapshot;
    }

    private function countLines(Visit $visit): int
    {
        return (int) $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->count();
    }

    private function getVisitOrFail(string $visitId): Visit
    {
        /** @var Visit|null $visit */
        $visit = $this->entityManager->getEntityById(Visit::ENTITY_TYPE, $visitId);

        if (!$visit) {
            throw new NotFound('Visit not found');
        }

        return $visit;
    }

    /**
     * @return array<string, int>
     */
    private function getReceptionCounts(Visit $visit): array
    {
        $visitId = (string) $visit->getId();

        return [
            'services' => $this->countByVisit(VisitServiceLine::ENTITY_TYPE, $visitId),
            'materials' => $this->countByVisit(VisitMaterialLine::ENTITY_TYPE, $visitId),
            'photos' => $this->countByVisit(VisitPhoto::ENTITY_TYPE, $visitId),
            'toothCharts' => $this->countByVisit(ToothChartSnapshot::ENTITY_TYPE, $visitId),
            'invoices' => $this->countByVisit(Invoice::ENTITY_TYPE, $visitId),
        ];
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{key: string, label: string, done: bool}>
     */
    private function buildReceptionChecklist(Visit $visit, array $counts): array
    {
        return [
            [
                'key' => 'complaints',
                'label' => 'Complaints',
                'done' => trim((string) ($visit->get('complaints') ?? '')) !== '',
            ],
            [
                'key' => 'treatmentNotes',
                'label' => 'Treatment notes',
                'done' => trim((string) ($visit->get('performed') ?? '')) !== '',
            ],
            ['key' => 'services', 'label' => 'Services', 'done' => $counts['services'] > 0],
            ['key' => 'materials', 'label' => 'Materials', 'done' => $counts['materials'] > 0],
            ['key' => 'toothChart', 'label' => 'Tooth chart', 'done' => $counts['toothCharts'] > 0],
            [
                'key' => 'invoice',
                'label' => 'Invoice',
                'done' => $visit->getStatus() !== Visit::STATUS_FINISHED || $counts['invoices'] > 0,
            ],
        ];
    }

    /**
     * @return list<array{id: string, name: string, section: string, body: string, isShared: bool}>
     */
    private function getPersonalTemplates(): array
    {
        /** @var iterable<VisitNoteTemplate> $templates */
        $templates = $this->entityManager
            ->getRDBRepository(VisitNoteTemplate::ENTITY_TYPE)
            ->where([
                'OR' => [
                    ['ownerUserId' => $this->user->getId()],
                    ['isShared' => true],
                ],
            ])
            ->order('name', 'ASC')
            ->find();

        $rows = [];
        foreach ($templates as $template) {
            $rows[] = [
                'id' => (string) $template->getId(),
                'name' => (string) ($template->get('name') ?? ''),
                'section' => (string) ($template->get('section') ?? ''),
                'body' => (string) ($template->get('body') ?? ''),
                'isShared' => (bool) ($template->get('isShared') ?? false),
            ];
        }

        return $rows;
    }

    private function countByVisit(string $entityType, string $visitId): int
    {
        return (int) $this->entityManager
            ->getRDBRepository($entityType)
            ->where(['visitId' => $visitId])
            ->count();
    }
}
