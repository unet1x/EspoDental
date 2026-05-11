<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\InvoiceLine;
use Espo\Modules\EspoDental\Entities\Payment;

class InvoiceCalculator
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function recalculate(Invoice $invoice): void
    {
        /** @var iterable<InvoiceLine> $lines */
        $lines = $this->entityManager
            ->getRDBRepository(InvoiceLine::ENTITY_TYPE)
            ->where(['invoiceId' => $invoice->getId()])
            ->find();

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $vatTotal = 0.0;
        $afterDiscount = 0.0;

        foreach ($lines as $line) {
            $qty = max(1, $line->getQuantity());
            $unit = $line->getUnitPrice();
            $gross = $qty * $unit;
            $subtotal += $gross;
            $afterDiscount += $line->getAmount();
            $discountTotal += $gross - $line->getAmount();
            $vatTotal += $line->getVatAmount();
        }

        $total = round($afterDiscount + $vatTotal, 2);

        $invoice->set('subtotal', round($subtotal, 2));
        $invoice->set('discountAmount', round($discountTotal, 2));
        $invoice->set('vatAmount', round($vatTotal, 2));
        $invoice->set('totalAmount', $total);

        $paid = $this->sumCompletedPayments($invoice);
        $invoice->set('paidAmount', $paid);
        $balance = round($total - $paid, 2);
        $invoice->set('balance', $balance);

        $status = $invoice->getStatus();
        if (
            $status !== Invoice::STATUS_DRAFT
            && $status !== Invoice::STATUS_STORNO
            && $status !== Invoice::STATUS_CANCELLED
        ) {
            if ($balance <= 0 && $total > 0) {
                $invoice->set('status', Invoice::STATUS_PAID);
            } elseif ($paid > 0 && $balance > 0) {
                $invoice->set('status', Invoice::STATUS_PARTIAL_PAID);
            } else {
                $invoice->set('status', Invoice::STATUS_ISSUED);
            }
        }
    }

    private function sumCompletedPayments(Invoice $invoice): float
    {
        /** @var iterable<Payment> $payments */
        $payments = $this->entityManager
            ->getRDBRepository(Payment::ENTITY_TYPE)
            ->where([
                'invoiceId' => $invoice->getId(),
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
