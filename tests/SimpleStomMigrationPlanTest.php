<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomMigrationPlanTest extends TestCase
{
    private const PLAN = __DIR__ . '/../docs/simple-stom-migration-plan.md';
    private const GAP_MATRIX = __DIR__ . '/../docs/simple-stom-gap-matrix.md';

    public function testMigrationDocumentsAreLinkedFromReadme(): void
    {
        $readme = $this->readFile(__DIR__ . '/../README.md');

        $this->assertStringContainsString('docs/simple-stom-migration-plan.md', $readme);
        $this->assertStringContainsString('docs/simple-stom-gap-matrix.md', $readme);
    }

    public function testPlanTracksCompletedMigrationStages(): void
    {
        $plan = $this->readFile(self::PLAN);

        foreach ([
            '| 0. Migration contract | Completed |',
            '| 1. Gap matrix and acceptance scope | Completed |',
            'docs/simple-stom-gap-matrix.md',
            'MCP/AI behavior is out of scope for this migration run',
        ] as $needle) {
            $this->assertStringContainsString($needle, $plan);
        }
    }

    public function testGapMatrixCoversRequiredScopeCategories(): void
    {
        $matrix = $this->readFile(self::GAP_MATRIX);

        foreach ([
            '## Entity And Data Matrix',
            '## Backend And API Matrix',
            '## UI Screen Matrix',
            '## Visual Scope',
            '## Demo Acceptance Scope',
            '## Stage Acceptance Gates',
            '## Open Decisions',
        ] as $heading) {
            $this->assertStringContainsString($heading, $matrix);
        }

        foreach (['exists', 'extend', 'new', 'defer', 'do-not-port'] as $status) {
            $this->assertStringContainsString($status, $matrix);
        }
    }

    public function testGapMatrixLocksHighRiskMigrationItems(): void
    {
        $matrix = $this->readFile(self::GAP_MATRIX);

        foreach ([
            'AppointmentWaitlistEntry',
            'AppointmentRescheduleRequest',
            'AppointmentSlotHold',
            'PatientPortalSession',
            'InventoryWarehouse',
            'StockLot',
            'CashShift',
            'ReportDefinition',
            'MCP/AI runtime',
            'SimpleStom FastAPI backend',
            'SimpleStom React/Tailwind app shell',
        ] as $item) {
            $this->assertStringContainsString($item, $matrix);
        }
    }

    public function testDemoAcceptanceScopeCoversEndToEndClinicWorkflow(): void
    {
        $matrix = $this->readFile(self::GAP_MATRIX);

        foreach ([
            'SimpleStom-style dashboard',
            'create a preliminary',
            'waitlist',
            'patient workspace',
            'questionnaire',
            'request a reschedule',
            'Start a visit',
            'cash shift',
            'FEFO',
            'payroll',
        ] as $needle) {
            $this->assertStringContainsString($needle, $matrix);
        }
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
