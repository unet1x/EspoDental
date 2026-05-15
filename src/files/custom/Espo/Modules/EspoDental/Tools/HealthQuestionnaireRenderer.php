<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\Utils\File\Manager as FileManager;
use Espo\ORM\Entity;

class HealthQuestionnaireRenderer
{
    private const TEMPLATE_PATH =
        'custom/Espo/Modules/EspoDental/Resources/templates/public/healthQuestionnaire.html.tpl';

    public function __construct(private readonly FileManager $fileManager)
    {
    }

    /**
     * @param array{
     *     language: string,
     *     token: string,
     *     schema: array<string, mixed>,
     *     patient: ?Entity,
     *     error: ?string,
     *     submitUrl: string
     * } $vars
     */
    public function render(array $vars): string
    {
        $template = (string) $this->fileManager->getContents(self::TEMPLATE_PATH);
        if ($template === '') {
            return '<!doctype html><meta charset="utf-8"><pre>Template missing</pre>';
        }

        $strings = $this->getStrings($vars['language']);

        $patient = $vars['patient'];
        $patientFullName = '';
        if ($patient) {
            $patientFullName = trim(
                (string) $patient->get('lastName') . ' ' .
                (string) $patient->get('firstName') . ' ' .
                (string) $patient->get('middleName')
            );
        }
        $patientGender = $patient ? (string) $patient->get('gender') : '';

        $bootstrap = [
            'language' => $vars['language'],
            'token' => $vars['token'],
            'schema' => $vars['schema'],
            'submitUrl' => $vars['submitUrl'],
            'patientFullName' => $patientFullName,
            'patientGender' => $patientGender,
            'strings' => $strings,
            'error' => $vars['error'],
        ];

        $replacements = [
            '{{lang}}' => $this->htmlAttr(substr($vars['language'], 0, 2)),
            '{{title}}' => $this->html($strings['title']),
            '{{patientFullName}}' => $this->html($patientFullName),
            '{{bootstrapJson}}' => $this->jsonForScript($bootstrap),
            '{{errorBanner}}' => $vars['error']
                ? '<div class="alert">' . $this->html($strings['errorPrefix'] . ': ' . $vars['error']) . '</div>'
                : '',
        ];

        return strtr($template, $replacements);
    }

    /**
     * @return array<string, string>
     */
    private function getStrings(string $language): array
    {
        $dict = [
            'ru_RU' => [
                'title' => 'Анкета здоровья',
                'subtitle' => 'Пожалуйста, заполните анкету перед приёмом',
                'patientLabel' => 'Пациент',
                'yes' => 'Да',
                'no' => 'Нет',
                'signaturePrompt' => 'Подпишите ниже пальцем',
                'clear' => 'Очистить',
                'submit' => 'Готово',
                'thankYou' => 'Спасибо! Анкета сохранена.',
                'errorPrefix' => 'Ошибка',
                'submitting' => 'Сохранение...',
                'signatureRequired' => 'Пожалуйста, поставьте подпись',
                'submitFailed' => 'Не удалось отправить. Проверьте соединение и попробуйте снова.',
                'allRequired' => 'Ответьте Да/Нет на все вопросы анкеты',
            ],
            'en_US' => [
                'title' => 'Health Questionnaire',
                'subtitle' => 'Please fill the questionnaire before your appointment',
                'patientLabel' => 'Patient',
                'yes' => 'Yes',
                'no' => 'No',
                'signaturePrompt' => 'Sign below with your finger',
                'clear' => 'Clear',
                'submit' => 'Done',
                'thankYou' => 'Thank you! Your questionnaire has been saved.',
                'errorPrefix' => 'Error',
                'submitting' => 'Saving...',
                'signatureRequired' => 'Please add your signature',
                'submitFailed' => 'Failed to submit. Check the connection and try again.',
                'allRequired' => 'Please answer Yes/No for every questionnaire item',
            ],
            'es_ES' => [
                'title' => 'Cuestionario de salud',
                'subtitle' => 'Por favor complete el cuestionario antes de su cita',
                'patientLabel' => 'Paciente',
                'yes' => 'Si',
                'no' => 'No',
                'signaturePrompt' => 'Firme abajo con el dedo',
                'clear' => 'Borrar',
                'submit' => 'Listo',
                'thankYou' => 'Gracias. El cuestionario se ha guardado.',
                'errorPrefix' => 'Error',
                'submitting' => 'Guardando...',
                'signatureRequired' => 'Por favor agregue su firma',
                'submitFailed' => 'No se pudo enviar. Revise la conexion y vuelva a intentar.',
                'allRequired' => 'Responda Si/No en todos los puntos del cuestionario',
            ],
        ];
        return $dict[$language] ?? $dict['en_US'];
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function htmlAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Safely embed JSON inside a script tag (escapes </ and HTML control chars).
     */
    private function jsonForScript(mixed $value): string
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            return '{}';
        }
        return str_replace(
            ['<', '>', '&', "\u{2028}", "\u{2029}"],
            ['\\u003C', '\\u003E', '\\u0026', '\\u2028', '\\u2029'],
            $json
        );
    }
}
