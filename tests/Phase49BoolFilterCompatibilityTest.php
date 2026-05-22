<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase49BoolFilterCompatibilityTest extends TestCase
{
    private const SELECT_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental/Classes/Select';

    public function testRawBoolFilterUsesEspoCrmNineSignature(): void
    {
        $raw = (string) file_get_contents(self::SELECT_ROOT . '/Common/RawBoolFilter.php');
        $userAware = (string) file_get_contents(self::SELECT_ROOT . '/Common/UserAwareRawBoolFilter.php');

        $this->assertStringContainsString('implements Filter', $raw);
        $this->assertStringContainsString(
            'apply(QueryBuilder $queryBuilder, OrGroupBuilder $orGroupBuilder): void',
            $raw
        );
        $this->assertStringContainsString('$orGroupBuilder->add($this->buildWhereItem());', $raw);
        $this->assertStringContainsString('protected User $user', $userAware);
    }

    public function testModuleBoolFiltersDoNotUseRemovedApplyUserSignature(): void
    {
        $files = glob(self::SELECT_ROOT . '/*/BoolFilters/*.php');
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $code = (string) file_get_contents($file);

            $this->assertStringNotContainsString('function apply(User $user)', $code, $file);
            $this->assertStringNotContainsString('implements Filter', $code, $file);
            $this->assertMatchesRegularExpression(
                '/extends (RawBoolFilter|UserAwareRawBoolFilter)/',
                $code,
                $file
            );
            $this->assertStringContainsString('buildWhereItem(): WhereItem', $code, $file);
        }
    }
}
