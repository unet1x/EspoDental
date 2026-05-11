<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\QuestionnaireToken;
use Espo\Modules\EspoDental\Services\HealthQuestionnaireService;
use Espo\Modules\EspoDental\Tools\HealthQuestionnaireRenderer;

class HealthQuestionnaire
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config,
        private readonly HealthQuestionnaireService $service,
        private readonly HealthQuestionnaireRenderer $renderer
    ) {
    }

    public function run(Request $request, Response $response): void
    {
        $token = (string) $request->getQueryParam('token');
        if ($token === '') {
            throw new BadRequest('token is required');
        }

        $tokenEntity = null;
        $error = null;

        try {
            $tokenEntity = $this->service->findValidToken($token);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $language = $tokenEntity ? $tokenEntity->getLanguage() : $this->detectLanguage($request);

        $patient = null;
        if ($tokenEntity) {
            $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, (string) $tokenEntity->getPatientId());
        }

        $schema = $this->service->getSchema($language);

        $html = $this->renderer->render([
            'language' => $language,
            'token' => $token,
            'schema' => $schema,
            'patient' => $patient,
            'error' => $error,
            'submitUrl' => $this->buildSubmitUrl($token),
        ]);

        $response
            ->setHeader('Content-Type', 'text/html; charset=utf-8')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->writeBody($html);
    }

    private function buildSubmitUrl(string $token): string
    {
        $siteUrl = rtrim((string) $this->config->get('siteUrl'), '/');
        return $siteUrl . '/api/v1/EspoDental/Public/HealthQuestionnaire/' . rawurlencode($token) . '/submit';
    }

    private function detectLanguage(Request $request): string
    {
        $header = (string) $request->getHeader('Accept-Language');
        if (str_starts_with(strtolower($header), 'es')) {
            return 'es_ES';
        }
        if (str_starts_with(strtolower($header), 'en')) {
            return 'en_US';
        }
        return 'ru_RU';
    }
}
