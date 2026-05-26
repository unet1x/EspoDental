<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Console;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\EspoDental\Tools\Installer\DemoSeeder;
use Espo\Modules\EspoDental\Tools\Installer\WorkspaceSeeder;

/**
 * Optional local-demo data entry point for SimpleStom migration acceptance.
 *
 * Run:
 *   php command.php espo-dental-demo-seed
 */
class DemoSeedCommand implements Command
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ConfigWriter $configWriter
    ) {
    }

    public function run(Params $params, IO $io): void
    {
        $io->writeLine('Preparing EspoDental workspace before demo data...');
        (new WorkspaceSeeder($this->entityManager, $this->configWriter))->seed();

        $io->writeLine('Seeding SimpleStom demo data...');
        $result = (new DemoSeeder($this->entityManager))->seed();

        $io->writeLine(sprintf(
            'Demo ready. Created %d user(s), %d shift(s), %d patient/lead record(s), %d appointment(s), ' .
            '%d waitlist/proposal record(s), %d questionnaire/portal record(s), %d visit(s), ' .
            '%d clinical line/chart record(s), %d invoice/shift record(s), %d payment(s), ' .
            '%d inventory record(s), %d payroll record(s), %d integration record(s).',
            $result['users'],
            $result['doctorShifts'],
            $result['patients'],
            $result['appointments'],
            $result['waitlistEntries'],
            $result['questionnaires'],
            $result['visits'],
            $result['clinicalLines'],
            $result['invoices'],
            $result['payments'],
            $result['inventoryRecords'],
            $result['payrollRecords'],
            $result['integrationRecords']
        ));
        $io->writeLine('Re-run is safe: demo records are matched by stable DEMO markers.');
    }
}
