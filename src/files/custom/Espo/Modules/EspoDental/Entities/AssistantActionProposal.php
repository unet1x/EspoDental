<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class AssistantActionProposal extends Entity
{
    public const ENTITY_TYPE = 'AssistantActionProposal';

    public const SOURCE_MCP = 'mcp';
    public const SOURCE_LLM = 'llm';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SYSTEM = 'system';

    public const ACTION_DRAFT_MESSAGE = 'draft_message';
    public const ACTION_PROPOSE_APPOINTMENT = 'propose_appointment';
    public const ACTION_ISSUE_QUESTIONNAIRE = 'issue_questionnaire';
    public const ACTION_UPDATE_CONTACT = 'update_contact';
    public const ACTION_POST_PAYMENT = 'post_payment';
    public const ACTION_FINISH_VISIT = 'finish_visit';
    public const ACTION_EDIT_MEDICAL_NOTE = 'edit_medical_note';
    public const ACTION_CANCEL_INVOICE = 'cancel_invoice';
    public const ACTION_OTHER = 'other';

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_CANCELLED = 'cancelled';
}
