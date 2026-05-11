<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\FileStorage\AttachmentFileStorage;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;
use Espo\Modules\EspoDental\Entities\HealthQuestionnaire;
use Espo\Modules\EspoDental\Entities\Patient;
use TCPDF;

class QuestionnairePdfBuilder
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly QuestionnaireSchemaProvider $schemaProvider,
        private readonly AttachmentFileStorage $attachmentFileStorage
    ) {
    }

    public function build(
        HealthQuestionnaire $questionnaire,
        Patient $patient,
        ?Attachment $signatureAttachment
    ): ?Attachment {
        if (!class_exists(TCPDF::class)) {
            return null;
        }

        $language = $questionnaire->getLanguage();
        $schema = $this->schemaProvider->get($language);
        $items = $questionnaire->getItems();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('EspoDental');
        $pdf->SetAuthor('EspoDental');
        $pdf->SetTitle($this->t('Health Questionnaire', $language));
        $pdf->SetMargins(15, 18, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, $this->t('Health Questionnaire', $language), 0, 1, 'C');

        $pdf->SetFont('dejavusans', '', 11);
        $fullName = trim(
            (string) $patient->get('lastName') . ' ' .
            (string) $patient->get('firstName') . ' ' .
            (string) $patient->get('middleName')
        );
        $pdf->Cell(
            0,
            6,
            $this->t('Patient', $language) . ': ' . $fullName,
            0,
            1
        );
        $pdf->Cell(0, 6, $this->t('Filled at', $language) . ': ' . (string) $questionnaire->get('filledAt'), 0, 1);
        $pdf->Ln(3);

        foreach ($schema['groups'] as $group) {
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->Cell(0, 7, (string) $group['label'], 0, 1);
            $pdf->SetFont('dejavusans', '', 11);

            foreach ($group['items'] as $item) {
                $id = $item['id'];
                $type = $item['type'];
                $label = (string) $item['label'];
                $value = $items[$id] ?? null;

                if ($type === 'bool') {
                    $checked = ($value === true || $value === 1 || $value === '1' || $value === 'true');
                    $mark = $checked ? '[X]' : '[ ]';
                    $isAlert = $checked && !empty($item['alert']);
                    if ($isAlert) {
                        $pdf->SetTextColor(180, 0, 0);
                    }
                    $pdf->MultiCell(0, 5.5, $mark . ' ' . $label, 0, 'L');
                    if ($isAlert) {
                        $pdf->SetTextColor(0, 0, 0);
                    }
                } elseif ($type === 'text') {
                    $text = is_string($value) ? trim($value) : '';
                    $pdf->MultiCell(0, 5.5, $label . ': ' . ($text !== '' ? $text : '—'), 0, 'L');
                }
            }

            $pdf->Ln(2);
        }

        if ($signatureAttachment) {
            $this->renderSignature($pdf, $signatureAttachment, $language);
        }

        $binary = $pdf->Output('questionnaire.pdf', 'S');
        if (!is_string($binary) || $binary === '') {
            return null;
        }

        /** @var Attachment $pdfAttachment */
        $pdfAttachment = $this->entityManager->getNewEntity(Attachment::ENTITY_TYPE);
        $pdfAttachment->set('name', 'questionnaire-' . substr((string) $questionnaire->getId(), 0, 8) . '.pdf');
        $pdfAttachment->set('type', 'application/pdf');
        $pdfAttachment->set('role', 'Attachment');
        $pdfAttachment->set('relatedType', HealthQuestionnaire::ENTITY_TYPE);
        $pdfAttachment->set('relatedId', $questionnaire->getId());
        $pdfAttachment->set('contents', $binary);

        $this->entityManager->saveEntity($pdfAttachment);

        return $pdfAttachment;
    }

    private function renderSignature(TCPDF $pdf, Attachment $attachment, string $language): void
    {
        try {
            $contents = $this->attachmentFileStorage->read(
                new \Espo\Core\FileStorage\Manager\Data($attachment)
            );
        } catch (\Throwable) {
            $contents = null;
        }

        if (!$contents) {
            return;
        }

        $pdf->Ln(6);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 6, $this->t('Signature', $language) . ':', 0, 1);

        $tmp = tempnam(sys_get_temp_dir(), 'espodental_sig_');
        if ($tmp === false) {
            return;
        }
        file_put_contents($tmp, $contents);
        try {
            $pdf->Image($tmp, 15, $pdf->GetY(), 80, 30, '', '', '', false, 300, '', false, false, 0);
            $pdf->Ln(34);
        } finally {
            @unlink($tmp);
        }
    }

    private function t(string $key, string $language): string
    {
        $dict = [
            'Health Questionnaire' => [
                'ru_RU' => 'Анкета здоровья',
                'en_US' => 'Health Questionnaire',
                'es_ES' => 'Cuestionario de salud',
            ],
            'Patient' => [
                'ru_RU' => 'Пациент',
                'en_US' => 'Patient',
                'es_ES' => 'Paciente',
            ],
            'Filled at' => [
                'ru_RU' => 'Дата заполнения',
                'en_US' => 'Filled at',
                'es_ES' => 'Completado el',
            ],
            'Signature' => [
                'ru_RU' => 'Подпись',
                'en_US' => 'Signature',
                'es_ES' => 'Firma',
            ],
        ];
        return $dict[$key][$language] ?? $dict[$key]['en_US'] ?? $key;
    }
}
