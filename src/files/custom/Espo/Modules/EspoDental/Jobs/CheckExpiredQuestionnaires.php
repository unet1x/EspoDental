<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Jobs;

use DateTimeImmutable;
use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\HealthQuestionnaire;
use Espo\Modules\EspoDental\Entities\Patient;

class CheckExpiredQuestionnaires implements Job
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function run(Data $data): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->entityManager
            ->getQueryExecutor()
            ->execute(
                $this->entityManager->getQueryBuilder()
                    ->update()
                    ->in(HealthQuestionnaire::ENTITY_TYPE)
                    ->set(['isExpired' => true])
                    ->where([
                        'expiresAt<' => $now,
                        'isExpired' => false,
                        'deleted' => false,
                    ])
                    ->build()
            );

        $this->entityManager
            ->getQueryExecutor()
            ->execute(
                $this->entityManager->getQueryBuilder()
                    ->update()
                    ->in(Patient::ENTITY_TYPE)
                    ->set(['questionnaireExpired' => true])
                    ->where([
                        'OR' => [
                            ['lastQuestionnaireAt' => null],
                            ['lastQuestionnaireAt<' => (new DateTimeImmutable('-365 days'))->format('Y-m-d H:i:s')],
                        ],
                        'questionnaireExpired' => false,
                        'deleted' => false,
                    ])
                    ->build()
            );
    }
}
