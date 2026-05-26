<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase11MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = __DIR__ . '/../src/files/client/custom/modules/espo-dental/src';
    private const LOCALES = ['ru_RU', 'en_US', 'es_ES'];

    public function testCalendarServiceExists(): void
    {
        $path = self::MODULE_ROOT . '/Services/CalendarService.php';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('getDayData', $code);
        $this->assertStringContainsString('moveAppointment', $code);
    }

    public function testCalendarControllerExists(): void
    {
        $path = self::MODULE_ROOT . '/Controllers/Calendar.php';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('getActionAppointments', $code);
        $this->assertStringContainsString('postActionMove', $code);
    }

    public function testCalendarRoutesRegistered(): void
    {
        $routes = json_decode((string) file_get_contents(self::MODULE_ROOT . '/Resources/routes.json'), true);
        $paths = array_column($routes, 'route');
        $this->assertContains('/EspoDental/Calendar/appointments', $paths);
        $this->assertContains('/EspoDental/Calendar/move', $paths);
    }

    public function testDashletMetadataExists(): void
    {
        $path = self::MODULE_ROOT . '/Resources/metadata/dashlets/ResourceCalendar.json';
        $this->assertFileExists($path);
        $def = json_decode((string) file_get_contents($path), true);
        $this->assertSame('espo-dental:views/dashlets/resource-calendar-feedback', $def['view']);
        $this->assertArrayHasKey('rowMinutes', $def['options']['fields']);
        $this->assertArrayHasKey('startHour', $def['options']['fields']);
        $this->assertArrayHasKey('endHour', $def['options']['fields']);
    }

    public function testDashletViewExists(): void
    {
        $path = self::CLIENT_ROOT . '/views/dashlets/resource-calendar.js';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('EspoDental/Calendar/appointments', $code);
        $this->assertStringContainsString('EspoDental/Calendar/move', $code);
        $this->assertStringContainsString('handleMove', $code);
        $this->assertStringContainsString('handleCellClick', $code);
    }

    public function testResourceGridLibraryExists(): void
    {
        $path = self::CLIENT_ROOT . '/lib/resource-grid.js';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('ResourceGrid', $code);
        $this->assertStringContainsString('STATUS_COLORS', $code);
        $this->assertStringContainsString('dragstart', $code);
        $this->assertStringContainsString('drop', $code);
    }

    public function testGlobalLocalesIncludeDashlet(): void
    {
        foreach (self::LOCALES as $locale) {
            $g = json_decode(
                (string) file_get_contents(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json"),
                true
            );
            $this->assertArrayHasKey('ResourceCalendar', $g['dashlets']);
        }
    }

    public function testCalendarServiceUsesClinicFilter(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/CalendarService.php');
        $this->assertStringContainsString('clinicId', $code);
        $this->assertStringContainsString('Cabinet::ENTITY_TYPE', $code);
        $this->assertStringContainsString('Appointment::ENTITY_TYPE', $code);
    }

    public function testMoveAppointmentValidates(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/CalendarService.php');
        $this->assertStringContainsString('dateEnd must be after dateStart', $code);
        $this->assertStringContainsString('throw new BadRequest', $code);
        $this->assertStringContainsString('throw new NotFound', $code);
    }
}
