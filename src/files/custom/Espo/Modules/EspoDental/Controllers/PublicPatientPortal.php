<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EspoDental\Services\PatientPortalService;

class PublicPatientPortal
{
    public function __construct(private readonly PatientPortalService $service)
    {
    }

    /**
     * @return array{ok: true, expiresAt: string, debugOtp?: string|null}
     */
    public function postActionRequestCode(Request $request): array
    {
        $body = $this->getBody($request);

        return $this->service->requestCode((string) ($body->email ?? ''), $this->resolveAudit($request));
    }

    /**
     * @return array{token: string, expiresAt: string, patient: array<string, mixed>}
     */
    public function postActionVerifyCode(Request $request): array
    {
        $body = $this->getBody($request);

        return $this->service->verifyCode(
            (string) ($body->email ?? ''),
            (string) ($body->code ?? ''),
            $this->resolveAudit($request)
        );
    }

    /**
     * @return array{patient: array<string, mixed>, appointments: list<array<string, mixed>>}
     */
    public function getActionAppointments(Request $request): array
    {
        return $this->service->getAppointments($this->resolvePortalToken($request), $this->resolveAudit($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function postActionRescheduleRequests(Request $request): array
    {
        $body = (array) $this->getBody($request);

        return $this->service->createRescheduleRequest(
            $this->resolvePortalToken($request),
            $body,
            $this->resolveAudit($request)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function postActionCancelRescheduleRequest(Request $request): array
    {
        return $this->service->cancelRescheduleRequest(
            $this->resolvePortalToken($request),
            (string) $request->getRouteParam('id'),
            $this->resolveAudit($request)
        );
    }

    /**
     * @return array{ok: true}
     */
    public function postActionLogout(Request $request): array
    {
        return $this->service->logout($this->resolvePortalToken($request), $this->resolveAudit($request));
    }

    private function getBody(Request $request): object
    {
        $body = $request->getParsedBody();
        if (!is_object($body)) {
            throw new BadRequest('body required');
        }

        return $body;
    }

    private function resolvePortalToken(Request $request): string
    {
        $token = (string) $request->getHeader('X-Patient-Portal-Token');
        if ($token !== '') {
            return $token;
        }

        $authorization = (string) $request->getHeader('Authorization');
        if (str_starts_with($authorization, 'Bearer ')) {
            return trim(substr($authorization, 7));
        }

        return '';
    }

    /**
     * @return array{ip: string, userAgent: string}
     */
    private function resolveAudit(Request $request): array
    {
        $forwarded = (string) $request->getHeader('X-Forwarded-For');
        $ip = $forwarded !== ''
            ? trim(explode(',', $forwarded)[0])
            : (string) ($request->getServerParam('REMOTE_ADDR') ?? '');

        return [
            'ip' => substr($ip, 0, 64),
            'userAgent' => substr((string) $request->getHeader('User-Agent'), 0, 512),
        ];
    }
}
