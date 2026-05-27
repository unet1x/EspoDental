<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\AssistantActionProposal;
use Espo\Modules\EspoDental\Entities\IntegrationSettings;
use Espo\Modules\EspoDental\Entities\NotificationLog;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Tools\Messaging\MessageDeliveryGateway;

class IntegrationMcpService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config,
        private readonly MessageDeliveryGateway $messageDeliveryGateway
    ) {
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
        $stageJAcceptance = $this->buildStageJAcceptance(
            $tools,
            $toolAudit,
            $integrations,
            $notifications,
            $proposals
        );
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
            'stageJAcceptance' => $stageJAcceptance,
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
     * @return array{id: string, type: string, status: string, acceptedAt: string, acceptedById: string}
     */
    public function acceptProviderCredentials(string $id, string $note, string $userId): array
    {
        /** @var IntegrationSettings|null $setting */
        $setting = $this->entityManager->getEntityById(IntegrationSettings::ENTITY_TYPE, $id);
        if (!$setting) {
            throw new NotFound('Integration settings not found');
        }

        $enabled = (bool) $setting->get('isEnabled');
        $secretsReference = trim((string) ($setting->get('secretsReference') ?? ''));
        $checklist = $this->buildProviderChecklist(
            (string) ($setting->get('integrationType') ?? ''),
            $setting,
            $enabled,
            $secretsReference
        );
        $readiness = $this->buildLiveDeliveryReadiness($checklist, false);

        if (!$readiness['dryRunReady']) {
            throw new Conflict('Provider readiness checklist is not ready for acceptance');
        }

        $acceptedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $setting->set('credentialAcceptanceStatus', IntegrationSettings::ACCEPTANCE_ACCEPTED);
        $setting->set('credentialAcceptedAt', $acceptedAt);
        $setting->set('credentialAcceptedById', $userId);
        $setting->set('credentialAcceptanceNote', $note);

        $this->entityManager->saveEntity($setting);

        return [
            'id' => (string) $setting->getId(),
            'type' => (string) ($setting->get('integrationType') ?? ''),
            'status' => IntegrationSettings::ACCEPTANCE_ACCEPTED,
            'acceptedAt' => $acceptedAt,
            'acceptedById' => $userId,
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
            $accepted = $this->isCredentialAccepted($setting);
            $checklist = $this->buildProviderChecklist($type, $setting, $enabled, $secretsReference);
            $liveDelivery = $this->buildLiveDeliveryReadiness($checklist, $accepted);
            $checklist[] = $this->checklistItem(
                'provider_acceptance',
                'Clinic credentials accepted before controlled live smoke',
                $liveDelivery['dryRunReady'] ? $liveDelivery['status'] : 'blocked',
                true
            );

            $rows[] = [
                'id' => $setting ? (string) $setting->getId() : '',
                'type' => $type,
                'configured' => $setting !== null,
                'enabled' => $enabled,
                'secretsReference' => $secretsReference,
                'clinicId' => $setting ? (string) ($setting->get('clinicId') ?? '') : '',
                'updatedAt' => $setting ? (string) ($setting->get('updatedAt') ?? '') : '',
                'status' => $status,
                'credentialAcceptanceStatus' => $this->credentialAcceptanceStatus($setting),
                'credentialAcceptedAt' => $setting ? (string) ($setting->get('credentialAcceptedAt') ?? '') : '',
                'credentialAcceptedById' => $setting ? (string) ($setting->get('credentialAcceptedById') ?? '') : '',
                'runtimeConfigured' => $this->isRuntimeConfigured($checklist),
                'checklist' => $checklist,
                'liveDelivery' => $liveDelivery,
            ];
        }

        $summary = [
            'configuredCount' => 0,
            'enabledCount' => 0,
            'readyCount' => 0,
            'needsSecretCount' => 0,
            'missingSettingsCount' => 0,
            'runtimeMissingCount' => 0,
            'dryRunReadyCount' => 0,
            'acceptancePendingCount' => 0,
            'acceptedCount' => 0,
            'liveTestAllowedCount' => 0,
            'blockedLiveTestCount' => 0,
        ];

        foreach ($rows as $row) {
            $summary['configuredCount'] += $row['configured'] ? 1 : 0;
            $summary['enabledCount'] += $row['enabled'] ? 1 : 0;
            $summary['readyCount'] += $row['status'] === 'ready' ? 1 : 0;
            $summary['needsSecretCount'] += $row['status'] === 'needs_secret' ? 1 : 0;
            $summary['missingSettingsCount'] += $row['status'] === 'missing_settings' ? 1 : 0;
            $summary['runtimeMissingCount'] += $row['enabled'] && !$row['runtimeConfigured'] ? 1 : 0;
            $summary['dryRunReadyCount'] += $row['liveDelivery']['dryRunReady'] ? 1 : 0;
            $summary['acceptancePendingCount'] += $row['liveDelivery']['status'] === 'pending_acceptance' ? 1 : 0;
            $summary['acceptedCount'] += $row['liveDelivery']['status'] === 'accepted' ? 1 : 0;
            $summary['liveTestAllowedCount'] += $row['liveDelivery']['liveTestAllowed'] ? 1 : 0;
            $summary['blockedLiveTestCount'] += $row['liveDelivery']['status'] === 'blocked' ? 1 : 0;
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
     * @return list<array<string, mixed>>
     */
    private function buildProviderChecklist(
        string $type,
        ?IntegrationSettings $setting,
        bool $enabled,
        string $secretsReference
    ): array {
        $checklist = [
            $this->checklistItem(
                'settings_record',
                'IntegrationSettings record exists',
                $setting !== null ? 'ok' : 'missing',
                true
            ),
            $this->checklistItem(
                'enabled_flag',
                'Integration is enabled by staff',
                $setting === null ? 'missing' : ($enabled ? 'ok' : 'disabled'),
                true
            ),
            $this->checklistItem(
                'secret_reference',
                'Secret reference is assigned',
                $secretsReference !== '' ? 'ok' : 'missing',
                $enabled
            ),
        ];

        foreach ($this->runtimeRequirements($type) as $requirement) {
            $required = $enabled;
            $checklist[] = $this->checklistItem(
                'runtime_' . $requirement['key'],
                $requirement['label'],
                $required ? ($this->configValuePresent($requirement['configKey']) ? 'ok' : 'missing') : 'not_checked',
                $required,
                ['sensitive' => (bool) ($requirement['sensitive'] ?? false)]
            );
        }

        return $checklist;
    }

    /**
     * @param list<array<string, mixed>> $checklist
     * @return array{status: string, dryRunReady: bool, liveTestAllowed: bool, blockers: list<string>, nextStep: string}
     */
    private function buildLiveDeliveryReadiness(array $checklist, bool $accepted): array
    {
        $blockers = [];

        foreach ($checklist as $item) {
            if (!(bool) ($item['required'] ?? false)) {
                continue;
            }

            if (($item['status'] ?? '') !== 'ok') {
                $blockers[] = (string) ($item['key'] ?? '');
            }
        }

        $dryRunReady = count($blockers) === 0;
        $status = 'blocked';
        $liveTestAllowed = false;
        $nextStep = 'Complete blocking checklist items before provider credential acceptance.';

        if ($dryRunReady && $accepted) {
            $status = IntegrationSettings::ACCEPTANCE_ACCEPTED;
            $liveTestAllowed = true;
            $nextStep = 'Provider credentials accepted; live smoke remains an explicit staff action.';
        } elseif ($dryRunReady) {
            $status = IntegrationSettings::ACCEPTANCE_PENDING;
            $nextStep = 'Credential checklist is ready for staff acceptance; run live provider smoke only after explicit approval.';
        }

        return [
            'status' => $status,
            'dryRunReady' => $dryRunReady,
            'liveTestAllowed' => $liveTestAllowed,
            'blockers' => array_values(array_filter($blockers)),
            'nextStep' => $nextStep,
        ];
    }

    private function isCredentialAccepted(?IntegrationSettings $setting): bool
    {
        return $this->credentialAcceptanceStatus($setting) === IntegrationSettings::ACCEPTANCE_ACCEPTED;
    }

    private function credentialAcceptanceStatus(?IntegrationSettings $setting): string
    {
        if (!$setting) {
            return IntegrationSettings::ACCEPTANCE_PENDING;
        }

        $status = (string) ($setting->get('credentialAcceptanceStatus') ?? '');

        return in_array(
            $status,
            [
                IntegrationSettings::ACCEPTANCE_PENDING,
                IntegrationSettings::ACCEPTANCE_ACCEPTED,
                IntegrationSettings::ACCEPTANCE_REVOKED,
            ],
            true
        ) ? $status : IntegrationSettings::ACCEPTANCE_PENDING;
    }

    /**
     * @param list<array<string, mixed>> $checklist
     */
    private function isRuntimeConfigured(array $checklist): bool
    {
        $hasRuntimeRequirement = false;

        foreach ($checklist as $item) {
            $key = (string) ($item['key'] ?? '');
            if (!str_starts_with($key, 'runtime_') || !(bool) ($item['required'] ?? false)) {
                continue;
            }

            $hasRuntimeRequirement = true;
            if (($item['status'] ?? '') !== 'ok') {
                return false;
            }
        }

        return $hasRuntimeRequirement;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function checklistItem(string $key, string $label, string $status, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'required' => $required,
        ], $extra);
    }

    /**
     * @return list<array{key: string, label: string, configKey: string, sensitive?: bool}>
     */
    private function runtimeRequirements(string $type): array
    {
        return match ($type) {
            IntegrationSettings::TYPE_SMTP => [
                [
                    'key' => 'smtp_enabled',
                    'label' => 'SMTP runtime setting is enabled',
                    'configKey' => 'espoDentalSmtpEnabled',
                ],
                [
                    'key' => 'smtp_host',
                    'label' => 'SMTP host is configured',
                    'configKey' => 'espoDentalSmtpHost',
                ],
                [
                    'key' => 'smtp_port',
                    'label' => 'SMTP port is configured',
                    'configKey' => 'espoDentalSmtpPort',
                ],
                [
                    'key' => 'smtp_from',
                    'label' => 'SMTP sender address is configured',
                    'configKey' => 'espoDentalSmtpFromAddress',
                ],
            ],
            IntegrationSettings::TYPE_TELEGRAM => [
                [
                    'key' => 'telegram_enabled',
                    'label' => 'Telegram runtime setting is enabled',
                    'configKey' => 'espoDentalTelegramEnabled',
                ],
                [
                    'key' => 'telegram_token',
                    'label' => 'Telegram bot token is present',
                    'configKey' => 'espoDentalTelegramBotToken',
                    'sensitive' => true,
                ],
                [
                    'key' => 'telegram_api_base',
                    'label' => 'Telegram API base URL is configured',
                    'configKey' => 'espoDentalTelegramApiBase',
                ],
            ],
            IntegrationSettings::TYPE_WHATSAPP => [
                [
                    'key' => 'whatsapp_enabled',
                    'label' => 'WhatsApp runtime setting is enabled',
                    'configKey' => 'espoDentalWhatsAppEnabled',
                ],
                [
                    'key' => 'whatsapp_provider',
                    'label' => 'WhatsApp provider label is configured',
                    'configKey' => 'espoDentalWhatsAppProvider',
                ],
                [
                    'key' => 'whatsapp_api_base',
                    'label' => 'WhatsApp API endpoint is configured',
                    'configKey' => 'espoDentalWhatsAppApiBase',
                ],
                [
                    'key' => 'whatsapp_token',
                    'label' => 'WhatsApp access token is present',
                    'configKey' => 'espoDentalWhatsAppAccessToken',
                    'sensitive' => true,
                ],
            ],
            default => [],
        };
    }

    private function configValuePresent(string $key): bool
    {
        $value = $this->config->get($key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return trim((string) $value) !== '';
        }

        return $value !== null;
    }

    /**
     * @return array{summary: array<string, int>, failedRows: list<array<string, mixed>>, queuedRows: list<array<string, mixed>>}
     */
    private function buildNotificationHealth(int $limit): array
    {
        $summary = [
            'queuedCount' => $this->countNotificationStatus(NotificationLog::STATUS_QUEUED),
            'sentCount' => $this->countNotificationStatus(NotificationLog::STATUS_SENT),
            'failedCount' => $this->countNotificationStatus(NotificationLog::STATUS_FAILED),
            'skippedCount' => $this->countNotificationStatus(NotificationLog::STATUS_SKIPPED),
            'retryCandidateCount' => 0,
            'queuedReadyCount' => 0,
            'queuedBlockedCount' => 0,
        ];
        $failedRows = [];
        $queuedRows = [];

        /** @var iterable<NotificationLog> $queuedLogs */
        $queuedLogs = $this->entityManager
            ->getRDBRepository(NotificationLog::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'status' => NotificationLog::STATUS_QUEUED,
            ])
            ->order('scheduledFor', 'ASC')
            ->find();

        foreach ($queuedLogs as $log) {
            $preflight = $this->messageDeliveryGateway->preflight($log);

            if ($preflight['ok']) {
                $summary['queuedReadyCount']++;
            } else {
                $summary['queuedBlockedCount']++;
            }

            if (count($queuedRows) >= $limit) {
                continue;
            }

            $queuedRows[] = [
                'id' => (string) $log->getId(),
                'name' => (string) ($log->get('name') ?? ''),
                'channel' => (string) ($log->get('channel') ?? ''),
                'provider' => $preflight['provider'],
                'kind' => (string) ($log->get('kind') ?? ''),
                'recipient' => (string) ($log->get('recipient') ?? ''),
                'scheduledFor' => (string) ($log->get('scheduledFor') ?? ''),
                'attempts' => (int) ($log->get('attempts') ?? 0),
                'deliveryGate' => [
                    'ok' => $preflight['ok'],
                    'error' => $preflight['error'],
                    'accepted' => $preflight['accepted'],
                ],
            ];
        }

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

        return ['summary' => $summary, 'failedRows' => $failedRows, 'queuedRows' => $queuedRows];
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
     * @param list<array<string, mixed>> $tools
     * @param array{directMutationToolCount: int} $toolAudit
     * @param array{summary: array<string, int>, rows: list<array<string, mixed>>} $integrations
     * @param array{summary: array<string, int>, failedRows: list<array<string, mixed>>, queuedRows: list<array<string, mixed>>} $notifications
     * @param array{summary: array<string, int>, rows: list<array<string, mixed>>} $proposals
     * @return array{status: string, readyCount: int, attentionCount: int, checks: list<array{key: string, label: string, status: string, detail: string}>}
     */
    private function buildStageJAcceptance(
        array $tools,
        array $toolAudit,
        array $integrations,
        array $notifications,
        array $proposals
    ): array {
        $restrictedRoutes = [
            '/EspoDental/AssistantActionProposal/approve',
            '/EspoDental/AssistantActionProposal/reject',
            '/EspoDental/NotificationLog/requeue',
            '/EspoDental/NotificationLog/processQueue',
            '/EspoDental/Integration/acceptProviderCredentials',
        ];
        $restrictedMcpRoutes = [];

        foreach ($tools as $tool) {
            $route = (string) ($tool['route'] ?? '');
            if (in_array($route, $restrictedRoutes, true)) {
                $restrictedMcpRoutes[] = $route;
            }
        }

        $integrationRows = $integrations['rows'];
        $providerRowsWithChecklist = 0;
        foreach ($integrationRows as $row) {
            if (!empty($row['checklist']) && is_array($row['checklist'])) {
                $providerRowsWithChecklist++;
            }
        }

        $ungatedLiveRows = 0;
        foreach ($integrationRows as $row) {
            $liveDelivery = $row['liveDelivery'] ?? [];
            if (
                (bool) ($liveDelivery['liveTestAllowed'] ?? false) &&
                (string) ($row['credentialAcceptanceStatus'] ?? '') !== IntegrationSettings::ACCEPTANCE_ACCEPTED
            ) {
                $ungatedLiveRows++;
            }
        }

        $checks = [
            $this->acceptanceCheck(
                'mcp_contract',
                'MCP contract has no direct mutations',
                $toolAudit['directMutationToolCount'] === 0 && count($restrictedMcpRoutes) === 0 ? 'ok' : 'critical',
                'safeTools=' . (string) (($toolAudit['safeToolCount'] ?? 0)) .
                    ', restrictedMcpRoutes=' . (string) count($restrictedMcpRoutes)
            ),
            $this->acceptanceCheck(
                'provider_readiness',
                'Provider readiness checklist is visible',
                $providerRowsWithChecklist >= 3 ? 'ok' : 'attention',
                'providers=' . (string) count($integrationRows) .
                    ', dryRunReady=' . (string) ($integrations['summary']['dryRunReadyCount'] ?? 0) .
                    ', accepted=' . (string) ($integrations['summary']['acceptedCount'] ?? 0)
            ),
            $this->acceptanceCheck(
                'credential_gate',
                'Credential gate blocks live sends until staff acceptance',
                $ungatedLiveRows === 0 ? 'ok' : 'critical',
                'liveTestAllowed=' . (string) ($integrations['summary']['liveTestAllowedCount'] ?? 0) .
                    ', acceptancePending=' . (string) ($integrations['summary']['acceptancePendingCount'] ?? 0)
            ),
            $this->acceptanceCheck(
                'queue_preflight',
                'Queue preflight is visible before processing',
                ($notifications['summary']['queuedBlockedCount'] ?? 0) > 0 ? 'attention' : 'ok',
                'queuedReady=' . (string) ($notifications['summary']['queuedReadyCount'] ?? 0) .
                    ', queuedBlocked=' . (string) ($notifications['summary']['queuedBlockedCount'] ?? 0)
            ),
            $this->acceptanceCheck(
                'notification_retry_loop',
                'Notification retry loop stays staff-controlled',
                ($notifications['summary']['failedCount'] ?? 0) > 0 ? 'attention' : 'ok',
                'failed=' . (string) ($notifications['summary']['failedCount'] ?? 0) .
                    ', retryCandidates=' . (string) ($notifications['summary']['retryCandidateCount'] ?? 0)
            ),
            $this->acceptanceCheck(
                'proposal_review_loop',
                'Assistant proposals require human review',
                ($proposals['summary']['pendingReviewCount'] ?? 0) > 0 ? 'attention' : 'ok',
                'pending=' . (string) ($proposals['summary']['pendingReviewCount'] ?? 0) .
                    ', highRisk=' . (string) ($proposals['summary']['highRiskPendingCount'] ?? 0)
            ),
        ];

        $status = 'ok';
        $readyCount = 0;
        $attentionCount = 0;

        foreach ($checks as $check) {
            if ($check['status'] === 'ok') {
                $readyCount++;
                continue;
            }

            $attentionCount++;
            if ($check['status'] === 'critical') {
                $status = 'critical';
            } elseif ($status !== 'critical') {
                $status = 'attention';
            }
        }

        return [
            'status' => $status,
            'readyCount' => $readyCount,
            'attentionCount' => $attentionCount,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function acceptanceCheck(string $key, string $label, string $status, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
        ];
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
            $notifications['summary']['queuedBlockedCount'] > 0 ||
            $proposals['summary']['highRiskPendingCount'] > 0 ||
            $integrations['summary']['needsSecretCount'] > 0 ||
            $integrations['summary']['runtimeMissingCount'] > 0 ||
            $integrations['summary']['acceptancePendingCount'] > 0
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
