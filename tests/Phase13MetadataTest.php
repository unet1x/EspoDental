<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase13MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = __DIR__ . '/../src/files/client/custom/modules/espo-dental/src';

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: $path");
        return $data;
    }

    public function testFreeSlotsRoute(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $paths = array_column($routes, 'route');
        $this->assertContains('/EspoDental/Calendar/freeSlots', $paths);
        foreach ($routes as $r) {
            if ($r['route'] === '/EspoDental/Calendar/freeSlots') {
                $this->assertSame('get', $r['method']);
                $this->assertSame('freeSlots', $r['params']['action']);
            }
        }
    }

    public function testCalendarServiceHasFindFreeSlots(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/CalendarService.php');
        $this->assertStringContainsString('public function findFreeSlots', $code);
        $this->assertStringContainsString('Appointment::BLOCKING_STATUSES', $code);
        $this->assertStringContainsString('overlapsAny', $code);
        $this->assertStringContainsString('excludeAppointmentId', $code);
        $this->assertStringContainsString('patientKey', $code);
        $this->assertStringContainsString('$occByPatient', $code);
        $this->assertStringNotContainsString('$appWhere[\'cabinetId\']', $code);
        $this->assertStringNotContainsString('$appWhere[\'doctorId\']', $code);
        $this->assertStringContainsString('localStart', $code);
        $this->assertStringContainsString('localEnd', $code);
        $this->assertStringContainsString('resolveTimeZone', $code);
        $this->assertStringContainsString('Clinic::ENTITY_TYPE', $code);
    }

    public function testCalendarServiceGetDataAcceptsCabinetId(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/CalendarService.php');
        $this->assertMatchesRegularExpression(
            '/getDayData\s*\(\s*string\s+\$date,\s*\?string\s+\$clinicId,\s*string\s+\$view[^,]*,\s*\?string\s+\$cabinetId/s',
            $code
        );
    }

    public function testControllerActionFreeSlots(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Calendar.php');
        $this->assertStringContainsString('getActionFreeSlots', $code);
        $this->assertStringContainsString('durationMinutes', $code);
        $this->assertStringContainsString('cabinetId', $code);
        $this->assertStringContainsString('excludeAppointmentId', $code);
        $this->assertStringContainsString('parentType', $code);
        $this->assertStringContainsString('parentId', $code);
    }

    public function testDashletExposesWeekViewAndCabinetFilter(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/dashlets/ResourceCalendar.json');
        $this->assertArrayHasKey('defaultView', $def['options']['fields']);
        $this->assertContains('week', $def['options']['fields']['defaultView']['options']);
        $this->assertArrayHasKey('cabinet', $def['options']['fields']);
    }

    public function testResourceGridHasWeekAndResizeSupport(): void
    {
        $code = (string) file_get_contents(self::CLIENT_ROOT . '/lib/resource-grid.js');
        $this->assertStringContainsString('rc-resizer', $code);
        $this->assertStringContainsString('dayCount', $code);
        $this->assertStringContainsString('onResize', $code);
        $this->assertStringContainsString("payload.view === 'week'", $code);
    }

    public function testDashletViewSupportsViewSwitcherAndFindSlot(): void
    {
        $code = (string) file_get_contents(self::CLIENT_ROOT . '/views/dashlets/resource-calendar.js');
        $this->assertStringContainsString('viewSelect', $code);
        $this->assertStringContainsString('openFindSlot', $code);
        $this->assertStringContainsString('handleResize', $code);
        $this->assertStringContainsString('EspoDental/Calendar/freeSlots', $code);
    }
}
