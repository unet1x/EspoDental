<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase41RoleDashboardAssignmentTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testBootstrapAssignsRoleSpecificDashboardTemplatesToRoleUsers(): void
    {
        $code = $this->workspaceSeeder();

        $this->assertStringContainsString('ROLE_DASHBOARD_TEMPLATES', $code);
        $this->assertStringContainsString('TEAM_DASHBOARD_TEMPLATES', $code);
        $this->assertStringContainsString("'EspoDental Manager' => 'EspoDental: менеджер'", $code);
        $this->assertStringContainsString("'EspoDental Administrator' => 'EspoDental: администратор'", $code);
        $this->assertStringContainsString("'EspoDental Doctor' => 'EspoDental: врач'", $code);
        $this->assertStringContainsString("'EspoDental Assistant' => 'EspoDental: ассистент'", $code);
        $this->assertStringContainsString("'EspoDental Stock Manager' => 'EspoDental: склад'", $code);
        $this->assertStringContainsString("'EspoDental Managers' => 'EspoDental: менеджер'", $code);
        $this->assertStringContainsString("'EspoDental Doctors' => 'EspoDental: врач'", $code);

        $this->assertStringContainsString('ensureDashboardTemplateAssignments', $code);
        $this->assertStringContainsString('dashboardTemplateAssignments', $code);
        $this->assertStringContainsString('User::TYPE_REGULAR', $code);
        $this->assertStringContainsString("if (\$user->get('dashboardTemplateId'))", $code);
        $this->assertStringContainsString("findDashboardTemplateForUser(\$user, User::LINK_ROLES", $code);
        $this->assertStringContainsString("findDashboardTemplateForUser(\$user, User::LINK_TEAMS", $code);
        $this->assertStringContainsString('getLinkMultipleIdList($link)', $code);
        $this->assertStringContainsString("\$user->set('dashboardTemplateId', \$template->getId())", $code);
    }

    public function testBootstrapOutputReportsDashboardTemplateAssignments(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Console/SeedRolesCommand.php');

        $this->assertStringContainsString('assigned %d dashboard template(s) to role user(s)', $code);
        $this->assertStringContainsString("\$result['dashboardTemplateAssignments']", $code);
    }

    public function testDocsRecordRoleDashboardAssignmentVerification(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $acceptanceChecklist = (string) file_get_contents(self::ROOT . '/docs/acceptance-checklist.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('Role-specific dashboard templates are assigned', $currentState);
        $this->assertStringContainsString(
            'Confirm each EspoDental role or role-team user receives the matching',
            $acceptanceChecklist
        );
        $this->assertStringContainsString('Bootstrap now assigns role-specific dashboard templates', $releaseNotes);
    }

    private function workspaceSeeder(): string
    {
        return (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
    }
}
