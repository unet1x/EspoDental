<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EspoDental\Services\HealthQuestionnaireService;

class PublicHealthQuestionnaire
{
    public function __construct(private readonly HealthQuestionnaireService $service)
    {
    }

    /**
     * @return array{ok: true, questionnaireId: string, hasAlerts: bool}
     */
    public function postActionSubmit(Request $request): array
    {
        $token = (string) $request->getRouteParam('token');
        if ($token === '') {
            throw new BadRequest('token missing');
        }

        $body = $request->getParsedBody();
        if (!is_object($body)) {
            throw new BadRequest('body required');
        }

        $items = isset($body->items) ? (array) $body->items : [];
        $signature = isset($body->signature) && is_string($body->signature) ? $body->signature : null;

        $audit = [
            'ip' => $this->resolveClientIp($request),
            'userAgent' => (string) $request->getHeader('User-Agent'),
        ];

        $questionnaire = $this->service->submit($token, $items, $signature, $audit);

        return [
            'ok' => true,
            'questionnaireId' => (string) $questionnaire->getId(),
            'hasAlerts' => $questionnaire->hasAlerts(),
        ];
    }

    private function resolveClientIp(Request $request): string
    {
        $forwarded = (string) $request->getHeader('X-Forwarded-For');
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if ($first !== '') {
                return substr($first, 0, 64);
            }
        }

        $real = (string) $request->getHeader('X-Real-IP');
        if ($real !== '') {
            return substr($real, 0, 64);
        }

        $serverParams = $request->getServerParams();
        return substr((string) ($serverParams['REMOTE_ADDR'] ?? ''), 0, 64);
    }
}
