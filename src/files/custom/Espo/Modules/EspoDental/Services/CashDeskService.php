<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\CashShift;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\Payment;
use Espo\Modules\EspoDental\Entities\Visit;

class CashDeskService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly User $user
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getWorkspace(
        ?string $clinicId = null,
        ?string $doctorId = null,
        bool $unpaidOnly = true,
        int $limit = 40,
        ?string $selectedInvoiceId = null
    ): array {
        $invoices = $this->getInvoiceRows($clinicId, $doctorId, $unpaidOnly, $limit);

        return [
            'filters' => [
                'clinicId' => $clinicId,
                'doctorId' => $doctorId,
                'unpaidOnly' => $unpaidOnly,
            ],
            'doctorOptions' => $this->getDoctorOptions($clinicId, $unpaidOnly),
            'invoices' => $invoices,
            'selectedInvoice' => $this->pickSelectedInvoice($invoices, $selectedInvoiceId),
            'closingPreview' => $clinicId ? $this->getClosingPreview($clinicId) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function closeShift(string $clinicId, ?string $periodFrom = null, ?string $periodTo = null): array
    {
        if ($clinicId === '') {
            throw new BadRequest('clinicId is required');
        }

        $preview = $this->getClosingPreview($clinicId, $periodFrom, $periodTo);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        /** @var CashShift $shift */
        $shift = $this->entityManager->getNewEntity(CashShift::ENTITY_TYPE);
        $shift->set('clinicId', $clinicId);
        $shift->set('cashierId', $this->user->getId());
        $shift->set('status', CashShift::STATUS_CLOSED);
        $shift->set('openedAt', $periodFrom ?: $preview['periodFrom']);
        $shift->set('closedAt', $now);
        $shift->set('periodFrom', $periodFrom ?: $preview['periodFrom']);
        $shift->set('periodTo', $periodTo ?: $preview['periodTo']);
        foreach (['cashTotal', 'cardTotal', 'cryptoTotal', 'advanceTotal', 'invoiceTotal'] as $field) {
            $shift->set($field, $preview[$field]);
        }
        $this->entityManager->saveEntity($shift);

        return [
            'cashShiftId' => (string) $shift->getId(),
            'totals' => $preview,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getClosingPreview(
        string $clinicId,
        ?string $periodFrom = null,
        ?string $periodTo = null
    ): array {
        $periodFrom = $periodFrom ?: (new DateTimeImmutable('today'))->format('Y-m-d 00:00:00');
        $periodTo = $periodTo ?: (new DateTimeImmutable())->format('Y-m-d H:i:s');

        /** @var iterable<Payment> $payments */
        $payments = $this->entityManager
            ->getRDBRepository(Payment::ENTITY_TYPE)
            ->where([
                'clinicId' => $clinicId,
                'status' => Payment::STATUS_COMPLETED,
                'paidAt>=' => $periodFrom,
                'paidAt<=' => $periodTo,
            ])
            ->find();

        $totals = [
            'periodFrom' => $periodFrom,
            'periodTo' => $periodTo,
            'cashTotal' => 0.0,
            'cardTotal' => 0.0,
            'cryptoTotal' => 0.0,
            'advanceTotal' => 0.0,
            'invoiceTotal' => 0.0,
            'paymentCount' => 0,
        ];

        foreach ($payments as $payment) {
            $signed = $payment->getDirection() === Payment::DIRECTION_OUT
                ? -$payment->getAmount()
                : $payment->getAmount();
            $method = (string) $payment->get('method');
            if ($method === Payment::METHOD_CASH) {
                $totals['cashTotal'] += $signed;
            } elseif ($method === Payment::METHOD_CARD || $method === Payment::METHOD_TERMINAL) {
                $totals['cardTotal'] += $signed;
            } elseif ($method === Payment::METHOD_CRYPTO) {
                $totals['cryptoTotal'] += $signed;
            } elseif ($method === Payment::METHOD_ADVANCE) {
                $totals['advanceTotal'] += $signed;
            }
            if ($payment->getInvoiceId()) {
                $totals['invoiceTotal'] += $signed;
            }
            $totals['paymentCount']++;
        }

        foreach (['cashTotal', 'cardTotal', 'cryptoTotal', 'advanceTotal', 'invoiceTotal'] as $field) {
            $totals[$field] = round((float) $totals[$field], 2);
        }

        return $totals;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getInvoiceRows(?string $clinicId, ?string $doctorId, bool $unpaidOnly, int $limit): array
    {
        $where = ['deleted' => false];
        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }
        if ($unpaidOnly) {
            $where['status'] = [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID];
        }

        /** @var iterable<Invoice> $invoices */
        $invoices = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where($where)
            ->order('issuedAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($invoices as $invoice) {
            $visit = $this->getInvoiceVisit($invoice);

            if ($doctorId && (!$visit || (string) ($visit->get('doctorId') ?? '') !== $doctorId)) {
                continue;
            }

            $rows[] = $this->buildInvoiceRow($invoice, $visit);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $invoices
     * @return array<string, mixed>|null
     */
    private function pickSelectedInvoice(array $invoices, ?string $selectedInvoiceId): ?array
    {
        foreach ($invoices as $invoice) {
            if ($selectedInvoiceId && $invoice['id'] === $selectedInvoiceId) {
                return $invoice;
            }
        }

        return $invoices[0] ?? null;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function getDoctorOptions(?string $clinicId, bool $unpaidOnly): array
    {
        $options = [];

        foreach ($this->getInvoiceRows($clinicId, null, $unpaidOnly, 200) as $invoice) {
            $doctorId = (string) ($invoice['doctorId'] ?? '');
            if ($doctorId === '' || isset($options[$doctorId])) {
                continue;
            }

            $options[$doctorId] = [
                'id' => $doctorId,
                'name' => (string) ($invoice['doctorName'] ?: $doctorId),
            ];
        }

        usort($options, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return array_values($options);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInvoiceRow(Invoice $invoice, ?Visit $visit): array
    {
        $status = (string) ($invoice->getStatus() ?? '');
        $balance = $invoice->getBalance();

        return [
            'id' => (string) $invoice->getId(),
            'number' => (string) ($invoice->get('number') ?? ''),
            'patientId' => (string) ($invoice->get('patientId') ?? ''),
            'patientName' => (string) ($invoice->get('patientName') ?? ''),
            'clinicId' => (string) ($invoice->get('clinicId') ?? ''),
            'clinicName' => (string) ($invoice->get('clinicName') ?? ''),
            'visitId' => (string) ($invoice->get('visitId') ?? ''),
            'visitName' => $visit ? (string) ($visit->get('name') ?? '') : '',
            'doctorId' => $visit ? (string) ($visit->get('doctorId') ?? '') : '',
            'doctorName' => $visit ? (string) ($visit->get('doctorName') ?? '') : '',
            'status' => $status,
            'issuedAt' => (string) ($invoice->get('issuedAt') ?? ''),
            'totalAmount' => $invoice->getTotalAmount(),
            'paidAmount' => $invoice->getPaidAmount(),
            'balance' => $balance,
            'payable' => in_array($status, [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID], true)
                && round($balance, 2) > 0.0,
        ];
    }

    private function getInvoiceVisit(Invoice $invoice): ?Visit
    {
        $visitId = (string) ($invoice->get('visitId') ?? '');
        if ($visitId === '') {
            return null;
        }

        $visit = $this->entityManager->getEntityById(Visit::ENTITY_TYPE, $visitId);

        return $visit instanceof Visit ? $visit : null;
    }
}
