<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\VisitServiceLine;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Service;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;
use Espo\ORM\Entity;

class RecalculateAmount
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
        if (!$entity instanceof VisitServiceLine) {
            return;
        }

        if ($entity->isNew() && !$entity->get('unitPrice') && $entity->get('serviceId')) {
            /** @var Service|null $service */
            $service = $this->entityManager->getEntityById(Service::ENTITY_TYPE, $entity->get('serviceId'));
            if ($service) {
                $entity->set('unitPrice', $service->getPrice());
                if (!$entity->get('vatRate')) {
                    $entity->set('vatRate', $service->getVatRate());
                }
                if (!$entity->get('name')) {
                    $entity->set('name', (string) $service->get('name'));
                }
            }
        }

        $qty = max(1, $entity->getQuantity());
        $unit = $entity->getUnitPrice();
        $discount = max(0.0, min(100.0, $entity->getDiscount()));
        $vatRate = max(0.0, min(100.0, $entity->getVatRate()));

        $gross = $qty * $unit;
        $afterDiscount = $gross * (1 - $discount / 100.0);
        $vat = $afterDiscount * ($vatRate / 100.0);

        $entity->set('amount', round($afterDiscount, 2));
        $entity->set('vatAmount', round($vat, 2));
    }
}
