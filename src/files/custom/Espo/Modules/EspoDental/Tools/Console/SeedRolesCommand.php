<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Console;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Tools\Installer\RoleSeeder;

/**
 * CLI entry-point that seeds EspoDental teams, roles and starter service
 * categories. Use it for Docker volume-mount installs where the extension
 * installer flow is not used.
 *
 * Run:
 *   php command.php espo-dental-seed-roles
 */
class SeedRolesCommand implements Command
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function run(Params $params, IO $io): void
    {
        $io->writeLine('Seeding EspoDental teams, roles, service categories...');
        $result = (new RoleSeeder($this->entityManager))->seed();
        $io->writeLine(sprintf(
            'Done. Created %d team(s), %d role(s), %d service category(-ies).',
            $result['teams'],
            $result['roles'],
            $result['serviceCategories']
        ));
        $io->writeLine('Re-run is safe: the command is idempotent.');
    }
}
