<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase48LocalDashboardCompatibilityTest extends TestCase
{
    private const CLIENT_DASHLET_ROOT = __DIR__
        . '/../src/files/client/custom/modules/espo-dental/src/views/dashlets';

    /**
     * @return iterable<string, array{string}>
     */
    public static function recordListDashletProvider(): iterable
    {
        $dashlets = [
            'active-ortho-cases',
            'low-stock-materials',
            'open-invoices',
            'payroll-this-month',
            'recent-visits',
            'todays-appointments',
        ];

        foreach ($dashlets as $name) {
            yield $name => [$name];
        }
    }

    public function testLocalRecordListDashletBridgeTargetsEspoCrmNineAbstractView(): void
    {
        $bridge = (string) file_get_contents(self::CLIENT_DASHLET_ROOT . '/record-list.js');

        $this->assertStringContainsString(
            "define('espo-dental:views/dashlets/record-list'",
            $bridge
        );
        $this->assertStringContainsString("'views/dashlets/abstract/record-list'", $bridge);
        $this->assertStringContainsString('setupDefaultOptions', $bridge);
        $this->assertStringContainsString('expandedLayout', $bridge);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('recordListDashletProvider')]
    public function testRecordListDashletsUseModuleBridgeInsteadOfRemovedCoreAlias(string $dashlet): void
    {
        $code = (string) file_get_contents(self::CLIENT_DASHLET_ROOT . '/' . $dashlet . '.js');

        $this->assertStringContainsString("'espo-dental:views/dashlets/record-list'", $code);
        $this->assertStringNotContainsString("'views/dashlets/record-list'", $code);
        $this->assertStringContainsString('getListLayout', $code);
    }
}
