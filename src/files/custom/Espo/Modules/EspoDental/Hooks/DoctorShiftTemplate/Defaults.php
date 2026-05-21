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

        $this->validate($entity);
        $entity->set('name', $this->buildName($entity));
    }

    private function validate(DoctorShiftTemplate $template): void
    {
        if (!array_key_exists($template->getWeekday(), DoctorShiftTemplate::WEEKDAY_TO_ISO)) {
            throw new BadRequest('Invalid weekday');
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
        $doctorName = trim((string) ($template->get('doctorName') ?: 'Doctor'));

        return sprintf(
            '%s - %s %s-%s',
            $doctorName,
            ucfirst($template->getWeekday()),
            $template->getTimeStart(),
            $template->getTimeEnd()
        );
    }
}
