<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Payment;
use Espo\Modules\EspoDental\Entities\SalaryBonus;
use Espo\Modules\EspoDental\Entities\SalaryEntry;
use Espo\Modules\EspoDental\Entities\SalaryProfile;
use Espo\Modules\EspoDental\Tools\SalaryCalculator;

class SalaryService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly SalaryCalculator $calculator
    ) {
    }

    public function buildEntry(
        string $userId,
        string $periodFrom,
        string $periodTo,
        ?string $profileId = null,
        float $hoursWorked = 0.0
    ): SalaryEntry {
        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);
        if (!$user) {
            throw new NotFound('User not found');
        }

        $profile = $profileId
            ? $this->entityManager->getEntityById(SalaryProfile::ENTITY_TYPE, $profileId)
            : $this->findActiveProfile($userId, $periodFrom);

        $existing = $this->entityManager
            ->getRDBRepository(SalaryEntry::ENTITY_TYPE)
            ->where([
                'userId' => $userId,
                'periodFrom' => $periodFrom,
                'periodTo' => $periodTo,
                'status!=' => SalaryEntry::STATUS_CANCELLED,
            ])
            ->findOne();
        if ($existing) {
            throw new BadRequest('Entry already exists for this user/period');
        }

        $fromDt = $periodFrom . ' 00:00:00';
        $toDt = (new DateTimeImmutable($periodTo))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

        $doctor = $this->calculator->calculateDoctorRevenue($userId, $fromDt, $toDt);
        $assistant = $this->calculator->calculateAssistantRevenue($userId, $fromDt, $toDt);
        $bonuses = $this->calculator->aggregateBonuses($userId, $periodFrom, $periodTo);

        /** @var SalaryEntry $entry */
        $entry = $this->entityManager->getNewEntity(SalaryEntry::ENTITY_TYPE);
        $entry->set('user', $user);
        $entry->set('userId', $userId);
        if ($profile instanceof SalaryProfile) {
            $entry->set('profileId', $profile->getId());
            $entry->set('currency', $profile->getCurrency());
            $entry->set('clinicId', $profile->get('clinicId'));
        }
        $entry->set('periodFrom', $periodFrom);
        $entry->set('periodTo', $periodTo);
        $entry->set('status', SalaryEntry::STATUS_DRAFT);
        $entry->set('visitsCount', $doctor['visitsCount']);
        $entry->set('revenueBasis', $doctor['revenueBasis']);
        $entry->set('hoursWorked', max(0.0, $hoursWorked));

        $baseAmount = 0.0;
        $revenueAmount = 0.0;
        $assistantAmount = 0.0;
        if ($profile instanceof SalaryProfile) {
            $baseAmount = $this->calculator->calculateBase(
                $profile,
                (float) $entry->get('hoursWorked'),
                $doctor['visitsCount']
            );
            $revenueAmount = $doctor['revenueBasis'] * ($profile->getRevenuePercent() / 100);
            $assistantAmount = $assistant['revenueBasis'] * ($profile->getAssistantPercent() / 100);
        }
        $entry->set('baseAmount', round($baseAmount, 2));
        $entry->set('revenueAmount', round($revenueAmount, 2));
        $entry->set('assistantAmount', round($assistantAmount, 2));
        $entry->set('bonusAmount', round($bonuses['bonus'], 2));
        $entry->set('deductionAmount', round($bonuses['deduction'], 2));
        $entry->set('sourceBreakdown', [
            'doctor' => [
                'sourceType' => 'reception',
                'revenueBasis' => round($doctor['revenueBasis'], 2),
                'visitsCount' => $doctor['visitsCount'],
            ],
            'assistant' => [
                'sourceType' => 'reception',
                'revenueBasis' => round($assistant['revenueBasis'], 2),
            ],
            'manualAdjustments' => [
                'sourceType' => 'manual_adjustment',
                'bonus' => round($bonuses['bonus'], 2),
                'deduction' => round($bonuses['deduction'], 2),
            ],
            'rule' => [
                'profileId' => $profile instanceof SalaryProfile ? (string) $profile->getId() : '',
                'rateType' => $profile instanceof SalaryProfile ? $profile->getRateType() : '',
            ],
        ]);
        $entry->set('name', $this->buildEntryName($user, $periodFrom));

        $this->entityManager->saveEntity($entry);
        return $entry;
    }

    public function approveEntry(string $entryId, User $approver): SalaryEntry
    {
        /** @var ?SalaryEntry $entry */
        $entry = $this->entityManager->getEntityById(SalaryEntry::ENTITY_TYPE, $entryId);
        if (!$entry) {
            throw new NotFound('Salary entry not found');
        }
        if ($entry->getStatus() !== SalaryEntry::STATUS_DRAFT) {
            throw new BadRequest('Only draft entries can be approved');
        }
        $entry->set('status', SalaryEntry::STATUS_APPROVED);
        $entry->set('approvedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $entry->set('approvedById', $approver->getId());
        $this->entityManager->saveEntity($entry);
        $this->markBonusesIncluded($entry);
        return $entry;
    }

    public function payEntry(string $entryId, string $method = Payment::METHOD_CASH): SalaryEntry
    {
        /** @var ?SalaryEntry $entry */
        $entry = $this->entityManager->getEntityById(SalaryEntry::ENTITY_TYPE, $entryId);
        if (!$entry) {
            throw new NotFound('Salary entry not found');
        }
        if ($entry->getStatus() !== SalaryEntry::STATUS_APPROVED) {
            throw new BadRequest('Only approved entries can be paid');
        }
        $payment = $this->entityManager->getNewEntity(Payment::ENTITY_TYPE);
        $payment->set('patientId', null);
        $payment->set('direction', Payment::DIRECTION_OUT);
        $payment->set('status', Payment::STATUS_COMPLETED);
        $payment->set('amount', $entry->getTotalAmount());
        $payment->set('currency', $entry->getCurrency());
        $payment->set('method', $method);
        $payment->set('paidAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $payment->set('notes', 'Salary payout ' . (string) $entry->get('name'));
        $this->entityManager->saveEntity($payment);

        $entry->set('status', SalaryEntry::STATUS_PAID);
        $entry->set('paidAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $entry->set('paidPaymentId', $payment->getId());
        $this->entityManager->saveEntity($entry);
        return $entry;
    }

    public function cancelEntry(string $entryId): SalaryEntry
    {
        /** @var ?SalaryEntry $entry */
        $entry = $this->entityManager->getEntityById(SalaryEntry::ENTITY_TYPE, $entryId);
        if (!$entry) {
            throw new NotFound('Salary entry not found');
        }
        if ($entry->getStatus() === SalaryEntry::STATUS_PAID) {
            throw new BadRequest('Paid entries cannot be cancelled');
        }
        $entry->set('status', SalaryEntry::STATUS_CANCELLED);
        $this->entityManager->saveEntity($entry);
        $this->releaseBonuses($entry);
        return $entry;
    }

    private function findActiveProfile(string $userId, string $date): ?SalaryProfile
    {
        /** @var ?SalaryProfile $profile */
        $profile = $this->entityManager
            ->getRDBRepository(SalaryProfile::ENTITY_TYPE)
            ->where([
                'userId' => $userId,
                'isActive' => true,
                'dateStart<=' => $date,
                'OR' => [['dateEnd' => null], ['dateEnd>=' => $date]],
            ])
            ->order('dateStart', 'DESC')
            ->findOne();
        return $profile;
    }

    private function buildEntryName(User $user, string $periodFrom): string
    {
        $month = substr($periodFrom, 0, 7);
        return trim((string) $user->get('name')) . ' · ' . $month;
    }

    private function markBonusesIncluded(SalaryEntry $entry): void
    {
        /** @var iterable<SalaryBonus> $list */
        $list = $this->entityManager->getRDBRepository(SalaryBonus::ENTITY_TYPE)
            ->where([
                'userId' => $entry->get('userId'),
                'status' => SalaryBonus::STATUS_PENDING,
                'dateApplied>=' => $entry->get('periodFrom'),
                'dateApplied<=' => $entry->get('periodTo'),
            ])
            ->find();
        foreach ($list as $b) {
            $b->set('status', SalaryBonus::STATUS_INCLUDED);
            $b->set('includedInEntryId', $entry->getId());
            $this->entityManager->saveEntity($b);
        }
    }

    private function releaseBonuses(SalaryEntry $entry): void
    {
        /** @var iterable<SalaryBonus> $list */
        $list = $this->entityManager->getRDBRepository(SalaryBonus::ENTITY_TYPE)
            ->where([
                'includedInEntryId' => $entry->getId(),
                'status' => SalaryBonus::STATUS_INCLUDED,
            ])
            ->find();
        foreach ($list as $b) {
            $b->set('status', SalaryBonus::STATUS_PENDING);
            $b->set('includedInEntryId', null);
            $this->entityManager->saveEntity($b);
        }
    }
}
