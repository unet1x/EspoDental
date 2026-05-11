<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class ToothChartSnapshot extends Entity
{
    public const ENTITY_TYPE = 'ToothChartSnapshot';

    public const DENTITION_ADULT = 'adult';
    public const DENTITION_CHILD = 'child';
    public const DENTITION_MIXED = 'mixed';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTeeth(): array
    {
        $value = $this->get('teeth');
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (is_object($value)) {
            $value = (array) $value;
        }
        return is_array($value) ? $value : [];
    }
}
