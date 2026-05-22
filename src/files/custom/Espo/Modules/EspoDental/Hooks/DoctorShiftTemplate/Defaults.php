<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\DoctorShiftTemplate;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EspoDental\Entities\DoctorShift;
use Espo\Modules\EspoDental\Entities\DoctorShiftTemplate;
use Espo\ORM\Entity;

class Defaults
{
    public static int $order = 1;

    public function beforeSave(Entity $entity): void
    {
        if (!$entity instanceof DoctorShiftTemplate) {
            return;
        }

        if (!$entity->get('status')) {
            $entity->set('status', DoctorShiftTemplate::STATUS_ACTIVE);
        }

        if (!$entity->get('type')) {
            $entity->set('type', DoctorShift::TYPE_REGULAR);
        }

        $weekdays = $this->normalizeWeekdays($entity);
        $entity->set('weekdays', $weekdays);
        $entity->set('weekday', $weekdays[0]);

        $this->validate($entity);
        $entity->set('name', $this->buildName($entity));
    }

    private function validate(DoctorShiftTemplate $template): void
    {
        $weekdays = $template->getWeekdays();

        if ($weekdays === []) {
            throw new BadRequest('At least one weekday is required');
        }

        foreach ($weekdays as $weekday) {
            if (!array_key_exists($weekday, DoctorShiftTemplate::WEEKDAY_TO_ISO)) {
                throw new BadRequest('Invalid weekday');
            }
        }

        if (!$template->getDoctorId() && $template->getType() !== DoctorShift::TYPE_CLOSED) {
            throw new BadRequest('doctor is required for working shift templates');
        }

        if (
            $template->getType() === DoctorShift::TYPE_CLOSED
            && !$template->getDoctorId()
            && !$template->getCabinetId()
        ) {
            throw new BadRequest('doctor or cabinet is required for closed shift templates');
        }

        $this->validateTime($template->getTimeStart(), 'timeStart');
        $this->validateTime($template->getTimeEnd(), 'timeEnd');

        if ($template->getTimeEnd() <= $template->getTimeStart()) {
            throw new BadRequest('timeEnd must be after timeStart');
        }

        $from = new DateTimeImmutable($template->getDateStart());
        $to = new DateTimeImmutable($template->getDateEnd());

        if ($to < $from) {
            throw new BadRequest('dateEnd must be on/after dateStart');
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeWeekdays(DoctorShiftTemplate $template): array
    {
        $weekdays = $template->getWeekdays();

        if ($weekdays === []) {
            $weekday = $template->getWeekday();
            $weekdays = $weekday !== '' ? [$weekday] : [DoctorShiftTemplate::WEEKDAY_MONDAY];
        }

        return $weekdays;
    }

    private function validateTime(string $value, string $field): void
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
            throw new BadRequest($field . ' must be HH:MM');
        }

        [$hour, $minute] = array_map('intval', explode(':', $value));

        if ($hour > 23 || $minute > 59) {
            throw new BadRequest($field . ' must be a valid time');
        }
    }

    private function buildName(DoctorShiftTemplate $template): string
    {
        $doctorName = trim((string) ($template->get('doctorName') ?: ''));
        $cabinetName = trim((string) ($template->get('cabinetName') ?: ''));
        $subject = $doctorName !== '' ? $doctorName : ($cabinetName !== '' ? $cabinetName : 'Schedule');
        $weekdays = implode(', ', array_map('ucfirst', $template->getWeekdays()));

        return sprintf(
            '%s - %s %s-%s',
            $subject,
            $weekdays,
            $template->getTimeStart(),
            $template->getTimeEnd()
        );
    }
}
