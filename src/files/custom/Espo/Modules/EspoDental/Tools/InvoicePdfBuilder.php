<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\Acl;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Language;
use Espo\Entities\Attachment;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\InvoiceLine;
use Espo\Modules\EspoDental\Entities\Patient;
use TCPDF;

class InvoicePdfBuilder
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FileStorageManager $fileStorageManager,
        private readonly Language $language,
        private readonly Acl $acl
    ) {
    }

    public function buildAttachment(Invoice $invoice): Attachment
    {
        if (!$this->acl->checkScope('Invoice', 'read')) {
            throw new \RuntimeException('No access to invoice');
        }

        /** @var Patient|null $patient */
        $patient = $invoice->get('patientId')
            ? $this->entityManager->getEntityById(Patient::ENTITY_TYPE, (string) $invoice->get('patientId'))
            : null;

        /** @var Clinic|null $clinic */
        $clinic = $invoice->get('clinicId')
            ? $this->entityManager->getEntityById(Clinic::ENTITY_TYPE, (string) $invoice->get('clinicId'))
            : null;

        $bytes = $this->renderBytes($invoice, $patient, $clinic);

        $attachment = $this->entityManager->getRDBRepository(Attachment::ENTITY_TYPE)->getNew();
        $attachment->set('name', 'Invoice-' . (string) $invoice->get('number') . '.pdf');
        $attachment->set('type', 'application/pdf');
        $attachment->set('role', 'Attachment');
        $attachment->set('size', strlen($bytes));
        $attachment->set('relatedType', Invoice::ENTITY_TYPE);
        $attachment->set('relatedId', $invoice->getId());
        $this->entityManager->saveEntity($attachment);
        $this->fileStorageManager->putContents($attachment, $bytes);

        return $attachment;
    }

    private function renderBytes(Invoice $invoice, ?Patient $patient, ?Clinic $clinic): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('EspoDental');
        $pdf->SetTitle('Invoice ' . (string) $invoice->get('number'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, $this->t('Invoice') . ' ' . (string) $invoice->get('number'), 0, 1, 'C');

        $pdf->SetFont('dejavusans', '', 10);
        if ($clinic) {
            $pdf->Cell(0, 6, (string) $clinic->get('name'), 0, 1);
            $address = (string) $clinic->get('address');
            if ($address !== '') {
                $pdf->Cell(0, 5, $address, 0, 1);
            }
        }

        $pdf->Ln(3);
        if ($patient) {
            $name = trim(
                (string) $patient->get('lastName') . ' ' .
                (string) $patient->get('firstName') . ' ' .
                (string) $patient->get('middleName')
            );
            $pdf->Cell(0, 6, $this->t('Patient') . ': ' . $name, 0, 1);
        }
        $pdf->Cell(0, 6, $this->t('Issued at') . ': ' . (string) $invoice->get('issuedAt'), 0, 1);

        $pdf->Ln(3);
        $this->renderLinesTable($pdf, $invoice);

        $pdf->Ln(4);
        $this->renderTotals($pdf, $invoice);

        $notes = (string) $invoice->get('notes');
        if ($notes !== '') {
            $pdf->Ln(4);
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->MultiCell(0, 5, $notes, 0, 'L');
        }

        return (string) $pdf->Output('', 'S');
    }

    private function renderLinesTable(TCPDF $pdf, Invoice $invoice): void
    {
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(80, 6, $this->t('Service'), 1, 0, 'L', true);
        $pdf->Cell(20, 6, $this->t('Teeth'), 1, 0, 'C', true);
        $pdf->Cell(15, 6, $this->t('Qty'), 1, 0, 'C', true);
        $pdf->Cell(25, 6, $this->t('Unit'), 1, 0, 'R', true);
        $pdf->Cell(15, 6, $this->t('Disc'), 1, 0, 'R', true);
        $pdf->Cell(25, 6, $this->t('Amount'), 1, 1, 'R', true);

        $pdf->SetFont('dejavusans', '', 9);
        /** @var iterable<InvoiceLine> $lines */
        $lines = $this->entityManager
            ->getRDBRepository(InvoiceLine::ENTITY_TYPE)
            ->where(['invoiceId' => $invoice->getId()])
            ->find();

        foreach ($lines as $line) {
            $pdf->Cell(80, 6, (string) $line->get('name'), 1);
            $pdf->Cell(20, 6, (string) $line->get('teethNumbers'), 1, 0, 'C');
            $pdf->Cell(15, 6, (string) $line->getQuantity(), 1, 0, 'C');
            $pdf->Cell(25, 6, number_format($line->getUnitPrice(), 2), 1, 0, 'R');
            $pdf->Cell(15, 6, number_format($line->getDiscount(), 0) . '%', 1, 0, 'R');
            $pdf->Cell(25, 6, number_format($line->getAmount(), 2), 1, 1, 'R');
        }
    }

    private function renderTotals(TCPDF $pdf, Invoice $invoice): void
    {
        $pdf->SetFont('dejavusans', '', 10);
        $row = function (string $label, float $value) use ($pdf): void {
            $pdf->Cell(155, 6, $label, 0, 0, 'R');
            $pdf->Cell(25, 6, number_format($value, 2), 0, 1, 'R');
        };
        $row($this->t('Subtotal') . ':', (float) $invoice->get('subtotal'));
        $row($this->t('Discount') . ':', (float) $invoice->get('discountAmount'));
        $row($this->t('VAT') . ':', (float) $invoice->get('vatAmount'));
        $pdf->SetFont('dejavusans', 'B', 11);
        $row($this->t('Total') . ':', $invoice->getTotalAmount());
        $pdf->SetFont('dejavusans', '', 10);
        $row($this->t('Paid') . ':', $invoice->getPaidAmount());
        $pdf->SetFont('dejavusans', 'B', 10);
        $row($this->t('Balance') . ':', $invoice->getBalance());
    }

    private function t(string $key): string
    {
        $value = $this->language->translateLabel($key, 'labels', 'Invoice');
        return $value ?: $key;
    }
}
