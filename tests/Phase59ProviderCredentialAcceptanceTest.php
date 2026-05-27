<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase59ProviderCredentialAcceptanceTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testIntegrationSettingsStoresCredentialAcceptance(): void
    {
        $entity = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/IntegrationSettings.json');
        $class = $this->readFile(self::MODULE_ROOT . '/Entities/IntegrationSettings.php');
        $hook = $this->readFile(self::MODULE_ROOT . '/Hooks/IntegrationSettings/Normalize.php');

        foreach (
            [
                'credentialAcceptanceStatus',
                'credentialAcceptedAt',
                'credentialAcceptedBy',
                'credentialAcceptanceNote',
            ] as $field
        ) {
            $this->assertArrayHasKey($field, $entity['fields']);
        }

        $this->assertSame(
            ['pending_acceptance', 'accepted', 'revoked'],
            $entity['fields']['credentialAcceptanceStatus']['options']
        );
        $this->assertArrayHasKey('credentialAcceptedBy', $entity['links']);
        $this->assertStringContainsString('ACCEPTANCE_ACCEPTED', $class);
        $this->assertStringContainsString('shouldRevokeAcceptedCredentials', $hook);
        $this->assertStringContainsString('ACCEPTANCE_REVOKED', $hook);
    }

    public function testStaffAcceptanceRouteIsSeparateFromMcpTools(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $routeMap = [];
        foreach ($routes as $route) {
            $routeMap[$route['route']] = $route;
        }

        $this->assertArrayHasKey('/EspoDental/Integration/acceptProviderCredentials', $routeMap);
        $this->assertSame('post', $routeMap['/EspoDental/Integration/acceptProviderCredentials']['method']);
        $this->assertSame(
            'acceptProviderCredentials',
            $routeMap['/EspoDental/Integration/acceptProviderCredentials']['params']['action']
        );

        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Integration.php');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/IntegrationMcpService.php');

        $this->assertStringContainsString('postActionAcceptProviderCredentials', $controller);
        $this->assertStringContainsString("checkScope('IntegrationSettings', 'edit')", $controller);
        $this->assertStringContainsString('acceptProviderCredentials', $service);
        $this->assertStringContainsString('Provider readiness checklist is not ready for acceptance', $service);
        $this->assertStringContainsString('credentialAcceptedById', $service);
        $this->assertStringContainsString('IntegrationSettings::ACCEPTANCE_ACCEPTED', $service);

        $toolsMethod = $this->methodCode($service, 'listTools');
        $this->assertStringNotContainsString('acceptProviderCredentials', $toolsMethod);
    }

    public function testDeliveryGatewayRequiresAcceptedProviderCredentials(): void
    {
        $gateway = $this->readFile(self::MODULE_ROOT . '/Tools/Messaging/MessageDeliveryGateway.php');

        foreach (
            [
                'provider_acceptance_required',
                'integrationTypeForChannel',
                'isProviderAccepted',
                'IntegrationSettings::ACCEPTANCE_ACCEPTED',
                'NotificationLog::CHANNEL_EMAIL',
                'NotificationLog::CHANNEL_TELEGRAM',
                'NotificationLog::CHANNEL_WHATSAPP',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $gateway);
        }
    }

    public function testIntegrationOpsCenterCanAcceptCredentialsWithoutSending(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');
        $ui = $this->readFile(self::CLIENT_ROOT . '/lib/simple-stom-ui.js');

        foreach (
            [
                'acceptProviderCredentials',
                'renderProviderAcceptanceAction',
                'pending_acceptance',
                'EspoDental/Integration/acceptProviderCredentials',
                'Живая отправка этим действием не выполняется',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringContainsString('accepted', $ui);
        $this->assertStringContainsString('revoked', $ui);
    }

    public function testDocsTrackCredentialAcceptanceGate(): void
    {
        foreach (
            [
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/integration-architecture.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
                self::ROOT . '/docs/mcp-server-design.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('provider credential acceptance', $doc);
            $this->assertStringContainsString('provider_acceptance_required', $doc);
        }
    }

    private function methodCode(string $code, string $method): string
    {
        $start = strpos($code, 'public function ' . $method);
        $this->assertIsInt($start);

        $next = strpos($code, 'public function ', $start + 1);
        $this->assertIsInt($next);

        return substr($code, $start, $next - $start);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);

        $contents = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($contents, "Invalid JSON: {$path}");

        return $contents;
    }
}
