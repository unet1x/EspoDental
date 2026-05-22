<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class DoctorShiftTemplate extends Entity
{
    public const ENTITY_TYPE = 'DoctorShiftTemplate';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';

    public const WEEKDAY_MONDAY = 'monday';
    public const WEEKDAY_TUESDAY = 'tuesday';
    public const WEEKDAY_WEDNESDAY = 'wednesday';
    public const WEEKDAY_THURSDAY = 'thursday';
    public const WEEKDAY_FRIDAY = 'friday';
    public const WEEKDAY_SATURDAY = 'saturday';
    public const WEEKDAY_SUNDAY = 'sunday';

    public const WEEKDAY_TO_ISO = [
        self::WEEKDAY_MONDAY => 1,
        self::WEEKDAY_TUESDAY => 2,
        self::WEEKDAY_WEDNESDAY => 3,
        self::WEEKDAY_THURSDAY => 4,
        self::WEEKDAY_FRIDAY => 5,
        self::WEEKDAY_SATURDAY => 6,
        self::WEEKDAY_SUNDAY => 7,
    ];

    public const WEEKDAYS = [
        self::WEEKDAY_MONDAY,
        self::WEEKDAY_TUESDAY,
        self::WEEKDAY_WEDNESDAY,
        self::WEEKDAY_THURSDAY,
        self::WEEKDAY_FRIDAY,
        self::WEEKDAY_SATURDAY,
        self::WEEKDAY_SUNDAY,
    ];

    public function getDoctorId(): ?string
    {
        return $this->get('doctorId');
    }

    public function getAssistantId(): ?string
    {
        return $this->get('assistantId');
    }

    public function getClinicId(): ?string
    {
        return $this->get('clinicId');
    }

    public function getCabinetId(): ?string
    {
        return $this->get('cabinetId');
    }

    public function getWeekday(): string
    {
        return (string) $this->get('weekday');
    }

    /**
     * @return list<string>
     */
    public function getWeekdays(): array
    {
        $value = $this->get('weekdays');
        $days = [];

        if (is_array($value)) {
            $days = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $days = $decoded;
            } else {
                $days = explode(',', $value);
            }
        }

        $days = array_values(array_unique(array_filter(array_map(
            static fn ($day): string => trim((string) $day),
            $days
        ))));

        if ($days === [] && $this->getWeekday() !== '') {
            $days = [$this->getWeekday()];
        }

        return array_values(array_filter(
            $days,
            static fn (string $day): bool => in_array($day, self::WEEKDAYS, true)
        ));
    }

    public function getTimeStart(): string
    {
        return (string) $this->get('timeStart');
    }

    public function getTimeEnd(): string
    {
        return (string) $this->get('timeEnd');
    }

    public function getDateStart(): string
    {
        return (string) $this->get('dateStart');
    }

    public function getDateEnd(): string
    {
        return (string) $this->get('dateEnd');
    }

    public function getType(): string
    {
        return (string) ($this->get('type') ?: DoctorShift::TYPE_REGULAR);
    }

    public function getStatus(): string
    {
        return (string) ($this->get('status') ?: self::STATUS_ACTIVE);
    }
}
