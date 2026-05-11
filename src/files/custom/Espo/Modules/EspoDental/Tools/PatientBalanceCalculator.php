<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\Payment;

class PatientBalanceCalculator
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * Convention: positive balance = patient owes the clinic.
     * Negative balance = patient has prepaid credit.
     */
    public function recalculate(Patient $patient): void
    {
        $openInvoiceBalance = $this->sumOpenInvoiceBalance($patient);
        $unallocatedCredit = $this->sumUnallocatedPayments($patient);

        $balance = round($openInvoiceBalance - $unallocatedCredit, 2);
        $patient->set('balance', $balance);
    }

    private function sumOpenInvoiceBalance(Patient $patient): float
    {
        /** @var iterable<Invoice> $invoices */
        $invoices = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where([
                'patientId' => $patient->getId(),
                'status!=' => [
                    Invoice::STATUS_STORNO,
                    Invoice::STATUS_CANCELLED,
                    Invoice::STATUS_DRAFT,
                ],
            ])
            ->find();

        $sum = 0.0;
        foreach ($invoices as $invoice) {
            $sum += $invoice->getBalance();
        }
        return round($sum, 2);
    }

    private function sumUnallocatedPayments(Patient $patient): float
    {
        /** @var iterable<Payment> $payments */
        $payments = $this->entityManager
            ->getRDBRepository(Payment::ENTITY_TYPE)
            ->where([
                'patientId' => $patient->getId(),
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
}
