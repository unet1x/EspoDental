<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\AssistantActionProposal;

use DateTimeImmutable;
use Espo\Core\Exceptions\Conflict;
use Espo\Modules\EspoDental\Entities\AssistantActionProposal;
use Espo\ORM\Entity;

class GuardWorkflow
{
    public static int $order = 1;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof AssistantActionProposal) {
            return;
        }

        $this->normalizeApprovalRequirement($entity);
        $this->guardAppliedStatus($entity);
        $this->stampReviewTime($entity);
    }

    private function normalizeApprovalRequirement(AssistantActionProposal $proposal): void
    {
        $risk = (string) ($proposal->get('riskLevel') ?? AssistantActionProposal::RISK_LOW);
        $actionType = (string) ($proposal->get('actionType') ?? AssistantActionProposal::ACTION_OTHER);

        if (
            in_array($risk, [AssistantActionProposal::RISK_HIGH, AssistantActionProposal::RISK_CRITICAL], true) ||
            in_array($actionType, $this->criticalActionTypes(), true)
        ) {
            $proposal->set('requiresApproval', true);
        }
    }

    private function guardAppliedStatus(AssistantActionProposal $proposal): void
    {
        if ((string) ($proposal->get('status') ?? '') !== AssistantActionProposal::STATUS_APPLIED) {
            return;
        }

        if (!(bool) $proposal->get('requiresApproval')) {
            return;
        }

        $previousStatus = (string) ($proposal->getFetched('status') ?? '');
        if ($previousStatus !== AssistantActionProposal::STATUS_APPROVED) {
            throw new Conflict('Assistant proposal must be approved before it can be applied');
        }
    }

    private function stampReviewTime(AssistantActionProposal $proposal): void
    {
        $status = (string) ($proposal->get('status') ?? '');
        if (
            !in_array($status, [
                AssistantActionProposal::STATUS_APPROVED,
                AssistantActionProposal::STATUS_REJECTED,
                AssistantActionProposal::STATUS_CANCELLED,
            ], true)
        ) {
            return;
        }

        if ($proposal->get('reviewedAt')) {
            return;
        }

        $proposal->set('reviewedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
    }

    /**
     * @return list<string>
     */
    private function criticalActionTypes(): array
    {
        return [
            AssistantActionProposal::ACTION_POST_PAYMENT,
            AssistantActionProposal::ACTION_FINISH_VISIT,
            AssistantActionProposal::ACTION_EDIT_MEDICAL_NOTE,
            AssistantActionProposal::ACTION_CANCEL_INVOICE,
        ];
    }
}
