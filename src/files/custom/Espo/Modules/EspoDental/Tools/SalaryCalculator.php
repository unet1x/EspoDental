<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\SalaryBonus;
use Espo\Modules\EspoDental\Entities\SalaryProfile;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;

class SalaryCalculator
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array{revenueBasis: float, visitsCount: int}
     */
    public function calculateDoctorRevenue(string $userId, string $from, string $to): array
    {
        $qb = $this->entityManager->getQueryBuilder()
            ->select(['SUM:vsl.amountTotal', 'COUNT:DISTINCT:visit.id'])
            ->from(VisitServiceLine::ENTITY_TYPE, 'vsl')
            ->leftJoin(Visit::ENTITY_TYPE, 'visit', ['visit.id:' => 'vsl.visitId'])
            ->where([
                'visit.doctorId' => $userId,
                'visit.status' => Visit::STATUS_FINISHED,
                'visit.startedAt>=' => $from,
                'visit.startedAt<' => $to,
                'vsl.deleted' => false,
                'visit.deleted' => false,
            ])
            ->build();
        $row = $this->entityManager->getQueryExecutor()->execute($qb)->fetch();
        $values = $row ? array_values($row) : [0, 0];
        return [
            'revenueBasis' => (float) ($values[0] ?? 0),
            'visitsCount' => (int) ($values[1] ?? 0),
        ];
    }

    /**
     * @return array{revenueBasis: float, visitsCount: int}
     */
    public function calculateAssistantRevenue(string $userId, string $from, string $to): array
    {
        $qb = $this->entityManager->getQueryBuilder()
            ->select(['SUM:vsl.amountTotal', 'COUNT:DISTINCT:visit.id'])
            ->from(VisitServiceLine::ENTITY_TYPE, 'vsl')
            ->leftJoin(Visit::ENTITY_TYPE, 'visit', ['visit.id:' => 'vsl.visitId'])
            ->where([
                'visit.assistantId' => $userId,
                'visit.status' => Visit::STATUS_FINISHED,
                'visit.startedAt>=' => $from,
                'visit.startedAt<' => $to,
                'vsl.deleted' => false,
                'visit.deleted' => false,
            ])
            ->build();
        $row = $this->entityManager->getQueryExecutor()->execute($qb)->fetch();
        $values = $row ? array_values($row) : [0, 0];
        return [
            'revenueBasis' => (float) ($values[0] ?? 0),
            'visitsCount' => (int) ($values[1] ?? 0),
        ];
    }

    /**
     * @return array{bonus: float, deduction: float, items: list<SalaryBonus>}
     */
    public function aggregateBonuses(string $userId, string $from, string $to): array
    {
        /** @var iterable<SalaryBonus> $list */
        $list = $this->entityManager->getRDBRepository(SalaryBonus::ENTITY_TYPE)
            ->where([
                'userId' => $userId,
                'status' => SalaryBonus::STATUS_PENDING,
                'dateApplied>=' => $from,
                'dateApplied<' => $to,
                'deleted' => false,
            ])
            ->find();

        $bonus = 0.0;
        $deduction = 0.0;
        $items = [];
        foreach ($list as $b) {
            $items[] = $b;
            if ($b->isPenalty()) {
                $deduction += abs($b->getAmount());
            } else {
                $bonus += $b->getAmount();
            }
        }
        return ['bonus' => $bonus, 'deduction' => $deduction, 'items' => $items];
    }

    public function calculateBase(SalaryProfile $profile, float $hoursWorked, int $visitsCount): float
    {
        return match ($profile->getRateType()) {
            SalaryProfile::RATE_FIXED_MONTHLY => $profile->getBaseRate(),
            SalaryProfile::RATE_HOURLY => $profile->getBaseRate() * $hoursWorked,
            SalaryProfile::RATE_PER_VISIT => $profile->getBaseRate() * $visitsCount,
            default => 0.0,
        };
    }
}
