<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase28RoleWorkspacesTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testRoleDashboardTemplatesAreSeeded(): void
    {
        $code = $this->workspaceSeeder();

        $this->assertStringContainsString('ensureDashboardTemplates', $code);
        $this->assertStringContainsString('dashboardTemplates', $code);

        foreach (
            [
                'EspoDental: рабочее место клиники',
                'EspoDental: администратор',
                'EspoDental: врач',
                'EspoDental: ассистент',
                'EspoDental: менеджер',
                'EspoDental: склад',
            ] as $templateName
        ) {
            $this->assertStringContainsString($templateName, $code);
        }

        foreach (
            [
                'administratorDashboardLayout',
                'doctorDashboardLayout',
                'assistantDashboardLayout',
                'managerDashboardLayout',
                'stockDashboardLayout',
            ] as $method
        ) {
            $this->assertStringContainsString($method, $code);
        }
    }

    public function testRoleDashboardsDoNotExposeIrrelevantFinanceWidgets(): void
    {
        $code = $this->workspaceSeeder();
        $doctor = $this->methodCode($code, 'doctorDashboardLayout');
        $assistant = $this->methodCode($code, 'assistantDashboardLayout');
        $stock = $this->methodCode($code, 'stockDashboardLayout');
        $manager = $this->methodCode($code, 'managerDashboardLayout');

        foreach (['TodaysAppointments', 'RecentVisits'] as $dashlet) {
            $this->assertStringContainsString($dashlet, $doctor);
            $this->assertStringContainsString($dashlet, $assistant);
        }

        foreach (['MonthlyRevenue', 'OpenInvoices', 'PayrollThisMonth'] as $financeDashlet) {
            $this->assertStringNotContainsString($financeDashlet, $doctor);
            $this->assertStringNotContainsString($financeDashlet, $assistant);
            $this->assertStringNotContainsString($financeDashlet, $stock);
        }

        $this->assertStringContainsString('LowStockMaterials', $stock);
        $this->assertStringNotContainsString('ResourceCalendar', $stock);
        $this->assertStringContainsString('MonthlyRevenue', $manager);
        $this->assertStringContainsString('OpenInvoices', $manager);
        $this->assertStringContainsString('PayrollThisMonth', $manager);
        $this->assertStringContainsString('LowStockMaterials', $manager);
    }

    public function testRoleAclMatrixMatchesWorkspaceSafety(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');

        $this->assertStringContainsString("'Payment'              => \$row('no', 'team', 'no', 'no', 'team')", $code);
        $this->assertStringContainsString("'Payment'              => \$row('no', 'team', 'no', 'no', 'no')", $code);
        $this->assertStringContainsString(
            "'Payment'              => \$row('yes', 'team', 'team', 'no', 'team')",
            $code
        );
        $this->assertStringContainsString("'Payment'              => \$row('no', 'no', 'no', 'no', 'no')", $code);
        $this->assertStringContainsString("'StockMovement'        => \$row('yes', 'all', 'no', 'no', 'no')", $code);
    }

    public function testDocsRecordRoleWorkspaceSlice(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $roadmap = (string) file_get_contents(self::ROOT . '/docs/roadmap.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('role-specific dashboard templates', $currentState);
        $this->assertStringContainsString('role-specific dashboard templates', $roadmap);
        $this->assertStringContainsString('Role workspace templates', $releaseNotes);
    }

    private function workspaceSeeder(): string
    {
        return (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
    }

    private function methodCode(string $code, string $method): string
    {
        $start = strpos($code, 'private function ' . $method);
        $this->assertIsInt($start);

        $next = strpos($code, 'private function ', $start + 1);
        $this->assertIsInt($next);

        return substr($code, $start, $next - $start);
    }
}
