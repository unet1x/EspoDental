<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\InvoiceLine;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;
use Espo\Modules\EspoDental\Tools\InvoiceCalculator;
use Espo\Modules\EspoDental\Tools\InvoicePdfBuilder;
use Espo\Modules\EspoDental\Tools\PatientBalanceCalculator;

class InvoiceService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InvoiceCalculator $calculator,
        private readonly InvoicePdfBuilder $pdfBuilder,
        private readonly PatientBalanceCalculator $patientBalanceCalculator
    ) {
    }

    /**
     * Creates a draft Invoice from a finished/in-progress Visit
     * and copies all VisitServiceLine items as InvoiceLine.
     */
    public function buildFromVisit(Visit $visit): Invoice
    {
        $existing = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where([
                'visitId' => $visit->getId(),
                'status!=' => [Invoice::STATUS_STORNO, Invoice::STATUS_CANCELLED],
            ])
            ->findOne();

        if ($existing) {
            $this->calculator->recalculate($existing);
            $this->entityManager->saveEntity($existing, ['skipHooks' => true]);
            $this->refreshPatientBalance($existing->getPatientId());
            return $existing;
        }

        /** @var Invoice $invoice */
        $invoice = $this->entityManager->getNewEntity(Invoice::ENTITY_TYPE);
        $invoice->set('patientId', $visit->getPatientId());
        $invoice->set('clinicId', $visit->get('clinicId'));
        $invoice->set('visitId', $visit->getId());
        $invoice->set('status', Invoice::STATUS_DRAFT);
        $invoice->set('issuedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->entityManager->saveEntity($invoice, ['espodentalAllowInvoiceCreate' => true]);

        /** @var iterable<VisitServiceLine> $sourceLines */
        $sourceLines = $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->find();

        foreach ($sourceLines as $src) {
            /** @var InvoiceLine $line */
            $line = $this->entityManager->getNewEntity(InvoiceLine::ENTITY_TYPE);
            $line->set('invoiceId', $invoice->getId());
            $line->set('name', (string) $src->get('name'));
            $line->set('serviceId', $src->get('serviceId'));
            $line->set('doctorId', $src->get('doctorId'));
            $line->set('teethNumbers', (string) $src->get('teethNumbers'));
            $line->set('quantity', $src->getQuantity());
            $line->set('unitPrice', $src->getUnitPrice());
            $line->set('discount', $src->getDiscount());
            $line->set('vatRate', $src->getVatRate());
            $line->set('sourceVisitServiceLineId', $src->getId());
            $line->set('notes', (string) $src->get('notes'));
            $this->entityManager->saveEntity($line, ['skipInvoiceRecalc' => true]);
        }

        $this->calculator->recalculate($invoice);
        $this->entityManager->saveEntity($invoice, ['skipHooks' => true]);
        $this->refreshPatientBalance($invoice->getPatientId());

        return $invoice;
    }

    /**
     * @return array{invoiceId: string, number: string, total: float}
     */
    public function issue(string $invoiceId): array
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->entityManager->getEntityById(Invoice::ENTITY_TYPE, $invoiceId);
        if (!$invoice) {
            throw new NotFound('Invoice not found');
        }
        if ($invoice->getStatus() !== Invoice::STATUS_DRAFT) {
            throw new Conflict('Only draft invoices can be issued');
        }
        if ($invoice->getTotalAmount() <= 0) {
            throw new BadRequest('Invoice has zero amount, add at least one line');
        }

        $this->calculator->recalculate($invoice);
        $invoice->set('status', Invoice::STATUS_ISSUED);
        if (!$invoice->get('issuedAt')) {
            $invoice->set('issuedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        }
        $this->entityManager->saveEntity($invoice);
        $this->refreshPatientBalance($invoice->getPatientId());

        return [
            'invoiceId' => (string) $invoice->getId(),
            'number' => (string) $invoice->get('number'),
            'total' => $invoice->getTotalAmount(),
        ];
    }

    /**
     * @return array{stornoInvoiceId: string}
     */
    public function storno(string $invoiceId, string $reason = ''): array
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->entityManager->getEntityById(Invoice::ENTITY_TYPE, $invoiceId);
        if (!$invoice) {
            throw new NotFound('Invoice not found');
        }
        if (
            in_array($invoice->getStatus(), [
            Invoice::STATUS_STORNO,
            Invoice::STATUS_CANCELLED,
            Invoice::STATUS_DRAFT,
            ], true)
        ) {
            throw new Conflict('Invoice cannot be storno-ed from current status');
        }

        /** @var Invoice $storno */
        $storno = $this->entityManager->getNewEntity(Invoice::ENTITY_TYPE);
        $storno->set('patientId', $invoice->getPatientId());
        $storno->set('clinicId', $invoice->get('clinicId'));
        $storno->set('visitId', $invoice->get('visitId'));
        $storno->set('status', Invoice::STATUS_STORNO);
        $storno->set('issuedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $storno->set('stornoOfId', $invoice->getId());
        $storno->set('notes', trim('Storno of #' . (string) $invoice->get('number') . "\n" . $reason));
        $storno->set('subtotal', -$invoice->get('subtotal'));
        $storno->set('discountAmount', -$invoice->get('discountAmount'));
        $storno->set('vatAmount', -$invoice->get('vatAmount'));
        $storno->set('totalAmount', -$invoice->getTotalAmount());
        $storno->set('balance', -$invoice->getBalance());
        $this->entityManager->saveEntity($storno, ['espodentalAllowInvoiceCreate' => true]);

        $invoice->set('status', Invoice::STATUS_STORNO);
        $this->entityManager->saveEntity($invoice, ['skipHooks' => true]);
        $this->refreshPatientBalance($invoice->getPatientId());

        return ['stornoInvoiceId' => (string) $storno->getId()];
    }

    /**
     * @return array{attachmentId: string, name: string}
     */
    public function buildPdf(string $invoiceId): array
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->entityManager->getEntityById(Invoice::ENTITY_TYPE, $invoiceId);
        if (!$invoice) {
            throw new NotFound('Invoice not found');
        }
        $attachment = $this->pdfBuilder->buildAttachment($invoice);
        return [
            'attachmentId' => (string) $attachment->getId(),
            'name' => (string) $attachment->get('name'),
        ];
    }

    private function refreshPatientBalance(?string $patientId): void
    {
        if (!$patientId) {
            return;
        }

        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $patientId);
        if (!$patient) {
            return;
        }

        $this->patientBalanceCalculator->recalculate($patient);
        $this->entityManager->saveEntity($patient, ['skipHooks' => true]);
    }
}
