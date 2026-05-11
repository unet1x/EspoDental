<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Jobs;

use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Modules\EspoDental\Services\ReminderService;

class SendAppointmentReminders implements Job
{
    public function __construct(private readonly ReminderService $reminderService)
    {
    }

    public function run(Data $data): void
    {
        $this->reminderService->sendDueReminders();
    }
}
