<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use DateTimeImmutable;
use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\EspoDental\Entities\AssistantActionProposal as ProposalEntity;

class AssistantActionProposal extends Record
{
    /**
     * POST /EspoDental/AssistantActionProposal/approve
     *
     * @return array{id: string, status: string, reviewedAt: string, reviewedById: string}
     */
    public function postActionApprove(Request $request): array
    {
        return $this->review($request, ProposalEntity::STATUS_APPROVED);
    }

    /**
     * POST /EspoDental/AssistantActionProposal/reject
     *
     * @return array{id: string, status: string, reviewedAt: string, reviewedById: string}
     */
    public function postActionReject(Request $request): array
    {
        return $this->review($request, ProposalEntity::STATUS_REJECTED);
    }

    /**
     * @return array{id: string, status: string, reviewedAt: string, reviewedById: string}
     */
    private function review(Request $request, string $status): array
    {
        if (!$this->getAcl()->checkScope(ProposalEntity::ENTITY_TYPE, 'edit')) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
        if (!is_object($body)) {
            throw new BadRequest('Invalid payload');
        }

        $id = isset($body->id) ? (string) $body->id : '';
        if ($id === '') {
            throw new BadRequest('id is required');
        }

        /** @var ProposalEntity|null $proposal */
        $proposal = $this->entityManager->getEntityById(ProposalEntity::ENTITY_TYPE, $id);
        if (!$proposal) {
            throw new NotFound('Assistant proposal not found');
        }

        if ((string) ($proposal->get('status') ?? '') !== ProposalEntity::STATUS_PENDING_REVIEW) {
            throw new Conflict('Only pending review assistant proposals can be reviewed');
        }

        $reviewedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $reviewedById = (string) $this->getUser()->getId();
        $proposal->set('status', $status);
        $proposal->set('reviewedAt', $reviewedAt);
        $proposal->set('reviewedById', $reviewedById);

        if (property_exists($body, 'reviewNotes')) {
            $proposal->set('reviewNotes', trim((string) $body->reviewNotes));
        }

        $this->entityManager->saveEntity($proposal);

        return [
            'id' => (string) $proposal->getId(),
            'status' => (string) $proposal->get('status'),
            'reviewedAt' => (string) $proposal->get('reviewedAt'),
            'reviewedById' => (string) ($proposal->get('reviewedById') ?? $reviewedById),
        ];
    }
}
