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
        return $this->calculateRevenueForVisitUser('doctorId', $userId, $from, $to);
    }

    /**
     * @return array{revenueBasis: float, visitsCount: int}
     */
    public function calculateAssistantRevenue(string $userId, string $from, string $to): array
    {
        return $this->calculateRevenueForVisitUser('assistantId', $userId, $from, $to);
    }

    /**
     * @return array{revenueBasis: float, visitsCount: int}
     */
    private function calculateRevenueForVisitUser(string $userField, string $userId, string $from, string $to): array
    {
        /** @var iterable<Visit> $visits */
        $visits = $this->entityManager
            ->getRDBRepository(Visit::ENTITY_TYPE)
            ->where([
                $userField => $userId,
                'status' => Visit::STATUS_FINISHED,
                'startedAt>=' => $from,
                'startedAt<' => $to,
                'deleted' => false,
            ])
            ->find();

        $visitIds = [];
        foreach ($visits as $visit) {
            $visitIds[] = (string) $visit->getId();
        }

        if ($visitIds === []) {
            return [
                'revenueBasis' => 0.0,
                'visitsCount' => 0,
            ];
        }

        /** @var iterable<VisitServiceLine> $lines */
        $lines = $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where([
                'visitId' => $visitIds,
                'deleted' => false,
            ])
            ->find();

        $revenueBasis = 0.0;
        foreach ($lines as $line) {
            $revenueBasis += $line->getAmount();
        }

        return [
            'revenueBasis' => round($revenueBasis, 2),
            'visitsCount' => count($visitIds),
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
