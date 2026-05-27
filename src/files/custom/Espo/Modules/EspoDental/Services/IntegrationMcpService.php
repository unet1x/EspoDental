<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\AssistantActionProposal;
use Espo\Modules\EspoDental\Entities\IntegrationSettings;
use Espo\Modules\EspoDental\Entities\NotificationLog;
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
                'name' => 'tools.list',
                'method' => 'GET',
                'route' => '/EspoDental/Integration/tools',
                'description' => 'Return the supported integration tool contract.',
                'directMutation' => false,
            ],
            [
                'name' => 'integration.healthcheck',
                'method' => 'GET',
                'route' => '/EspoDental/Integration/healthcheck',
                'description' => 'Read integration readiness and operational audit status.',
                'directMutation' => false,
            ],
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
    public function getHealthcheck(int $limit = 8): array
    {
        $limit = max(1, min(25, $limit));
        $tools = $this->listTools();
        $toolAudit = $this->buildToolAudit($tools);
        $integrations = $this->buildIntegrationReadiness();
        $notifications = $this->buildNotificationHealth($limit);
        $proposals = $this->buildProposalHealth($limit);
        $status = $this->resolveHealthStatus($toolAudit, $integrations, $notifications, $proposals);

        return [
            'status' => $status,
            'checkedAt' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'mcp' => [
                'ok' => $toolAudit['directMutationToolCount'] === 0,
                'toolAudit' => $toolAudit,
                'tools' => $tools,
                'blockedDirectMutations' => [
                    AssistantActionProposal::ACTION_POST_PAYMENT,
                    AssistantActionProposal::ACTION_FINISH_VISIT,
                    AssistantActionProposal::ACTION_EDIT_MEDICAL_NOTE,
                    AssistantActionProposal::ACTION_CANCEL_INVOICE,
                ],
            ],
            'integrations' => $integrations,
            'notifications' => $notifications,
            'proposals' => $proposals,
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

    /**
     * @param list<array<string, mixed>> $tools
     * @return array{toolCount: int, safeToolCount: int, directMutationToolCount: int}
     */
    private function buildToolAudit(array $tools): array
    {
        $directMutationToolCount = 0;

        foreach ($tools as $tool) {
            if ((bool) ($tool['directMutation'] ?? false)) {
                $directMutationToolCount++;
            }
        }

        return [
            'toolCount' => count($tools),
            'safeToolCount' => count($tools) - $directMutationToolCount,
            'directMutationToolCount' => $directMutationToolCount,
        ];
    }

    /**
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function buildIntegrationReadiness(): array
    {
        $rows = [];
        $settingsByType = [];

        /** @var iterable<IntegrationSettings> $settings */
        $settings = $this->entityManager
            ->getRDBRepository(IntegrationSettings::ENTITY_TYPE)
            ->where(['deleted' => false])
            ->find();

        foreach ($settings as $setting) {
            $type = (string) ($setting->get('integrationType') ?? '');

            if ($type === '') {
                continue;
            }

            $settingsByType[$type] = $setting;
        }

        foreach (
            [
                IntegrationSettings::TYPE_SMTP,
                IntegrationSettings::TYPE_TELEGRAM,
                IntegrationSettings::TYPE_WHATSAPP,
            ] as $type
        ) {
            $setting = $settingsByType[$type] ?? null;
            $enabled = $setting ? (bool) $setting->get('isEnabled') : false;
            $secretsReference = $setting ? trim((string) ($setting->get('secretsReference') ?? '')) : '';
            $status = $this->resolveIntegrationStatus($setting !== null, $enabled, $secretsReference);

            $rows[] = [
                'type' => $type,
                'configured' => $setting !== null,
                'enabled' => $enabled,
                'secretsReference' => $secretsReference,
                'clinicId' => $setting ? (string) ($setting->get('clinicId') ?? '') : '',
                'updatedAt' => $setting ? (string) ($setting->get('updatedAt') ?? '') : '',
                'status' => $status,
            ];
        }

        $summary = [
            'configuredCount' => 0,
            'enabledCount' => 0,
            'needsSecretCount' => 0,
            'missingSettingsCount' => 0,
        ];

        foreach ($rows as $row) {
            $summary['configuredCount'] += $row['configured'] ? 1 : 0;
            $summary['enabledCount'] += $row['enabled'] ? 1 : 0;
            $summary['needsSecretCount'] += $row['status'] === 'needs_secret' ? 1 : 0;
            $summary['missingSettingsCount'] += $row['status'] === 'missing_settings' ? 1 : 0;
        }

        return ['summary' => $summary, 'rows' => $rows];
    }

    private function resolveIntegrationStatus(bool $configured, bool $enabled, string $secretsReference): string
    {
        if (!$configured) {
            return 'missing_settings';
        }

        if (!$enabled) {
            return 'disabled';
        }

        if ($secretsReference === '') {
            return 'needs_secret';
        }

        return 'ready';
    }

    /**
     * @return array{summary: array<string, int>, failedRows: list<array<string, mixed>>}
     */
    private function buildNotificationHealth(int $limit): array
    {
        $summary = [
            'queuedCount' => $this->countNotificationStatus(NotificationLog::STATUS_QUEUED),
            'sentCount' => $this->countNotificationStatus(NotificationLog::STATUS_SENT),
            'failedCount' => $this->countNotificationStatus(NotificationLog::STATUS_FAILED),
            'skippedCount' => $this->countNotificationStatus(NotificationLog::STATUS_SKIPPED),
            'retryCandidateCount' => 0,
        ];
        $failedRows = [];

        /** @var iterable<NotificationLog> $logs */
        $logs = $this->entityManager
            ->getRDBRepository(NotificationLog::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'status' => NotificationLog::STATUS_FAILED,
            ])
            ->order('modifiedAt', 'DESC')
            ->find();

        foreach ($logs as $log) {
            $attempts = (int) ($log->get('attempts') ?? 0);
            $retryCandidate = $attempts < 3;

            if ($retryCandidate) {
                $summary['retryCandidateCount']++;
            }

            if (count($failedRows) >= $limit) {
                continue;
            }

            $failedRows[] = [
                'id' => (string) $log->getId(),
                'name' => (string) ($log->get('name') ?? ''),
                'channel' => (string) ($log->get('channel') ?? ''),
                'provider' => (string) ($log->get('provider') ?? ''),
                'kind' => (string) ($log->get('kind') ?? ''),
                'recipient' => (string) ($log->get('recipient') ?? ''),
                'errorMessage' => (string) ($log->get('errorMessage') ?? ''),
                'attempts' => $attempts,
                'retryCandidate' => $retryCandidate,
                'modifiedAt' => (string) ($log->get('modifiedAt') ?? ''),
            ];
        }

        return ['summary' => $summary, 'failedRows' => $failedRows];
    }

    private function countNotificationStatus(string $status): int
    {
        return $this->entityManager
            ->getRDBRepository(NotificationLog::ENTITY_TYPE)
            ->where(['deleted' => false, 'status' => $status])
            ->count();
    }

    /**
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    private function buildProposalHealth(int $limit): array
    {
        $summary = [
            'pendingReviewCount' => $this->countProposalStatus(AssistantActionProposal::STATUS_PENDING_REVIEW),
            'highRiskPendingCount' => 0,
        ];
        $rows = [];

        /** @var iterable<AssistantActionProposal> $proposals */
        $proposals = $this->entityManager
            ->getRDBRepository(AssistantActionProposal::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'status' => AssistantActionProposal::STATUS_PENDING_REVIEW,
            ])
            ->order('createdAt', 'DESC')
            ->find();

        foreach ($proposals as $proposal) {
            $risk = (string) ($proposal->get('riskLevel') ?? AssistantActionProposal::RISK_MEDIUM);

            if (in_array($risk, [AssistantActionProposal::RISK_HIGH, AssistantActionProposal::RISK_CRITICAL], true)) {
                $summary['highRiskPendingCount']++;
            }

            if (count($rows) >= $limit) {
                continue;
            }

            $rows[] = [
                'id' => (string) $proposal->getId(),
                'name' => (string) ($proposal->get('name') ?? ''),
                'source' => (string) ($proposal->get('source') ?? ''),
                'actionType' => (string) ($proposal->get('actionType') ?? ''),
                'riskLevel' => $risk,
                'summary' => (string) ($proposal->get('summary') ?? ''),
                'requiresApproval' => (bool) ($proposal->get('requiresApproval') ?? true),
                'createdAt' => (string) ($proposal->get('createdAt') ?? ''),
            ];
        }

        return ['summary' => $summary, 'rows' => $rows];
    }

    private function countProposalStatus(string $status): int
    {
        return $this->entityManager
            ->getRDBRepository(AssistantActionProposal::ENTITY_TYPE)
            ->where(['deleted' => false, 'status' => $status])
            ->count();
    }

    /**
     * @param array{directMutationToolCount: int} $toolAudit
     * @param array{summary: array<string, int>} $integrations
     * @param array{summary: array<string, int>} $notifications
     * @param array{summary: array<string, int>} $proposals
     */
    private function resolveHealthStatus(
        array $toolAudit,
        array $integrations,
        array $notifications,
        array $proposals
    ): string {
        if ($toolAudit['directMutationToolCount'] > 0) {
            return 'critical';
        }

        if (
            $notifications['summary']['failedCount'] > 0 ||
            $proposals['summary']['highRiskPendingCount'] > 0 ||
            $integrations['summary']['needsSecretCount'] > 0
        ) {
            return 'attention';
        }

        return 'ok';
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
