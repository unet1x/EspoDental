<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\OrthodonticCard;

class OrthodonticCardService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function closeCard(string $id, string $finalStatus = OrthodonticCard::STATUS_COMPLETED): OrthodonticCard
    {
        if (
            !in_array(
                $finalStatus,
                [OrthodonticCard::STATUS_COMPLETED, OrthodonticCard::STATUS_CANCELLED],
                true
            )
        ) {
            throw new BadRequest('Invalid final status');
        }
        /** @var ?OrthodonticCard $card */
        $card = $this->entityManager->getEntityById(OrthodonticCard::ENTITY_TYPE, $id);
        if (!$card) {
            throw new NotFound('OrthodonticCard not found');
        }
        if (!$card->isActive()) {
            throw new BadRequest('Card already closed');
        }
        $card->set('status', $finalStatus);
        if (!$card->get('dateClose')) {
            $card->set('dateClose', (new DateTimeImmutable('today'))->format('Y-m-d'));
        }
        $this->entityManager->saveEntity($card);
        return $card;
    }

    public function reopenCard(string $id): OrthodonticCard
    {
        /** @var ?OrthodonticCard $card */
        $card = $this->entityManager->getEntityById(OrthodonticCard::ENTITY_TYPE, $id);
        if (!$card) {
            throw new NotFound('OrthodonticCard not found');
        }
        if ($card->isActive()) {
            throw new BadRequest('Card is already active');
        }
        $card->set('status', OrthodonticCard::STATUS_IN_TREATMENT);
        $card->set('dateClose', null);
        $this->entityManager->saveEntity($card);
        return $card;
    }
}
