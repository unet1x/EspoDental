<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase34VisitServiceCatalogTreeTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testVisitServiceLineUsesExpandableCatalogTree(): void
    {
        $clientDef = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/VisitServiceLine.json');
        $viewPath = self::CLIENT_ROOT . '/src/views/visit-service-line/record/edit.js';
        $view = (string) file_get_contents($viewPath);

        $this->assertSame(
            'espo-dental:views/visit-service-line/record/edit',
            $clientDef['recordViews']['edit']
        );
        $this->assertStringContainsString('serviceCatalogTree', $view);
        $this->assertStringContainsString('serviceCatalogSearch', $view);
        $this->assertStringContainsString('serviceCategoryToggle', $view);
        $this->assertStringContainsString('serviceCatalogItem', $view);
        $this->assertStringContainsString('catalogExpandedCategoryIds', $view);
        $this->assertStringContainsString('matchesServiceFilter', $view);
        $this->assertStringContainsString('renderServiceCatalogItems', $view);
        $this->assertStringContainsString('renderServiceMeta', $view);
        $this->assertStringContainsString('this.model.set({', $view);
        $this->assertStringContainsString('serviceId: service.id', $view);
        $this->assertStringContainsString('serviceName: service.name', $view);
        $this->assertStringContainsString('applyServicePrice(service)', $view);
    }

    public function testLegacyTwoSelectPickerIsRemoved(): void
    {
        $view = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/views/visit-service-line/record/edit.js'
        );

        $this->assertStringNotContainsString('serviceCategoryPicker', $view);
        $this->assertStringNotContainsString('servicePicker', $view);
        $this->assertStringNotContainsString('populateCategoryPicker', $view);
        $this->assertStringNotContainsString('populateServicePicker', $view);
    }

    public function testCatalogTreeLabelsAreLocalized(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $labels = $this->readJson(
                self::MODULE_ROOT . "/Resources/i18n/{$locale}/VisitServiceLine.json"
            )['labels'];

            foreach (['Search service', 'No matching services', 'No services in category'] as $label) {
                $this->assertArrayHasKey($label, $labels);
            }
        }
    }

    public function testDocsRecordCatalogTreeSlice(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');
        $acceptance = (string) file_get_contents(self::ROOT . '/docs/acceptance-checklist.md');

        $this->assertStringContainsString('expandable service catalog tree', $currentState);
        $this->assertStringContainsString('category tree', $releaseNotes);
        $this->assertStringContainsString('expandable service catalog tree', $acceptance);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: {$path}");

        return $data;
    }
}
