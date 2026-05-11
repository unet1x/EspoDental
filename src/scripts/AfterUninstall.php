<?php

declare(strict_types=1);

use Espo\Core\Container;

class AfterUninstall
{
    public function run(Container $container): void
    {
        // Roles and teams created by AfterInstall are kept on purpose:
        // the user may have assigned real people to them; removing
        // would break their access. They can be cleaned up manually
        // from Administration -> Roles / Teams.
    }
}
