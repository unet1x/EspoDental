<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\EspoDental\Tools\Installer\WorkspaceSeeder;

class AfterInstall
{
    public function run(Container $container): void
    {
        /** @var EntityManager $em */
        $em = $container->getByClass(EntityManager::class);
        /** @var ConfigWriter $configWriter */
        $configWriter = $container->getByClass(ConfigWriter::class);
        (new WorkspaceSeeder($em, $configWriter))->seed();
    }
}
