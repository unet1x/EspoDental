<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase50OperationalLanguageTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testSimpleStomUiProvidesOperationalRussianLabels(): void
    {
        $ui = $this->readFile(self::CLIENT_ROOT . '/lib/simple-stom-ui.js');

        foreach (
            [
                'labelGroups',
                "Patient: 'карта пациента'",
                "PreliminaryPatient: 'предварительный пациент'",
                "adult: 'взрослый'",
                "whole: 'весь зуб'",
                "healthy: 'здоров'",
                "issued: 'выставлен'",
                "ok: 'норма'",
                'formatValue',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $ui);
        }
    }

    public function testSimpleStomWorkspacesDoNotExposeRawReferenceLabels(): void
    {
        $patientWorkspace = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/patient-workspace.js');
        $slotBooking = $this->readFile(self::CLIENT_ROOT . '/views/appointment/modals/slot-booking.js');
        $cashDesk = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/cash-desk-workspace.js');
        $inventory = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/inventory-status.js');

        $this->assertStringContainsString("SimpleStomUi.label(current.dentitionType || 'adult', 'dentition')", $patientWorkspace);
        $this->assertStringContainsString("SimpleStomUi.label(row.surface || 'whole', 'surface')", $patientWorkspace);
        $this->assertStringContainsString("SimpleStomUi.label(row.condition || '', 'condition')", $patientWorkspace);
        $this->assertStringContainsString("SimpleStomUi.label(row.entityType || '', 'entity')", $slotBooking);
        $this->assertStringContainsString("SimpleStomUi.badge(invoice.status || 'issued'", $cashDesk);
        $this->assertStringContainsString("SimpleStomUi.badge(row.stockLevel || 'normal'", $inventory);
    }

    public function testLegacyCalendarDashletUsesRussianOperationalText(): void
    {
        $calendar = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/resource-calendar.js');

        foreach (['Today', 'Day</option>', 'Week</option>', 'Find slot', 'Update failed'] as $needle) {
            $this->assertStringNotContainsString($needle, $calendar);
        }

        foreach (['Сегодня', 'День</option>', 'Неделя</option>', 'Найти слот', 'Не удалось обновить запись.'] as $needle) {
            $this->assertStringContainsString($needle, $calendar);
        }
    }

    public function testManagerReportDashletsUseRussianFallbackTextAndHeaders(): void
    {
        foreach (
            [
                '/views/dashlets/no-show-cancellations.js',
                '/views/dashlets/cabinet-utilization.js',
                '/views/dashlets/doctor-productivity.js',
                '/views/dashlets/monthly-revenue.js',
            ] as $file
        ) {
            $source = $this->readFile(self::CLIENT_ROOT . $file);

            foreach (['Loading...', 'Failed to load.', 'No data.'] as $needle) {
                $this->assertStringNotContainsString($needle, $source);
            }

            $this->assertStringContainsString('Загрузка отчета...', $source);
            $this->assertStringContainsString('Не удалось загрузить отчет.', $source);
            $this->assertStringContainsString('Данных пока нет.', $source);
        }

        $this->assertStringContainsString('Проблемные, %', $this->readFile(self::CLIENT_ROOT . '/views/dashlets/no-show-cancellations.js'));
        $this->assertStringContainsString('Загрузка', $this->readFile(self::CLIENT_ROOT . '/views/dashlets/cabinet-utilization.js'));
        $this->assertStringContainsString('Средний чек', $this->readFile(self::CLIENT_ROOT . '/views/dashlets/doctor-productivity.js'));
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
