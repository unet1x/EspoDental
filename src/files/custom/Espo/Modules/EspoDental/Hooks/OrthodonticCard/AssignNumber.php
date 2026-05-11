<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\OrthodonticCard;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\OrthodonticCard;
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
        if (!$entity instanceof OrthodonticCard) {
            return;
        }
        if ((string) $entity->get('cardNumber') !== '') {
            $this->ensureName($entity);
            return;
        }
        $entity->set('cardNumber', $this->generateNumber());
        $this->ensureName($entity);
    }

    private function ensureName(OrthodonticCard $entity): void
    {
        if ((string) $entity->get('name') !== '') {
            return;
        }
        $entity->set('name', (string) $entity->get('cardNumber'));
    }

    private function generateNumber(): string
    {
        $year = (int) date('Y');
        $prefix = 'ORTHO-' . $year . '-';
        $last = $this->entityManager
            ->getRDBRepository(OrthodonticCard::ENTITY_TYPE)
            ->where(['cardNumber*' => $prefix . '%'])
            ->order('cardNumber', 'DESC')
            ->findOne();

        $next = 1;
        if ($last) {
            $tail = (int) substr((string) $last->get('cardNumber'), strlen($prefix));
            $next = $tail + 1;
        }
        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
