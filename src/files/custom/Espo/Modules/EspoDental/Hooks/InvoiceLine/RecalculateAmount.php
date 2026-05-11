<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\InvoiceLine;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\InvoiceLine;
use Espo\Modules\EspoDental\Tools\InvoiceCalculator;
use Espo\ORM\Entity;

class RecalculateAmount
{
    public static int $order = 5;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InvoiceCalculator $calculator
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof InvoiceLine) {
            return;
        }

        $qty = max(1, $entity->getQuantity());
        $unit = $entity->getUnitPrice();
        $discount = max(0.0, min(100.0, $entity->getDiscount()));
        $vatRate = max(0.0, min(100.0, $entity->getVatRate()));

        $gross = $qty * $unit;
        $afterDiscount = $gross * (1 - $discount / 100.0);
        $vat = $afterDiscount * ($vatRate / 100.0);

        $entity->set('amount', round($afterDiscount, 2));
        $entity->set('vatAmount', round($vat, 2));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof InvoiceLine) {
            return;
        }
        if (!empty($options['skipInvoiceRecalc'])) {
            return;
        }
        $invoiceId = $entity->getInvoiceId();
        if (!$invoiceId) {
            return;
        }
        /** @var Invoice|null $invoice */
        $invoice = $this->entityManager->getEntityById(Invoice::ENTITY_TYPE, $invoiceId);
        if ($invoice) {
            $this->calculator->recalculate($invoice);
            $this->entityManager->saveEntity($invoice, ['skipHooks' => true]);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterRemove(Entity $entity, array $options = []): void
    {
        $this->afterSave($entity, $options);
    }
}
