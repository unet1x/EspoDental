<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\DoctorShift;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Entities\DoctorShift;
use Espo\ORM\Entity;

class Defaults
{
    public static int $order = 1;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    public function beforeSave(Entity $entity): void
    {
        if (!$entity instanceof DoctorShift) {
            return;
        }

        if (!$entity->get('status')) {
            $entity->set('status', DoctorShift::STATUS_ACTIVE);
        }

        if (!$entity->get('type')) {
            $entity->set('type', DoctorShift::TYPE_REGULAR);
        }

        $this->assertDateOrder($entity);
        $entity->set('name', $this->buildName($entity));
    }

    private function assertDateOrder(DoctorShift $shift): void
    {
        $start = (string) $shift->getDateStart();
        $end = (string) $shift->getDateEnd();

        if ($start === '' || $end === '') {
            return;
        }

        try {
            $startDt = new DateTimeImmutable($start, new DateTimeZone('UTC'));
            $endDt = new DateTimeImmutable($end, new DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new BadRequest('Invalid shift datetime');
        }

        if ($endDt <= $startDt) {
            throw new BadRequest('dateEnd must be after dateStart');
        }
    }

    private function buildName(DoctorShift $shift): string
    {
        $doctorName = trim((string) ($shift->get('doctorName') ?: 'Doctor'));
        $type = $this->translateType($shift->getType());
        $dateStart = (string) $shift->getDateStart();

        if ($dateStart === '') {
            return $doctorName . ' - ' . $type;
        }

        try {
            $date = (new DateTimeImmutable($dateStart, new DateTimeZone('UTC')))
                ->setTimezone($this->resolveTimeZone($shift->getClinicId()))
                ->format('Y-m-d H:i');
        } catch (\Exception) {
            $date = substr($dateStart, 0, 16);
        }

        return $doctorName . ' - ' . $date . ' - ' . $type;
    }

    private function translateType(string $type): string
    {
        return match ($type) {
            DoctorShift::TYPE_ADDITIONAL => 'Additional',
            DoctorShift::TYPE_CLOSED => 'Closed',
            default => 'Regular',
        };
    }

    private function resolveTimeZone(?string $clinicId = null): DateTimeZone
    {
        if ($clinicId) {
            /** @var Clinic|null $clinic */
            $clinic = $this->entityManager->getEntityById(Clinic::ENTITY_TYPE, $clinicId);

            if ($clinic) {
                $timeZone = (string) ($clinic->get('timezone') ?: '');

                if ($timeZone !== '') {
                    return $this->buildTimeZone($timeZone);
                }
            }
        }

        return $this->buildTimeZone((string) ($this->config->get('timeZone') ?: 'UTC'));
    }

    private function buildTimeZone(string $timeZone): DateTimeZone
    {
        try {
            return new DateTimeZone($timeZone);
        } catch (\Exception) {
            return new DateTimeZone('UTC');
        }
    }
}
