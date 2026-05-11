<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Payment;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\Payment;
use Espo\Modules\EspoDental\Tools\InvoiceCalculator;
use Espo\Modules\EspoDental\Tools\PatientBalanceCalculator;
use Espo\ORM\Entity;

class UpdateInvoiceAndBalance
{
    public static int $order = 90;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InvoiceCalculator $invoiceCalc,
        private readonly PatientBalanceCalculator $balanceCalc
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Payment) {
            return;
        }
        $this->refresh($entity);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterRemove(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Payment) {
            return;
        }
        $this->refresh($entity);
    }

    private function refresh(Payment $payment): void
    {
        if ($payment->getInvoiceId()) {
            /** @var Invoice|null $invoice */
            $invoice = $this->entityManager->getEntityById(Invoice::ENTITY_TYPE, $payment->getInvoiceId());
            if ($invoice) {
                $this->invoiceCalc->recalculate($invoice);
                $this->entityManager->saveEntity($invoice, ['skipHooks' => true]);
            }
        }

        if ($payment->getPatientId()) {
            /** @var Patient|null $patient */
            $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $payment->getPatientId());
            if ($patient) {
                $this->balanceCalc->recalculate($patient);
                $this->entityManager->saveEntity($patient, ['skipHooks' => true]);
            }
        }
    }
}
