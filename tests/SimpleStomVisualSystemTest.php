<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomVisualSystemTest extends TestCase
{
    private const UI_KIT = __DIR__ . '/../src/files/client/custom/modules/espo-dental/src/lib/simple-stom-ui.js';
    private const VISUAL_DOC = __DIR__ . '/../docs/simple-stom-visual-system.md';

    public function testUiKitDefinesScopedSimpleStomTokens(): void
    {
        $js = $this->readFile(self::UI_KIT);

        foreach ([
            "define('espo-dental:lib/simple-stom-ui'",
            "styleId = 'espo-dental-simple-stom-ui'",
            "background: '#edf3ef'",
            "primary: '#438f7e'",
            "radius: '8px'",
            '.espo-dental-stom{',
            '.espo-dental-stom-panel',
            '.espo-dental-stom-table',
            '.espo-dental-stom-badge',
            '.espo-dental-stom-button',
        ] as $needle) {
            $this->assertStringContainsString($needle, $js);
        }
    }

    public function testUiKitExposesReusableWorkspaceHelpers(): void
    {
        $js = $this->readFile(self::UI_KIT);

        foreach ([
            'ensureStyles: ensureStyles',
            'workspace: workspace',
            'panel: panel',
            'badge: badge',
            'button: button',
            'emptyState: emptyState',
            'statusClasses: statusClasses',
            'riskClasses: riskClasses',
        ] as $needle) {
            $this->assertStringContainsString($needle, $js);
        }
    }

    public function testVisualSystemDocumentLocksScopeAndRules(): void
    {
        $doc = $this->readFile(self::VISUAL_DOC);

        foreach ([
            'simple-stom-ui.js',
            'not a global EspoCRM theme',
            '.espo-dental-stom',
            '#edf3ef',
            '#438f7e',
            '8px',
            'dense operational split panels',
            'Do not use viewport-width font scaling',
            'Stage 3 dashboard action center',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testMigrationPlanAndReadmeLinkVisualSystem(): void
    {
        $readme = $this->readFile(__DIR__ . '/../README.md');
        $plan = $this->readFile(__DIR__ . '/../docs/simple-stom-migration-plan.md');

        $this->assertStringContainsString('docs/simple-stom-visual-system.md', $readme);
        $this->assertStringContainsString('docs/simple-stom-visual-system.md', $plan);
        $this->assertStringContainsString('| 2. Visual system foundation | Completed |', $plan);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
