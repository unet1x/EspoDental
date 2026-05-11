<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class OrthoPhoto extends Entity
{
    public const ENTITY_TYPE = 'OrthoPhoto';

    public const PHASE_INITIAL = 'initial';
    public const PHASE_INTERIM = 'interim';
    public const PHASE_FINAL = 'final';
    public const PHASE_RETENTION = 'retention';

    public const EXTRA_TYPES = ['extra_front', 'extra_smile', 'extra_profile', 'extra_3q'];
    public const INTRA_TYPES = [
        'intra_front', 'intra_lateral_right', 'intra_lateral_left',
        'intra_upper_occlusal', 'intra_lower_occlusal',
    ];
    public const XRAY_TYPES = ['xray_panoramic', 'xray_cephalometric'];
    public const MODEL_TYPES = ['model_upper', 'model_lower'];

    public function getType(): ?string
    {
        return $this->get('type');
    }

    public function getPhase(): string
    {
        return (string) $this->get('phase', self::PHASE_INITIAL);
    }

    public function isXray(): bool
    {
        return in_array((string) $this->getType(), self::XRAY_TYPES, true);
    }
}
