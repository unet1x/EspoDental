<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\PreliminaryPatientConversion;

class PreliminaryPatient extends Record
{
    /**
     * POST /PreliminaryPatient/action/issueQuestionnaire
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

        if (!$this->getAcl()->checkScope('PreliminaryPatient', 'edit')) {
            throw new Forbidden();
        }

        /** @var PreliminaryPatientConversion $service */
        $service = $this->injectableFactory->create(PreliminaryPatientConversion::class);

        return $service->issueQuestionnaireToken($id, is_string($language) ? $language : null);
    }

    /**
     * POST /PreliminaryPatient/action/convertToPatient
     *
     * @return array{patientId: string, questionnaireId: string, appointmentsUpdated: int}
     */
    public function postActionConvertToPatient(Request $request): array
    {
        $body = $request->getParsedBody();

        $id = is_object($body) ? ($body->id ?? null) : null;
        $questionnaireId = is_object($body) ? ($body->questionnaireId ?? null) : null;

        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }

        if (!$this->getAcl()->checkScope('PreliminaryPatient', 'edit')) {
            throw new Forbidden();
        }
        if (!$this->getAcl()->checkScope('Patient', 'create')) {
            throw new Forbidden();
        }

        /** @var PreliminaryPatientConversion $service */
        $service = $this->injectableFactory->create(PreliminaryPatientConversion::class);

        return $service->convert($id, is_string($questionnaireId) ? $questionnaireId : null);
    }
}
