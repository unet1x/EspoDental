<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\AssistantActionProposal;
use Espo\Modules\EspoDental\Entities\Patient;

class IntegrationMcpService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTools(): array
    {
        return [
            [
                'name' => 'patient_context.read',
                'method' => 'GET',
                'route' => '/EspoDental/Integration/patientContext',
                'description' => 'Read bounded patient context for assistants.',
                'directMutation' => false,
            ],
            [
                'name' => 'assistant_action.propose',
                'method' => 'POST',
                'route' => '/EspoDental/Integration/proposeAction',
                'description' => 'Create an auditable proposal for human review.',
                'directMutation' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPatientContext(string $patientId, bool $includeFinancials = false): array
    {
        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $patientId);
        if (!$patient) {
            throw new NotFound('Patient not found');
        }

        $context = [
            'patient' => [
                'id' => $patient->getId(),
                'name' => $patient->get('name'),
                'phone' => $patient->get('phone'),
                'emailAddress' => $patient->get('emailAddress'),
                'preferredChannel' => $patient->get('preferredChannel'),
                'remindersEnabled' => (bool) $patient->get('remindersEnabled'),
                'isChild' => (bool) $patient->get('isChild'),
                'questionnaireExpired' => (bool) $patient->get('questionnaireExpired'),
                'questionnaireHasAlerts' => (bool) $patient->get('questionnaireHasAlerts'),
            ],
            'allowedActions' => [
                AssistantActionProposal::ACTION_DRAFT_MESSAGE,
                AssistantActionProposal::ACTION_PROPOSE_APPOINTMENT,
                AssistantActionProposal::ACTION_ISSUE_QUESTIONNAIRE,
                AssistantActionProposal::ACTION_UPDATE_CONTACT,
            ],
            'blockedDirectMutations' => [
                AssistantActionProposal::ACTION_POST_PAYMENT,
                AssistantActionProposal::ACTION_FINISH_VISIT,
                AssistantActionProposal::ACTION_EDIT_MEDICAL_NOTE,
                AssistantActionProposal::ACTION_CANCEL_INVOICE,
            ],
        ];

        if ($includeFinancials) {
            $context['financials'] = [
                'balance' => (float) ($patient->get('balance') ?? 0.0),
            ];
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id: string, status: string, actionType: string, riskLevel: string}
     */
    public function createActionProposal(array $data): array
    {
        $actionType = $this->sanitizeActionType((string) ($data['actionType'] ?? ''));
        $riskLevel = $this->sanitizeRiskLevel((string) ($data['riskLevel'] ?? ''));
        $source = $this->sanitizeSource((string) ($data['source'] ?? ''));

        /** @var AssistantActionProposal $proposal */
        $proposal = $this->entityManager->getNewEntity(AssistantActionProposal::ENTITY_TYPE);
        $proposal->set('name', $this->stringValue($data, 'name', 'Assistant proposal'));
        $proposal->set('source', $source);
        $proposal->set('actionType', $actionType);
        $proposal->set('riskLevel', $riskLevel);
        $proposal->set('status', AssistantActionProposal::STATUS_PENDING_REVIEW);
        $proposal->set('requiresApproval', true);
        $proposal->set('patientId', $this->nullableStringValue($data, 'patientId'));
        $proposal->set('appointmentId', $this->nullableStringValue($data, 'appointmentId'));
        $proposal->set('notificationLogId', $this->nullableStringValue($data, 'notificationLogId'));
        $proposal->set('targetType', $this->nullableStringValue($data, 'targetType'));
        $proposal->set('targetId', $this->nullableStringValue($data, 'targetId'));
        $proposal->set('summary', $this->nullableStringValue($data, 'summary'));
        $proposal->set('payload', $this->arrayValue($data, 'payload'));

        $this->entityManager->saveEntity($proposal);

        return [
            'id' => (string) $proposal->getId(),
            'status' => (string) $proposal->get('status'),
            'actionType' => $actionType,
            'riskLevel' => $riskLevel,
        ];
    }

    private function sanitizeActionType(string $actionType): string
    {
        if ($actionType === '') {
            return AssistantActionProposal::ACTION_DRAFT_MESSAGE;
        }

        $allowed = [
            AssistantActionProposal::ACTION_DRAFT_MESSAGE,
            AssistantActionProposal::ACTION_PROPOSE_APPOINTMENT,
            AssistantActionProposal::ACTION_ISSUE_QUESTIONNAIRE,
            AssistantActionProposal::ACTION_UPDATE_CONTACT,
            AssistantActionProposal::ACTION_POST_PAYMENT,
            AssistantActionProposal::ACTION_FINISH_VISIT,
            AssistantActionProposal::ACTION_EDIT_MEDICAL_NOTE,
            AssistantActionProposal::ACTION_CANCEL_INVOICE,
            AssistantActionProposal::ACTION_OTHER,
        ];

        if (!in_array($actionType, $allowed, true)) {
            throw new BadRequest('Unsupported actionType');
        }

        return $actionType;
    }

    private function sanitizeSource(string $source): string
    {
        if ($source === '') {
            return AssistantActionProposal::SOURCE_MCP;
        }

        $allowed = [
            AssistantActionProposal::SOURCE_MCP,
            AssistantActionProposal::SOURCE_LLM,
            AssistantActionProposal::SOURCE_MANUAL,
            AssistantActionProposal::SOURCE_SYSTEM,
        ];

        if (!in_array($source, $allowed, true)) {
            throw new BadRequest('Unsupported source');
        }

        return $source;
    }

    private function sanitizeRiskLevel(string $riskLevel): string
    {
        if ($riskLevel === '') {
            return AssistantActionProposal::RISK_MEDIUM;
        }

        $allowed = [
            AssistantActionProposal::RISK_LOW,
            AssistantActionProposal::RISK_MEDIUM,
            AssistantActionProposal::RISK_HIGH,
            AssistantActionProposal::RISK_CRITICAL,
        ];

        if (!in_array($riskLevel, $allowed, true)) {
            throw new BadRequest('Unsupported riskLevel');
        }

        return $riskLevel;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringValue(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function nullableStringValue(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function arrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
