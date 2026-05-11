<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetadataIntegrityTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const REQUIRED_ENTITIES = ['Clinic', 'Cabinet', 'PreliminaryPatient', 'Patient'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function entityProvider(): iterable
    {
        foreach (self::REQUIRED_ENTITIES as $entity) {
            yield $entity => [$entity];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function localeProvider(): iterable
    {
        foreach (['ru_RU', 'en_US', 'es_ES'] as $locale) {
            yield $locale => [$locale];
        }
    }

    public function testManifestParsesAndHasExpectedKeys(): void
    {
        $manifest = $this->readJson(__DIR__ . '/../src/manifest.json');

        $this->assertSame('EspoDental', $manifest['name']);
        $this->assertArrayHasKey('version', $manifest);
        $this->assertArrayHasKey('acceptableVersions', $manifest);
        $this->assertTrue($manifest['afterInstallScript']);
    }

    #[DataProvider('entityProvider')]
    public function testEntityHasAllRequiredMetadataFiles(string $entity): void
    {
        $files = [
            "Resources/metadata/scopes/{$entity}.json",
            "Resources/metadata/entityDefs/{$entity}.json",
            "Resources/metadata/clientDefs/{$entity}.json",
            "Resources/layouts/{$entity}/detail.json",
            "Resources/layouts/{$entity}/list.json",
        ];

        foreach ($files as $relative) {
            $path = self::MODULE_ROOT . '/' . $relative;
            $this->assertFileExists($path, "Missing: {$relative}");
            $this->readJson($path);
        }
    }

    #[DataProvider('entityProvider')]
    public function testEntityIsRegisteredInAllLocales(string $entity): void
    {
        foreach (['ru_RU', 'en_US', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            $this->assertArrayHasKey('scopeNames', $global);
            $this->assertArrayHasKey($entity, $global['scopeNames'], "Entity {$entity} is not translated in {$locale}");

            $entityI18n = self::MODULE_ROOT . "/Resources/i18n/{$locale}/{$entity}.json";
            $this->assertFileExists($entityI18n, "Missing per-entity i18n: {$locale}/{$entity}.json");
            $data = $this->readJson($entityI18n);
            $this->assertArrayHasKey('fields', $data);
        }
    }

    #[DataProvider('localeProvider')]
    public function testGlobalLocaleHasAllScopes(string $locale): void
    {
        $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");

        foreach (self::REQUIRED_ENTITIES as $entity) {
            $this->assertArrayHasKey($entity, $global['scopeNames']);
            $this->assertArrayHasKey($entity, $global['scopeNamesPlural']);
        }
    }

    public function testEntityDefsForeignLinksAreConsistent(): void
    {
        $clinicDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Clinic.json');
        $cabinetDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Cabinet.json');
        $patientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $prelimDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/PreliminaryPatient.json');

        $this->assertSame('cabinets', $cabinetDefs['links']['clinic']['foreign']);
        $this->assertSame('clinic', $clinicDefs['links']['cabinets']['foreign']);

        $this->assertSame('patients', $patientDefs['links']['clinic']['foreign']);
        $this->assertSame('clinic', $clinicDefs['links']['patients']['foreign']);

        $this->assertSame('convertedToPatient', $patientDefs['links']['convertedFromPreliminary']['foreign']);
        $this->assertSame('convertedFromPreliminary', $prelimDefs['links']['convertedToPatient']['foreign']);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);
        $decoded = json_decode($contents, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), "Invalid JSON: {$path}");

        return $decoded;
    }
}
