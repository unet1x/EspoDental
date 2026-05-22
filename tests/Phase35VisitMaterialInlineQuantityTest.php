<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase35VisitMaterialInlineQuantityTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testVisitMaterialQuantityUsesInlineRelationshipEditor(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitMaterialLine.json');
        $listSmall = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitMaterialLine/listSmall.json');
        $viewPath = self::CLIENT_ROOT . '/src/views/visit-material-line/fields/quantity-inline.js';
        $view = (string) file_get_contents($viewPath);

        $this->assertSame(
            'espo-dental:views/visit-material-line/fields/quantity-inline',
            $def['fields']['quantity']['view']
        );
        $this->assertSame('quantity', $listSmall[2]['name']);
        $this->assertFileExists($viewPath);
        $this->assertStringContainsString('listTemplateContent: \'{{{value}}}\'', $view);
        $this->assertStringContainsString('quantityInlineInput', $view);
        $this->assertStringContainsString('saveQuantityInline', $view);
        $this->assertStringContainsString('resetQuantityInline', $view);
        $this->assertStringContainsString('getParentVisitModel', $view);
        $this->assertStringContainsString('parent.get(\'status\') !== \'in_progress\'', $view);
        $this->assertStringContainsString('this.getAcl().checkModel(this.model, \'edit\')', $view);
        $this->assertStringContainsString('this.model.save(attrs, {patch: true, wait: true})', $view);
    }

    public function testDocsRecordInlineMaterialQuantitySlice(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $roadmap = (string) file_get_contents(self::ROOT . '/docs/roadmap.md');
        $acceptance = (string) file_get_contents(self::ROOT . '/docs/acceptance-checklist.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('inline quantity editor', $currentState);
        $this->assertStringNotContainsString('add inline quantity editing', $currentState);
        $this->assertStringContainsString('inline quantity editor', $roadmap);
        $this->assertStringContainsString('inline material quantity editor', $acceptance);
        $this->assertStringContainsString('inline material quantity editor', $releaseNotes);
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
