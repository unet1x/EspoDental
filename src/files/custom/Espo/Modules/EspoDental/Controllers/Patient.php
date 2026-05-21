<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\HealthQuestionnaireService;
use Espo\Modules\EspoDental\Services\PatientFileService;
use Espo\Modules\EspoDental\Services\PatientHistoryService;

class Patient extends Record
{
    /**
     * GET /Patient/action/files?id=...
     *
     * @return array{
     *     patientId: string,
     *     photos: list<array<string, mixed>>,
     *     questionnaireFiles: list<array<string, mixed>>
     * }
     */
    public function getActionFiles(Request $request): array
    {
        $id = $request->getQueryParam('id');
        $limit = (int) ($request->getQueryParam('limit') ?? 12);

        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }

        if (!$this->getAcl()->checkScope('Patient', 'read')) {
            throw new Forbidden();
        }

        /** @var PatientFileService $service */
        $service = $this->injectableFactory->create(PatientFileService::class);

        return $service->getPatientFiles(
            $id,
            $this->getAcl()->checkScope('VisitPhoto', 'read'),
            $this->getAcl()->checkScope('HealthQuestionnaire', 'read'),
            $limit
        );
    }

    /**
     * GET /Patient/action/history?id=...
     *
     * @return array{
     *     patientId: string,
     *     futureAppointments: list<array<string, mixed>>,
     *     pastVisits: list<array<string, mixed>>
     * }
     */
    public function getActionHistory(Request $request): array
    {
        $id = $request->getQueryParam('id');
        $limit = (int) ($request->getQueryParam('limit') ?? 8);

        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }

        if (!$this->getAcl()->checkScope('Patient', 'read')) {
            throw new Forbidden();
        }

        /** @var PatientHistoryService $service */
        $service = $this->injectableFactory->create(PatientHistoryService::class);

        return $service->getPatientHistory(
            $id,
            $this->getAcl()->checkScope('Appointment', 'read'),
            $this->getAcl()->checkScope('Visit', 'read'),
            $limit
        );
    }

    /**
     * POST /Patient/action/issueQuestionnaire
     *
     * @return array{tokenId: string, tokenUrl: string, expiresAt: string}
     */
    public function postActionIssueQuestionnaire(Request $request): array
    {
        $body = $request->getParsedBody();

        $id = is_object($body) ? ($body->id ?? null) : null;
        $language = is_object($body) ? ($body->language ?? null) : null;

        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }

        if (!$this->getAcl()->checkScope('Patient', 'edit')) {
            throw new Forbidden();
        }

        /** @var HealthQuestionnaireService $service */
        $service = $this->injectableFactory->create(HealthQuestionnaireService::class);

        return $service->issuePatientQuestionnaireToken($id, is_string($language) ? $language : null);
    }
}
