<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Common;

use Espo\Entities\User;

abstract class UserAwareRawBoolFilter extends RawBoolFilter
{
    public function __construct(protected User $user)
    {
    }
}
