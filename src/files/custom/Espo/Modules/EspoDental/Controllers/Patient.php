<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\HealthQuestionnaireService;

class Patient extends Record
{
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
