<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Payment;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Payment;
use Espo\ORM\Entity;

class AssignNumber
{
    public static int $order = 5;

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Payment) {
            return;
        }
        if (!$entity->isNew() || $entity->get('number')) {
            return;
        }

        $year = (new DateTimeImmutable())->format('Y');
        $prefix = 'PMT-' . $year . '-';

        $count = (int) $this->entityManager
            ->getRDBRepository(Payment::ENTITY_TYPE)
            ->where(['number*' => $prefix . '%'])
            ->count();

        $next = $count + 1;
        $candidate = $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        while ($this->numberExists($candidate)) {
            $next++;
            $candidate = $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        }

        $entity->set('number', $candidate);
        if (!$entity->get('name')) {
            $entity->set('name', $candidate);
        }
    }

    private function numberExists(string $number): bool
    {
        $existing = $this->entityManager
            ->getRDBRepository(Payment::ENTITY_TYPE)
            ->where(['number' => $number])
            ->findOne();
        return $existing !== null;
    }
}
