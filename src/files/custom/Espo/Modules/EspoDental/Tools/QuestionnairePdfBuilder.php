<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Dompdf\Dompdf;
use Dompdf\Options;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;
use Espo\Modules\EspoDental\Entities\HealthQuestionnaire;
use Espo\Modules\EspoDental\Entities\Patient;

class QuestionnairePdfBuilder
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly QuestionnaireSchemaProvider $schemaProvider,
        private readonly FileStorageManager $fileStorageManager
    ) {
    }

    public function build(
        HealthQuestionnaire $questionnaire,
        Patient $patient,
        ?Attachment $signatureAttachment
    ): ?Attachment {
        if (!class_exists(Dompdf::class)) {
            return null;
        }

        $language = $questionnaire->getLanguage();
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($this->buildHtml($questionnaire, $patient, $signatureAttachment, $language), 'UTF-8');
        $pdf->render();

        $binary = $pdf->output();
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

    private function buildHtml(
        HealthQuestionnaire $questionnaire,
        Patient $patient,
        ?Attachment $signatureAttachment,
        string $language
    ): string {
        $schema = $this->schemaProvider->get($language);
        $items = $questionnaire->getItems();
        $fullName = trim(
            (string) $patient->get('lastName') . ' ' .
            (string) $patient->get('firstName') . ' ' .
            (string) $patient->get('middleName')
        );

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>' .
            'body{font-family:"DejaVu Sans",sans-serif;font-size:11px;color:#1f1f1f;}' .
            'h1{font-size:20px;text-align:center;margin:0 0 14px;}' .
            'h2{font-size:13px;margin:0 0 5px;border-bottom:1px solid #999;padding-bottom:2px;}' .
            '.meta{margin-bottom:10px;}' .
            '.answers{width:100%;border-collapse:collapse;margin-top:8px;}' .
            '.answers td{width:50%;vertical-align:top;padding:0 8px 0 0;}' .
            '.group{margin:0 0 10px;page-break-inside:avoid;}' .
            '.item{margin:2px 0;line-height:1.25;border-bottom:1px dotted #ddd;padding-bottom:2px;}' .
            '.answer{font-weight:bold;display:inline-block;min-width:24px;}' .
            '.alert{color:#a00000;font-weight:bold;}' .
            '.signature{margin-top:18px;}' .
            '.signature img{width:260px;max-height:100px;border:1px solid #ccc;padding:8px;}' .
            '</style></head><body>';

        $html .= '<h1>' . $this->html($this->t('Health Questionnaire', $language)) . '</h1>';
        $html .= '<div class="meta"><strong>' . $this->html($this->t('Patient', $language)) . ':</strong> ' .
            $this->html($fullName) . '</div>';
        $html .= '<div class="meta"><strong>' . $this->html($this->t('Filled at', $language)) . ':</strong> ' .
            $this->html((string) $questionnaire->get('filledAt')) . '</div>';

        $columns = $this->buildAnswerColumns($schema, $items, $language, (string) $patient->get('gender'));
        $html .= '<table class="answers"><tr><td>' . $columns[0] . '</td><td>' . $columns[1] . '</td></tr></table>';

        $signatureDataUri = $signatureAttachment ? $this->buildSignatureDataUri($signatureAttachment) : null;
        if ($signatureDataUri) {
            $html .= '<div class="signature"><strong>' . $this->html($this->t('Signature', $language)) .
                ':</strong><br><img src="' . $this->html($signatureDataUri) . '" alt=""></div>';
        }

        return $html . '</body></html>';
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $items
     * @return array{0: string, 1: string}
     */
    private function buildAnswerColumns(array $schema, array $items, string $language, string $patientGender): array
    {
        $blocks = [];
        $totalItems = 0;

        foreach ($schema['groups'] ?? [] as $group) {
            $requiredGender = $group['conditional']['showIf']['patientGender'] ?? null;

            if ($requiredGender && $requiredGender !== $patientGender) {
                continue;
            }

            $blockItemCount = count($group['items'] ?? []);
            $totalItems += $blockItemCount;
            $block = '<div class="group"><h2>' . $this->html((string) ($group['label'] ?? '')) . '</h2>';

            foreach ($group['items'] ?? [] as $item) {
                $id = (string) ($item['id'] ?? '');
                $type = (string) ($item['type'] ?? '');
                $label = (string) ($item['label'] ?? $id);
                $value = $items[$id] ?? null;

                if ($type === 'bool') {
                    $checked = ($value === true || $value === 1 || $value === '1' || $value === 'true');
                    $class = $checked && !empty($item['alert']) ? ' class="item alert"' : ' class="item"';
                    $block .= '<div' . $class . '><span class="answer">' .
                        $this->html($checked ? $this->t('Yes', $language) : $this->t('No', $language)) .
                        '</span> ' . $this->html($label) . '</div>';
                    continue;
                }

                if ($type === 'text') {
                    $text = is_string($value) ? trim($value) : '';
                    $block .= '<div class="item"><strong>' . $this->html($label) . ':</strong> ' .
                        $this->html($text !== '' ? $text : '—') . '</div>';
                }
            }

            $blocks[] = [
                'html' => $block . '</div>',
                'count' => $blockItemCount,
            ];
        }

        $left = '';
        $right = '';
        $leftCount = 0;
        $half = (int) ceil($totalItems / 2);

        foreach ($blocks as $block) {
            if ($leftCount < $half) {
                $left .= $block['html'];
                $leftCount += $block['count'];
                continue;
            }

            $right .= $block['html'];
        }

        return [$left, $right];
    }

    private function buildSignatureDataUri(Attachment $attachment): ?string
    {
        try {
            $contents = $this->fileStorageManager->getContents($attachment);
        } catch (\Throwable) {
            $contents = null;
        }

        if (!$contents) {
            return null;
        }

        $type = (string) ($attachment->get('type') ?: 'image/png');

        return 'data:' . $type . ';base64,' . base64_encode($contents);
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            'Yes' => [
                'ru_RU' => 'Да',
                'en_US' => 'Yes',
                'es_ES' => 'Si',
            ],
            'No' => [
                'ru_RU' => 'Нет',
                'en_US' => 'No',
                'es_ES' => 'No',
            ],
        ];
        return $dict[$key][$language] ?? $dict[$key]['en_US'] ?? $key;
    }
}
