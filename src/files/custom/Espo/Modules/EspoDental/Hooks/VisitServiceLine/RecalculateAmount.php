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

        $service = null;
        if ($entity->get('serviceId')) {
            /** @var Service|null $service */
            $service = $this->entityManager->getEntityById(Service::ENTITY_TYPE, $entity->get('serviceId'));
            $serviceChanged = $entity->get('serviceId') !== $entity->getFetched('serviceId');
            if ($service) {
                $entity->set('unitPrice', $service->getPrice());
                $entity->set('unitPriceCurrency', (string) ($service->get('priceCurrency') ?: 'RUB'));
                $entity->set('vatRate', $service->getVatRate());
            }
            if ($service && ($serviceChanged || !$entity->get('name'))) {
                $entity->set('name', (string) $service->get('name'));
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
        $currency = (string) ($entity->get('unitPriceCurrency') ?: 'RUB');
        $entity->set('amountCurrency', $currency);
        $entity->set('vatAmountCurrency', $currency);
        if ($currency === 'RUB') {
            $entity->set('unitPriceConverted', $unit);
            $entity->set('amountConverted', round($afterDiscount, 2));
            $entity->set('vatAmountConverted', round($vat, 2));
        }
    }
}
