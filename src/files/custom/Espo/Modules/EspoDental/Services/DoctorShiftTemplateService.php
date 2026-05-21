<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Entities\DoctorShift;
use Espo\Modules\EspoDental\Entities\DoctorShiftTemplate;

class DoctorShiftTemplateService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{templateId: string, created: int, skipped: int}
     */
    public function generate(string $templateId): array
    {
        /** @var DoctorShiftTemplate|null $template */
        $template = $this->entityManager->getEntityById(DoctorShiftTemplate::ENTITY_TYPE, $templateId);

        if (!$template) {
            throw new NotFound('Doctor shift template not found');
        }

        if ($template->getStatus() !== DoctorShiftTemplate::STATUS_ACTIVE) {
            throw new Conflict('Only active shift templates can generate shifts');
        }

        $this->validateTemplate($template);

        $timeZone = $this->resolveTimeZone($template->getClinicId());
        $from = new DateTimeImmutable($template->getDateStart() . ' 00:00:00', $timeZone);
        $to = new DateTimeImmutable($template->getDateEnd() . ' 00:00:00', $timeZone);
        $targetWeekday = DoctorShiftTemplate::WEEKDAY_TO_ISO[$template->getWeekday()];
        $created = 0;
        $skipped = 0;

        for ($day = $from; $day <= $to; $day = $day->add(new DateInterval('P1D'))) {
            if ((int) $day->format('N') !== $targetWeekday) {
                continue;
            }

            [$startUtc, $endUtc] = $this->buildUtcDateTimes($day, $template, $timeZone);

            if ($this->findExistingShift($template, $startUtc, $endUtc)) {
                $skipped++;
                continue;
            }

            $this->createShift($template, $startUtc, $endUtc);
            $created++;
        }

        $template->set('lastGeneratedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->entityManager->saveEntity($template);

        return [
            'templateId' => (string) $template->getId(),
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    private function validateTemplate(DoctorShiftTemplate $template): void
    {
        if (!array_key_exists($template->getWeekday(), DoctorShiftTemplate::WEEKDAY_TO_ISO)) {
            throw new BadRequest('Invalid weekday');
        }

        if (!$template->getDoctorId() || !$template->getClinicId()) {
            throw new BadRequest('doctor and clinic are required');
        }

        $this->validateTime($template->getTimeStart(), 'timeStart');
        $this->validateTime($template->getTimeEnd(), 'timeEnd');

        $from = new DateTimeImmutable($template->getDateStart());
        $to = new DateTimeImmutable($template->getDateEnd());

        if ($to < $from) {
            throw new BadRequest('dateEnd must be on/after dateStart');
        }

        if ($from->diff($to)->days > 370) {
            throw new BadRequest('Template generation range cannot exceed 370 days');
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

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function buildUtcDateTimes(
        DateTimeImmutable $day,
        DoctorShiftTemplate $template,
        DateTimeZone $timeZone
    ): array {
        $startLocal = new DateTimeImmutable(
            $day->format('Y-m-d') . ' ' . $template->getTimeStart() . ':00',
            $timeZone
        );
        $endLocal = new DateTimeImmutable(
            $day->format('Y-m-d') . ' ' . $template->getTimeEnd() . ':00',
            $timeZone
        );

        if ($endLocal <= $startLocal) {
            throw new BadRequest('timeEnd must be after timeStart');
        }

        $utc = new DateTimeZone('UTC');

        return [$startLocal->setTimezone($utc), $endLocal->setTimezone($utc)];
    }

    private function findExistingShift(
        DoctorShiftTemplate $template,
        DateTimeImmutable $startUtc,
        DateTimeImmutable $endUtc
    ): ?DoctorShift {
        /** @var DoctorShift|null $shift */
        $shift = $this->entityManager
            ->getRDBRepository(DoctorShift::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'shiftTemplateId' => $template->getId(),
                'doctorId' => $template->getDoctorId(),
                'dateStart' => $startUtc->format('Y-m-d H:i:s'),
                'dateEnd' => $endUtc->format('Y-m-d H:i:s'),
            ])
            ->findOne();

        return $shift;
    }

    private function createShift(
        DoctorShiftTemplate $template,
        DateTimeImmutable $startUtc,
        DateTimeImmutable $endUtc
    ): void {
        /** @var DoctorShift $shift */
        $shift = $this->entityManager->getNewEntity(DoctorShift::ENTITY_TYPE);
        $shift->set('shiftTemplateId', $template->getId());
        $shift->set('doctorId', $template->getDoctorId());
        $shift->set('assistantId', $template->getAssistantId());
        $shift->set('clinicId', $template->getClinicId());
        $shift->set('cabinetId', $template->getCabinetId());
        $shift->set('dateStart', $startUtc->format('Y-m-d H:i:s'));
        $shift->set('dateEnd', $endUtc->format('Y-m-d H:i:s'));
        $shift->set('type', $template->getType());
        $shift->set('status', DoctorShift::STATUS_ACTIVE);
        $shift->set('description', (string) ($template->get('description') ?? ''));
        $this->entityManager->saveEntity($shift);
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
