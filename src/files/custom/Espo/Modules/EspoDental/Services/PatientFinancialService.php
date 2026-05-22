<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\Payment;

class PatientFinancialService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{
     *     patientId: string,
     *     balance: float,
     *     openInvoiceBalance: float,
     *     unallocatedCredit: float,
     *     openInvoices: list<array<string, mixed>>,
     *     recentPayments: list<array<string, mixed>>
     * }
     */
    public function getPatientFinancials(
        string $patientId,
        bool $includeInvoices = true,
        bool $includePayments = true,
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
            'balance' => round($patient->getBalance(), 2),
            'openInvoiceBalance' => $includeInvoices ? $this->sumOpenInvoiceBalance($patientId) : 0.0,
            'unallocatedCredit' => $includePayments ? $this->sumUnallocatedCredit($patientId) : 0.0,
            'openInvoices' => $includeInvoices ? $this->getOpenInvoices($patientId, $limit) : [],
            'recentPayments' => $includePayments ? $this->getRecentPayments($patientId, $limit) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getOpenInvoices(string $patientId, int $limit): array
    {
        /** @var iterable<Invoice> $invoices */
        $invoices = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where([
                'patientId' => $patientId,
                'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
                'balance>' => 0,
            ])
            ->order('issuedAt', 'ASC')
            ->find();

        $rows = [];
        foreach ($invoices as $invoice) {
            $timeZone = $this->resolveTimeZone((string) ($invoice->get('clinicId') ?: ''));

            $rows[] = [
                'id' => (string) $invoice->getId(),
                'number' => (string) $invoice->get('number'),
                'name' => (string) $invoice->get('name'),
                'status' => (string) $invoice->getStatus(),
                'issuedAt' => (string) $invoice->get('issuedAt'),
                'localIssuedAt' => $this->formatLocalDateTime((string) $invoice->get('issuedAt'), $timeZone),
                'currency' => (string) $invoice->get('currency'),
                'totalAmount' => $invoice->getTotalAmount(),
                'paidAmount' => $invoice->getPaidAmount(),
                'balance' => $invoice->getBalance(),
                'visitId' => $invoice->get('visitId'),
                'visitName' => $invoice->get('visitName'),
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
    private function getRecentPayments(string $patientId, int $limit): array
    {
        /** @var iterable<Payment> $payments */
        $payments = $this->entityManager
            ->getRDBRepository(Payment::ENTITY_TYPE)
            ->where([
                'patientId' => $patientId,
                'status' => [
                    Payment::STATUS_COMPLETED,
                    Payment::STATUS_REFUNDED,
                ],
            ])
            ->order('paidAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($payments as $payment) {
            $timeZone = $this->resolveTimeZone((string) ($payment->get('clinicId') ?: ''));

            $rows[] = [
                'id' => (string) $payment->getId(),
                'number' => (string) $payment->get('number'),
                'status' => (string) $payment->getStatus(),
                'direction' => (string) $payment->getDirection(),
                'method' => (string) $payment->get('method'),
                'paidAt' => (string) $payment->get('paidAt'),
                'localPaidAt' => $this->formatLocalDateTime((string) $payment->get('paidAt'), $timeZone),
                'currency' => (string) $payment->get('currency'),
                'amount' => $payment->getAmount(),
                'invoiceId' => $payment->getInvoiceId(),
                'invoiceName' => $payment->get('invoiceName'),
                'refundOfId' => $payment->get('refundOfId'),
                'refundOfName' => $payment->get('refundOfName'),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function sumOpenInvoiceBalance(string $patientId): float
    {
        /** @var iterable<Invoice> $invoices */
        $invoices = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where([
                'patientId' => $patientId,
                'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
                'balance>' => 0,
            ])
            ->find();

        $sum = 0.0;
        foreach ($invoices as $invoice) {
            $sum += $invoice->getBalance();
        }

        return round($sum, 2);
    }

    private function sumUnallocatedCredit(string $patientId): float
    {
        /** @var iterable<Payment> $payments */
        $payments = $this->entityManager
            ->getRDBRepository(Payment::ENTITY_TYPE)
            ->where([
                'patientId' => $patientId,
                'invoiceId' => null,
                'status' => Payment::STATUS_COMPLETED,
            ])
            ->find();

        $sum = 0.0;
        foreach ($payments as $payment) {
            $sign = $payment->getDirection() === Payment::DIRECTION_OUT ? -1.0 : 1.0;
            $sum += $sign * $payment->getAmount();
        }

        return round($sum, 2);
    }

    private function resolveTimeZone(?string $clinicId = null): DateTimeZone
    {
        if ($clinicId) {
            /** @var Clinic|null $clinic */
            $clinic = $this->entityManager->getEntityById(Clinic::ENTITY_TYPE, $clinicId);

            if ($clinic) {
                $timeZone = (string) ($clinic->get('timezone') ?: '');

                if ($timeZone !== '') {
                    return $this->buildTimeZone($timeZone);
                }
            }
        }

        return $this->buildTimeZone((string) ($this->config->get('timeZone') ?: 'UTC'));
    }

    private function buildTimeZone(string $timeZone): DateTimeZone
    {
        try {
            return new DateTimeZone($timeZone);
        } catch (\Exception) {
            return new DateTimeZone('UTC');
        }
    }

    private function formatLocalDateTime(?string $value, DateTimeZone $timeZone): string
    {
        if (!$value) {
            return '';
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone($timeZone)
                ->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return '';
        }
    }
}
