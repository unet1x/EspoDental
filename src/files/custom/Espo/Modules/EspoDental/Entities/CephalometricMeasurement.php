<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class CephalometricMeasurement extends Entity
{
    public const ENTITY_TYPE = 'CephalometricMeasurement';

    public const UNIT_DEGREE = 'degree';
    public const UNIT_MM = 'mm';
    public const UNIT_RATIO = 'ratio';

    public const PHASE_INITIAL = 'initial';
    public const PHASE_INTERIM = 'interim';
    public const PHASE_FINAL = 'final';

    public const NORMAL_RANGES = [
        'SNA' => '82±3',
        'SNB' => '80±3',
        'ANB' => '2±2',
        'FMA' => '25±3',
        'SN_GoGn' => '32±3',
        'U1_SN' => '103±5',
        'U1_NA' => '22±3',
        'L1_NB' => '25±3',
        'L1_GoGn' => '90±5',
        'IMPA' => '95±7',
        'Interincisal' => '131±10',
    ];

    public function getCode(): ?string
    {
        return $this->get('code');
    }

    public function getValue(): float
    {
        return (float) $this->get('value', 0);
    }
}
