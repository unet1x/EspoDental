<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Common;

use DateTimeImmutable;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

final class DateRangeHelper
{
    public static function today(string $column): WhereItem
    {
        $from = (new DateTimeImmutable('today'))->format('Y-m-d H:i:s');
        $to = (new DateTimeImmutable('tomorrow'))->format('Y-m-d H:i:s');
        return self::between($column, $from, $to);
    }

    public static function thisWeek(string $column): WhereItem
    {
        $from = (new DateTimeImmutable('monday this week'))->format('Y-m-d H:i:s');
        $to = (new DateTimeImmutable('monday next week'))->format('Y-m-d H:i:s');
        return self::between($column, $from, $to);
    }

    public static function thisMonth(string $column): WhereItem
    {
        $from = (new DateTimeImmutable('first day of this month 00:00'))->format('Y-m-d H:i:s');
        $to = (new DateTimeImmutable('first day of next month 00:00'))->format('Y-m-d H:i:s');
        return self::between($column, $from, $to);
    }

    public static function between(string $column, string $from, string $to): WhereItem
    {
        return WhereClause::fromRaw([
            "{$column}>=" => $from,
            "{$column}<" => $to,
        ]);
    }
}
