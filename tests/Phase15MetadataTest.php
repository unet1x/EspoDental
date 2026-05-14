<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase15MetadataTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testRoleSeederExists(): void
    {
        $path = self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('class RoleSeeder', $code);
        $this->assertStringContainsString('public function seed', $code);
        $this->assertStringContainsString('roleMatrix', $code);
        foreach (
            ['EspoDental Manager', 'EspoDental Doctor', 'EspoDental Assistant',
                'EspoDental Administrator', 'EspoDental Stock Manager'] as $r
        ) {
            $this->assertStringContainsString($r, $code);
        }
    }

    public function testWorkspaceSeederExists(): void
    {
        $path = self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('class WorkspaceSeeder', $code);
        $this->assertStringContainsString('ensureClinic', $code);
        $this->assertStringContainsString('ensureSettings', $code);
        $this->assertStringContainsString('dashboardLayout', $code);
        $this->assertStringContainsString('Кабинет 5', $code);
    }

    public function testSeedRolesCommandExists(): void
    {
        $path = self::MODULE_ROOT . '/Tools/Console/SeedRolesCommand.php';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('implements Command', $code);
        $this->assertStringContainsString('WorkspaceSeeder', $code);
        $this->assertStringContainsString('Params $params, IO $io', $code);
    }

    public function testConsoleCommandRegistered(): void
    {
        $path = self::MODULE_ROOT . '/Resources/metadata/app/consoleCommands.json';
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('espoDentalSeedRoles', $data);
        $this->assertSame(
            'Espo\\Modules\\EspoDental\\Tools\\Console\\SeedRolesCommand',
            $data['espoDentalSeedRoles']['className']
        );
        $this->assertTrue($data['espoDentalSeedRoles']['listed']);
        $this->assertSame(
            'Espo\\Modules\\EspoDental\\Tools\\Console\\SeedRolesCommand',
            $data['espoDentalBootstrap']['className']
        );
        $this->assertTrue($data['espoDentalBootstrap']['listed']);
    }

    public function testEntityScopesHaveApiControllers(): void
    {
        $scopeFiles = glob(self::MODULE_ROOT . '/Resources/metadata/scopes/*.json') ?: [];
        $this->assertNotEmpty($scopeFiles);

        foreach ($scopeFiles as $scopeFile) {
            $scope = basename($scopeFile, '.json');
            $data = json_decode((string) file_get_contents($scopeFile), true);

            if (!($data['entity'] ?? false)) {
                continue;
            }

            $controllerPath = self::MODULE_ROOT . "/Controllers/{$scope}.php";
            $this->assertFileExists($controllerPath, "{$scope} API controller is missing");
            $this->assertStringContainsString(
                'extends Record',
                (string) file_get_contents($controllerPath),
                "{$scope} API controller should inherit base CRUD actions"
            );
        }
    }

    public function testPatientPreliminaryConversionLinkIsBelongsToField(): void
    {
        $path = self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json';
        $data = json_decode((string) file_get_contents($path), true);

        $this->assertSame('link', $data['fields']['convertedFromPreliminary']['type']);
        $this->assertSame('belongsTo', $data['links']['convertedFromPreliminary']['type']);
        $this->assertSame('PreliminaryPatient', $data['links']['convertedFromPreliminary']['entity']);
    }

    public function testCustomRoutesUseControllerNames(): void
    {
        $path = self::MODULE_ROOT . '/Resources/routes.json';
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);

        foreach ($data as $route) {
            $controller = $route['params']['controller'] ?? '';
            $this->assertIsString($controller);
            $this->assertStringNotContainsString('\\', $controller);
            $this->assertFileExists(self::MODULE_ROOT . "/Controllers/{$controller}.php");
        }
    }

    public function testAfterInstallDelegatesToSeeder(): void
    {
        $code = (string) file_get_contents(self::ROOT . '/src/scripts/AfterInstall.php');
        $this->assertStringContainsString('WorkspaceSeeder', $code);
        $this->assertStringContainsString('->seed()', $code);
        $this->assertLessThan(
            30,
            substr_count($code, "\n"),
            'AfterInstall.php should be thin after the refactor'
        );
    }

    public function testReadmeMentionsSeedCommand(): void
    {
        $code = (string) file_get_contents(self::ROOT . '/README.md');
        $this->assertStringContainsString('espo-dental-bootstrap', $code);
        $this->assertStringContainsString('Releases', $code);
        $this->assertStringContainsString('rebuild.php', $code);
    }

    public function testManifestVersionIsSemver(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(self::ROOT . '/src/manifest.json'),
            true
        );
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            $manifest['version']
        );
        $this->assertGreaterThanOrEqual(
            0,
            version_compare($manifest['version'], '0.15.0'),
            'version must be >= 0.15.0'
        );
    }
}
