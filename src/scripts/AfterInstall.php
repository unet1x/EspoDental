<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Tools\Installer\RoleSeeder;

class AfterInstall
{
    public function run(Container $container): void
    {
        /** @var EntityManager $em */
        $em = $container->getByClass(EntityManager::class);
        (new RoleSeeder($em))->seed();
    }
}
