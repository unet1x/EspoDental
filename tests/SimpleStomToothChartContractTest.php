<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomToothChartContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testRendererLocksDentitionRowsAndWholeToothContract(): void
    {
        $renderer = $this->readFile(self::CLIENT_ROOT . '/tooth-chart/renderer.js');

        foreach (
            [
                "ADULT_TOP = ['18'",
                "'28']",
                "ADULT_BOTTOM = ['48'",
                "'38']",
                "CHILD_TOP = ['55'",
                "'65']",
                "CHILD_BOTTOM = ['85'",
                "'75']",
                "dentition === 'mixed'",
                'WHOLE_TOOTH_CONDITIONS',
                "'crown'",
                "'bridge'",
                "'implant'",
                "'extracted'",
                "'missing'",
                'name="tooth-condition"',
                'state.c = toothCondition',
                'allowedTeeth',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $renderer);
        }
    }

    public function testRendererEnforcesSimpleStomSurfaceRules(): void
    {
        $renderer = $this->readFile(self::CLIENT_ROOT . '/tooth-chart/renderer.js');

        foreach (
            [
                'SURFACE_CONDITION_RULES',
                "veneer: ['b']",
                "sealant: ['o']",
                'isSurfaceConditionAllowed',
                'normalizeSurfaceCondition',
                'getSurfaceConditionItems',
                "stroke-dasharray', '5 3'",
                "opacity: isRemoved ? '0.42' : '1'",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $renderer);
        }
    }

    public function testSnapshotMetadataKeepsThreeDentitionTypes(): void
    {
        $entityDef = $this->readJson(
            self::MODULE_ROOT . '/Resources/metadata/entityDefs/ToothChartSnapshot.json'
        );

        $this->assertSame(['adult', 'child', 'mixed'], $entityDef['fields']['dentitionType']['options']);
    }

    public function testPatientWorkspaceEmbedsCurrentSnapshotAndHistory(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/PatientWorkspaceService.php');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/patient-workspace.js');

        foreach (
            [
                'getRecentToothChartSnapshots',
                'summarizeToothChartSnapshot',
                'summarizeTeeth',
                "'currentSnapshot'",
                "'recentSnapshots'",
                "'summary'",
                "'annotatedTeeth'",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }

        foreach (
            [
                'renderToothChartTab',
                'renderSnapshotSummary',
                'renderRecentSnapshots',
                'data-tooth-chart-snapshot',
                'currentSnapshot',
                'recentSnapshots',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringNotContainsString("#ToothChartSnapshot/view/", $view);
    }

    public function testDocsTrackToothChartStage(): void
    {
        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-tooth-chart-contract.md');

        $this->assertStringContainsString('docs/simple-stom-tooth-chart-contract.md', $readme);
        $this->assertStringContainsString('| 9. Tooth chart contract | Completed |', $plan);
        $this->assertStringContainsString('veneer', $doc);
        $this->assertStringContainsString('sealant', $doc);
        $this->assertStringContainsString('currentSnapshot', $doc);
        $this->assertStringContainsString('recentSnapshots', $doc);
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
        $this->assertIsArray($contents);

        return $contents;
    }
}
