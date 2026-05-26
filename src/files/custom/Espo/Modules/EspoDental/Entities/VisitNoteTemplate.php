<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class VisitNoteTemplate extends Entity
{
    public const ENTITY_TYPE = 'VisitNoteTemplate';

    public const SECTION_COMPLAINTS = 'complaints';
    public const SECTION_PERFORMED = 'performed';
    public const SECTION_RECOMMENDATIONS = 'recommendations';
    public const SECTION_TREATMENT_PLAN = 'treatmentPlan';
}
