<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class PatientPortalEvent extends Entity
{
    public const ENTITY_TYPE = 'PatientPortalEvent';

    public const ACTION_REQUEST_CODE = 'request_code';
    public const ACTION_VERIFY_CODE = 'verify_code';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LIST_APPOINTMENTS = 'list_appointments';
    public const ACTION_CREATE_RESCHEDULE_REQUEST = 'create_reschedule_request';
    public const ACTION_CANCEL_RESCHEDULE_REQUEST = 'cancel_reschedule_request';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILURE = 'failure';
    public const STATUS_BLOCKED = 'blocked';
}
