<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\ORM\Entity;

class ReminderTemplate
{
    /**
     * @return array{subject: string, text: string, html: string}
     */
    public function build(Appointment $appointment, ?Entity $parent, string $language = 'ru_RU'): array
    {
        $patientName = $this->parentDisplayName($parent);
        $cabinet = (string) $appointment->get('cabinetName');
        $clinic = (string) $appointment->get('clinicName');
        $doctor = (string) $appointment->get('doctorName');
        $dateStart = (string) $appointment->getDateStart();

        $when = $this->formatWhen($dateStart, $language);

        return match ($language) {
            'en_US' => $this->buildEn($patientName, $when, $doctor, $clinic, $cabinet),
            'es_ES' => $this->buildEs($patientName, $when, $doctor, $clinic, $cabinet),
            default => $this->buildRu($patientName, $when, $doctor, $clinic, $cabinet),
        };
    }

    private function parentDisplayName(?Entity $parent): string
    {
        if (!$parent) {
            return '';
        }
        if ($parent instanceof Patient) {
            return trim(
                (string) $parent->get('firstName') . ' ' .
                (string) $parent->get('middleName')
            );
        }
        return trim(
            (string) $parent->get('firstName') . ' ' .
            (string) $parent->get('middleName')
        );
    }

    private function formatWhen(string $dateStart, string $language): string
    {
        if ($dateStart === '') {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($dateStart);
        } catch (\Exception) {
            return $dateStart;
        }
        return $dt->format('d.m.Y H:i');
    }

    /**
     * @return array{subject: string, text: string, html: string}
     */
    private function buildRu(string $name, string $when, string $doctor, string $clinic, string $cabinet): array
    {
        $hi = $name !== '' ? "Здравствуйте, {$name}!" : 'Здравствуйте!';
        $text = "{$hi}\nНапоминаем о приёме {$when}\nВрач: {$doctor}\nКлиника: {$clinic}\nКабинет: {$cabinet}\n\n"
            . 'Если планы изменились, пожалуйста, отмените запись заранее.';
        $html = nl2br(htmlspecialchars($text));
        return ['subject' => 'Напоминание о приёме ' . $when, 'text' => $text, 'html' => $html];
    }

    /**
     * @return array{subject: string, text: string, html: string}
     */
    private function buildEn(string $name, string $when, string $doctor, string $clinic, string $cabinet): array
    {
        $hi = $name !== '' ? "Hello, {$name}!" : 'Hello!';
        $text = "{$hi}\nThis is a reminder of your appointment on {$when}.\n"
            . "Doctor: {$doctor}\nClinic: {$clinic}\nCabinet: {$cabinet}\n\n"
            . 'Please contact us in advance if you need to reschedule.';
        $html = nl2br(htmlspecialchars($text));
        return ['subject' => 'Appointment reminder ' . $when, 'text' => $text, 'html' => $html];
    }

    /**
     * @return array{subject: string, text: string, html: string}
     */
    private function buildEs(string $name, string $when, string $doctor, string $clinic, string $cabinet): array
    {
        $hi = $name !== '' ? "Hola, {$name}!" : 'Hola!';
        $text = "{$hi}\nLe recordamos su cita {$when}.\n"
            . "Doctor: {$doctor}\nClinica: {$clinic}\nGabinete: {$cabinet}\n\n"
            . 'Por favor, avisenos con antelacion si necesita reprogramar.';
        $html = nl2br(htmlspecialchars($text));
        return ['subject' => 'Recordatorio de cita ' . $when, 'text' => $text, 'html' => $html];
    }
}
