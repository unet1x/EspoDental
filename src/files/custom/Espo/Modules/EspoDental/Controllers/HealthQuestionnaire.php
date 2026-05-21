<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\HealthQuestionnaireService;

class HealthQuestionnaire extends Record
{
    /**
     * GET /HealthQuestionnaire/action/answers?id=...
     *
     * @return array<string, mixed>
     */
    public function getActionAnswers(Request $request): array
    {
        $id = $request->getQueryParam('id');

        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }

        if (!$this->getAcl()->checkScope('HealthQuestionnaire', 'read')) {
            throw new Forbidden();
        }

        /** @var HealthQuestionnaireService $service */
        $service = $this->injectableFactory->create(HealthQuestionnaireService::class);

        return $service->getAnswerTable($id);
    }
}
