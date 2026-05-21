<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Console;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\EspoDental\Tools\Installer\WorkspaceSeeder;

/**
 * CLI entry-point that seeds a ready-to-work EspoDental workspace. Use it for
 * Docker volume-mount installs where the extension installer flow is not used.
 *
 * Run:
 *   php command.php espo-dental-bootstrap
 */
class SeedRolesCommand implements Command
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ConfigWriter $configWriter
    ) {
    }

    public function run(Params $params, IO $io): void
    {
        $io->writeLine('Seeding EspoDental workspace...');
        $result = (new WorkspaceSeeder($this->entityManager, $this->configWriter))->seed();
        $io->writeLine(sprintf(
            'Done. Created %d team(s), %d role(s), %d service category(-ies), %d clinic(s), %d cabinet(s), ' .
            '%d material category(-ies), %d service(s), %d material(s), %d service material link(s), ' .
            '%d stock movement(s), %d scheduled job(s), %d dashboard template(s). Settings updated: %s. ' .
            'Backfilled %d clinical line name(s), %d visit name(s), %d tooth chart name(s), ' .
            '%d child flag(s), %d patient balance(s).',
            $result['teams'],
            $result['roles'],
            $result['serviceCategories'],
            $result['clinics'],
            $result['cabinets'],
            $result['materialCategories'],
            $result['services'],
            $result['materials'],
            $result['serviceMaterials'],
            $result['stockMovements'],
            $result['scheduledJobs'],
            $result['dashboardTemplates'],
            $result['settings'] > 0 ? 'yes' : 'no',
            $result['clinicalLineNames'],
            $result['visitNames'],
            $result['toothChartNames'],
            $result['childFlags'],
            $result['patientBalances']
        ));
        $io->writeLine('Re-run is safe: the command is idempotent.');
    }
}
