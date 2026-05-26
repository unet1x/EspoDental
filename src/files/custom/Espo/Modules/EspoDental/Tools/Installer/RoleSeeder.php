<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Installer;

use Espo\Core\ORM\EntityManager;
use Espo\Entities\Role;
use Espo\Entities\Team;

/**
 * Idempotent installer that seeds teams, roles and starter service categories
 * required by EspoDental. Invoked from AfterInstall.php (Extensions UI) and
 * from SeedRolesCommand (CLI for Docker volume-mount installs).
 */
class RoleSeeder
{
    /** @var list<string> */
    public const TEAMS = [
        'EspoDental Doctors',
        'EspoDental Assistants',
        'EspoDental Administrators',
        'EspoDental Managers',
        'EspoDental Stock Managers',
    ];

    /** @var list<string> */
    public const SCOPES = [
        'Clinic', 'Cabinet',
        'PreliminaryPatient', 'Patient',
        'Appointment', 'AppointmentWaitlistEntry', 'AppointmentRescheduleRequest',
        'PatientPortalSession', 'PatientPortalEvent',
        'DoctorShiftTemplate', 'DoctorShift', 'AppointmentStatusLog',
        'Visit', 'ServiceCategory', 'Service', 'VisitServiceLine', 'VisitMaterialLine',
        'ToothChartSnapshot', 'VisitPhoto',
        'Invoice', 'InvoiceLine', 'Payment',
        'MaterialCategory', 'Material', 'StockMovement', 'ServiceMaterial',
        'LowStockAlert', 'NotificationLog', 'AssistantActionProposal',
        'SalaryProfile', 'SalaryEntry', 'SalaryBonus',
        'OrthodonticCard', 'TreatmentStage', 'ToothMovementPlan',
        'OrthoPhoto', 'CephalometricMeasurement',
    ];

    /** @var list<string> */
    private const FORCE_PATCH_SCOPES = [
        'StockMovement',
    ];

    /** @var list<array{name: string, code: string, order: int, color: string}> */
    public const SERVICE_CATEGORIES = [
        ['name' => 'Терапия',              'code' => 'THE', 'order' => 10, 'color' => '#1F77B4'],
        ['name' => 'Хирургия',             'code' => 'SUR', 'order' => 20, 'color' => '#D62728'],
        ['name' => 'Ортопедия',            'code' => 'ORP', 'order' => 30, 'color' => '#FF7F0E'],
        ['name' => 'Ортодонтия',           'code' => 'ORD', 'order' => 40, 'color' => '#2CA02C'],
        ['name' => 'Гигиена',              'code' => 'HYG', 'order' => 50, 'color' => '#17BECF'],
        ['name' => 'Диагностика',          'code' => 'DIA', 'order' => 60, 'color' => '#9467BD'],
        ['name' => 'Имплантология',        'code' => 'IMP', 'order' => 70, 'color' => '#8C564B'],
        ['name' => 'Детская стоматология', 'code' => 'PED', 'order' => 80, 'color' => '#E377C2'],
    ];

    /** @var array<string, string> */
    private const LEGACY_SERVICE_CATEGORY_NAMES = [
        'THE' => 'Therapy',
        'SUR' => 'Surgery',
        'ORP' => 'Orthopedics',
        'ORD' => 'Orthodontics',
        'HYG' => 'Hygiene',
        'DIA' => 'Diagnostics',
        'IMP' => 'Implantology',
        'PED' => 'Pediatric',
    ];

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array{teams: int, roles: int, serviceCategories: int}
     */
    public function seed(): array
    {
        return [
            'teams' => $this->ensureTeams(),
            'roles' => $this->ensureRoles(),
            'serviceCategories' => $this->ensureServiceCategories(),
        ];
    }

    private function ensureTeams(): int
    {
        $created = 0;
        foreach (self::TEAMS as $teamName) {
            $existing = $this->entityManager
                ->getRDBRepository(Team::ENTITY_TYPE)
                ->where(['name' => $teamName])
                ->findOne();
            if ($existing) {
                continue;
            }
            $team = $this->entityManager->getRDBRepository(Team::ENTITY_TYPE)->getNew();
            $team->set('name', $teamName);
            $this->entityManager->saveEntity($team);
            $created++;
        }
        return $created;
    }

    private function ensureRoles(): int
    {
        $created = 0;
        foreach (self::roleMatrix() as $roleName => $cfg) {
            $existing = $this->entityManager
                ->getRDBRepository(Role::ENTITY_TYPE)
                ->where(['name' => $roleName])
                ->findOne();
            if ($existing) {
                $data = (array) $existing->get('data');
                $changed = false;

                foreach ($cfg['data'] as $scope => $row) {
                    if (
                        array_key_exists($scope, $data) &&
                        !in_array($scope, self::FORCE_PATCH_SCOPES, true)
                    ) {
                        continue;
                    }

                    if (
                        array_key_exists($scope, $data) &&
                        (array) $data[$scope] === $row
                    ) {
                        continue;
                    }

                    $data[$scope] = $row;
                    $changed = true;
                }

                if ($changed) {
                    $existing->set('data', (object) $data);
                    $this->entityManager->saveEntity($existing);
                }

                continue;
            }

            $role = $this->entityManager->getRDBRepository(Role::ENTITY_TYPE)->getNew();
            $role->set('name', $roleName);
            $role->set('description', $cfg['description']);
            $role->set('data', (object) $cfg['data']);
            $role->set('fieldData', (object) []);
            $this->entityManager->saveEntity($role);
            $created++;
        }
        return $created;
    }

    private function ensureServiceCategories(): int
    {
        $created = 0;
        foreach (self::SERVICE_CATEGORIES as $cfg) {
            $existing = $this->entityManager
                ->getRDBRepository('ServiceCategory')
                ->where(['code' => $cfg['code']])
                ->findOne();
            if ($existing) {
                if (
                    (string) $existing->get('name') === (self::LEGACY_SERVICE_CATEGORY_NAMES[$cfg['code']] ?? '')
                ) {
                    $existing->set('name', $cfg['name']);
                    $existing->set('order', $cfg['order']);
                    $existing->set('color', $cfg['color']);
                    $this->entityManager->saveEntity($existing);
                }
                continue;
            }
            $cat = $this->entityManager->getRDBRepository('ServiceCategory')->getNew();
            foreach ($cfg as $k => $v) {
                $cat->set($k, $v);
            }
            $cat->set('isActive', true);
            $this->entityManager->saveEntity($cat);
            $created++;
        }
        return $created;
    }

    /**
     * Pre-baked ACL matrices for the five EspoDental roles. Identical to the
     * legacy AfterInstall arrays so existing installs keep the same effective
     * permissions on subsequent rebuilds, except force-patched hard invariants
     * such as immutable stock movements.
     *
     * @return array<string, array{description: string, data: array<string, array<string, string>>}>
     */
    public static function roleMatrix(): array
    {
        $row = fn (string $c, string $r, string $e, string $d, string $s): array =>
            ['create' => $c, 'read' => $r, 'edit' => $e, 'delete' => $d, 'stream' => $s];

        $manager = [];
        foreach (self::SCOPES as $scope) {
            $manager[$scope] = $row('yes', 'all', 'all', 'all', 'all');
        }
        $manager['AppointmentStatusLog'] = $row('no', 'all', 'no', 'no', 'no');
        $manager['AppointmentWaitlistEntry'] = $row('yes', 'all', 'all', 'all', 'all');
        $manager['AppointmentRescheduleRequest'] = $row('yes', 'all', 'all', 'all', 'all');
        $manager['PatientPortalSession'] = $row('no', 'all', 'no', 'no', 'no');
        $manager['PatientPortalEvent'] = $row('no', 'all', 'no', 'no', 'no');
        $manager['DoctorShiftTemplate']  = $row('yes', 'all', 'all', 'all', 'no');
        $manager['DoctorShift']          = $row('yes', 'all', 'all', 'all', 'no');
        $manager['ServiceCategory']      = $row('yes', 'all', 'all', 'all', 'no');
        $manager['Service']              = $row('yes', 'all', 'all', 'all', 'no');
        $manager['VisitServiceLine']     = $row('yes', 'all', 'all', 'all', 'no');
        $manager['VisitMaterialLine']    = $row('yes', 'all', 'all', 'all', 'no');
        $manager['ToothChartSnapshot']   = $row('yes', 'all', 'all', 'all', 'no');
        $manager['VisitPhoto']           = $row('yes', 'all', 'all', 'all', 'no');
        $manager['InvoiceLine']          = $row('yes', 'all', 'all', 'all', 'no');
        $manager['MaterialCategory']     = $row('yes', 'all', 'all', 'all', 'no');
        $manager['StockMovement']        = $row('yes', 'all', 'no', 'no', 'no');
        $manager['ServiceMaterial']      = $row('yes', 'all', 'all', 'all', 'no');
        $manager['LowStockAlert']        = $row('no', 'all', 'all', 'no', 'all');
        $manager['NotificationLog']      = $row('yes', 'all', 'all', 'all', 'no');
        $manager['AssistantActionProposal'] = $row('yes', 'all', 'all', 'all', 'no');
        $manager['SalaryBonus']          = $row('yes', 'all', 'all', 'all', 'no');
        $manager['TreatmentStage']       = $row('yes', 'all', 'all', 'all', 'no');
        $manager['ToothMovementPlan']    = $row('yes', 'all', 'all', 'all', 'no');
        $manager['OrthoPhoto']           = $row('yes', 'all', 'all', 'all', 'no');
        $manager['CephalometricMeasurement'] = $row('yes', 'all', 'all', 'all', 'no');

        $doctor = [
            'Clinic'               => $row('no', 'team', 'no', 'no', 'team'),
            'Cabinet'              => $row('no', 'team', 'no', 'no', 'team'),
            'PreliminaryPatient'   => $row('yes', 'team', 'team', 'no', 'team'),
            'Patient'              => $row('yes', 'team', 'team', 'no', 'team'),
            'Appointment'          => $row('yes', 'team', 'team', 'no', 'team'),
            'AppointmentWaitlistEntry' => $row('no', 'team', 'no', 'no', 'team'),
            'AppointmentRescheduleRequest' => $row('no', 'team', 'no', 'no', 'team'),
            'PatientPortalSession' => $row('no', 'no', 'no', 'no', 'no'),
            'PatientPortalEvent'   => $row('no', 'no', 'no', 'no', 'no'),
            'DoctorShiftTemplate'  => $row('no', 'all', 'no', 'no', 'no'),
            'DoctorShift'          => $row('no', 'all', 'no', 'no', 'no'),
            'AppointmentStatusLog' => $row('no', 'team', 'no', 'no', 'no'),
            'Visit'                => $row('yes', 'team', 'own', 'no', 'team'),
            'ServiceCategory'      => $row('no', 'all', 'no', 'no', 'no'),
            'Service'              => $row('no', 'all', 'no', 'no', 'no'),
            'VisitServiceLine'     => $row('yes', 'team', 'own', 'own', 'no'),
            'VisitMaterialLine'    => $row('yes', 'team', 'own', 'own', 'no'),
            'ToothChartSnapshot'   => $row('yes', 'team', 'own', 'no', 'no'),
            'VisitPhoto'           => $row('yes', 'team', 'own', 'own', 'no'),
            'Invoice'              => $row('no', 'team', 'no', 'no', 'team'),
            'InvoiceLine'          => $row('no', 'team', 'no', 'no', 'no'),
            'Payment'              => $row('no', 'team', 'no', 'no', 'team'),
            'MaterialCategory'     => $row('no', 'all', 'no', 'no', 'no'),
            'Material'             => $row('no', 'all', 'no', 'no', 'no'),
            'StockMovement'        => $row('no', 'team', 'no', 'no', 'no'),
            'ServiceMaterial'      => $row('no', 'all', 'no', 'no', 'no'),
            'LowStockAlert'        => $row('no', 'team', 'no', 'no', 'no'),
            'NotificationLog'      => $row('no', 'team', 'no', 'no', 'no'),
            'AssistantActionProposal' => $row('no', 'team', 'no', 'no', 'no'),
            'SalaryProfile'        => $row('no', 'own', 'no', 'no', 'no'),
            'SalaryEntry'          => $row('no', 'own', 'no', 'no', 'own'),
            'SalaryBonus'          => $row('no', 'own', 'no', 'no', 'no'),
            'OrthodonticCard'      => $row('yes', 'team', 'team', 'no', 'team'),
            'TreatmentStage'       => $row('yes', 'team', 'team', 'team', 'no'),
            'ToothMovementPlan'    => $row('yes', 'team', 'team', 'team', 'no'),
            'OrthoPhoto'           => $row('yes', 'team', 'team', 'team', 'no'),
            'CephalometricMeasurement' => $row('yes', 'team', 'team', 'team', 'no'),
        ];

        $assistant = [
            'Clinic'               => $row('no', 'team', 'no', 'no', 'team'),
            'Cabinet'              => $row('no', 'team', 'no', 'no', 'team'),
            'PreliminaryPatient'   => $row('no', 'team', 'no', 'no', 'team'),
            'Patient'              => $row('no', 'team', 'no', 'no', 'team'),
            'Appointment'          => $row('no', 'team', 'no', 'no', 'team'),
            'AppointmentWaitlistEntry' => $row('no', 'team', 'no', 'no', 'team'),
            'AppointmentRescheduleRequest' => $row('no', 'no', 'no', 'no', 'no'),
            'PatientPortalSession' => $row('no', 'no', 'no', 'no', 'no'),
            'PatientPortalEvent'   => $row('no', 'no', 'no', 'no', 'no'),
            'DoctorShiftTemplate'  => $row('no', 'all', 'no', 'no', 'no'),
            'DoctorShift'          => $row('no', 'all', 'no', 'no', 'no'),
            'AppointmentStatusLog' => $row('no', 'team', 'no', 'no', 'no'),
            'Visit'                => $row('no', 'team', 'no', 'no', 'team'),
            'ServiceCategory'      => $row('no', 'all', 'no', 'no', 'no'),
            'Service'              => $row('no', 'all', 'no', 'no', 'no'),
            'VisitServiceLine'     => $row('no', 'team', 'no', 'no', 'no'),
            'VisitMaterialLine'    => $row('yes', 'team', 'own', 'own', 'no'),
            'ToothChartSnapshot'   => $row('no', 'team', 'no', 'no', 'no'),
            'VisitPhoto'           => $row('yes', 'team', 'own', 'own', 'no'),
            'Invoice'              => $row('no', 'team', 'no', 'no', 'no'),
            'InvoiceLine'          => $row('no', 'team', 'no', 'no', 'no'),
            'Payment'              => $row('no', 'team', 'no', 'no', 'no'),
            'MaterialCategory'     => $row('no', 'all', 'no', 'no', 'no'),
            'Material'             => $row('no', 'all', 'no', 'no', 'no'),
            'StockMovement'        => $row('no', 'no', 'no', 'no', 'no'),
            'ServiceMaterial'      => $row('no', 'all', 'no', 'no', 'no'),
            'LowStockAlert'        => $row('no', 'no', 'no', 'no', 'no'),
            'NotificationLog'      => $row('no', 'team', 'no', 'no', 'no'),
            'AssistantActionProposal' => $row('no', 'no', 'no', 'no', 'no'),
            'SalaryProfile'        => $row('no', 'own', 'no', 'no', 'no'),
            'SalaryEntry'          => $row('no', 'own', 'no', 'no', 'own'),
            'SalaryBonus'          => $row('no', 'own', 'no', 'no', 'no'),
            'OrthodonticCard'      => $row('no', 'team', 'no', 'no', 'team'),
            'TreatmentStage'       => $row('no', 'team', 'no', 'no', 'no'),
            'ToothMovementPlan'    => $row('no', 'team', 'no', 'no', 'no'),
            'OrthoPhoto'           => $row('yes', 'team', 'team', 'no', 'no'),
            'CephalometricMeasurement' => $row('no', 'team', 'no', 'no', 'no'),
        ];

        $administrator = [
            'Clinic'               => $row('no', 'team', 'no', 'no', 'team'),
            'Cabinet'              => $row('no', 'team', 'no', 'no', 'team'),
            'PreliminaryPatient'   => $row('yes', 'team', 'team', 'team', 'team'),
            'Patient'              => $row('yes', 'team', 'team', 'no', 'team'),
            'Appointment'          => $row('yes', 'team', 'team', 'team', 'team'),
            'AppointmentWaitlistEntry' => $row('yes', 'team', 'team', 'team', 'team'),
            'AppointmentRescheduleRequest' => $row('yes', 'team', 'team', 'team', 'team'),
            'PatientPortalSession' => $row('no', 'team', 'no', 'no', 'no'),
            'PatientPortalEvent'   => $row('no', 'team', 'no', 'no', 'no'),
            'DoctorShiftTemplate'  => $row('yes', 'all', 'all', 'no', 'no'),
            'DoctorShift'          => $row('yes', 'all', 'all', 'no', 'no'),
            'AppointmentStatusLog' => $row('no', 'team', 'no', 'no', 'no'),
            'Visit'                => $row('no', 'team', 'no', 'no', 'team'),
            'ServiceCategory'      => $row('no', 'all', 'no', 'no', 'no'),
            'Service'              => $row('no', 'all', 'no', 'no', 'no'),
            'VisitServiceLine'     => $row('no', 'team', 'no', 'no', 'no'),
            'VisitMaterialLine'    => $row('no', 'team', 'no', 'no', 'no'),
            'ToothChartSnapshot'   => $row('no', 'team', 'no', 'no', 'no'),
            'VisitPhoto'           => $row('no', 'team', 'no', 'no', 'no'),
            'Invoice'              => $row('yes', 'team', 'team', 'no', 'team'),
            'InvoiceLine'          => $row('yes', 'team', 'team', 'team', 'no'),
            'Payment'              => $row('yes', 'team', 'team', 'no', 'team'),
            'MaterialCategory'     => $row('no', 'all', 'no', 'no', 'no'),
            'Material'             => $row('no', 'all', 'no', 'no', 'no'),
            'StockMovement'        => $row('no', 'no', 'no', 'no', 'no'),
            'ServiceMaterial'      => $row('no', 'all', 'no', 'no', 'no'),
            'LowStockAlert'        => $row('no', 'team', 'no', 'no', 'no'),
            'NotificationLog'      => $row('yes', 'team', 'team', 'no', 'no'),
            'AssistantActionProposal' => $row('yes', 'team', 'team', 'no', 'no'),
            'SalaryProfile'        => $row('yes', 'all', 'all', 'no', 'all'),
            'SalaryEntry'          => $row('yes', 'all', 'all', 'no', 'all'),
            'SalaryBonus'          => $row('yes', 'all', 'all', 'all', 'no'),
            'OrthodonticCard'      => $row('no', 'all', 'team', 'no', 'all'),
            'TreatmentStage'       => $row('no', 'all', 'no', 'no', 'no'),
            'ToothMovementPlan'    => $row('no', 'all', 'no', 'no', 'no'),
            'OrthoPhoto'           => $row('no', 'all', 'no', 'no', 'no'),
            'CephalometricMeasurement' => $row('no', 'all', 'no', 'no', 'no'),
        ];

        $stockManager = [
            'Clinic'               => $row('no', 'team', 'no', 'no', 'team'),
            'Cabinet'              => $row('no', 'team', 'no', 'no', 'team'),
            'PreliminaryPatient'   => $row('no', 'no', 'no', 'no', 'no'),
            'Patient'              => $row('no', 'no', 'no', 'no', 'no'),
            'Appointment'          => $row('no', 'no', 'no', 'no', 'no'),
            'AppointmentWaitlistEntry' => $row('no', 'no', 'no', 'no', 'no'),
            'AppointmentRescheduleRequest' => $row('no', 'no', 'no', 'no', 'no'),
            'PatientPortalSession' => $row('no', 'no', 'no', 'no', 'no'),
            'PatientPortalEvent'   => $row('no', 'no', 'no', 'no', 'no'),
            'DoctorShiftTemplate'  => $row('no', 'no', 'no', 'no', 'no'),
            'DoctorShift'          => $row('no', 'no', 'no', 'no', 'no'),
            'AppointmentStatusLog' => $row('no', 'no', 'no', 'no', 'no'),
            'Visit'                => $row('no', 'no', 'no', 'no', 'no'),
            'ServiceCategory'      => $row('no', 'all', 'no', 'no', 'no'),
            'Service'              => $row('no', 'all', 'no', 'no', 'no'),
            'VisitServiceLine'     => $row('no', 'no', 'no', 'no', 'no'),
            'VisitMaterialLine'    => $row('no', 'no', 'no', 'no', 'no'),
            'ToothChartSnapshot'   => $row('no', 'no', 'no', 'no', 'no'),
            'VisitPhoto'           => $row('no', 'no', 'no', 'no', 'no'),
            'Invoice'              => $row('no', 'no', 'no', 'no', 'no'),
            'InvoiceLine'          => $row('no', 'no', 'no', 'no', 'no'),
            'Payment'              => $row('no', 'no', 'no', 'no', 'no'),
            'MaterialCategory'     => $row('yes', 'all', 'all', 'all', 'no'),
            'Material'             => $row('yes', 'all', 'all', 'all', 'all'),
            'StockMovement'        => $row('yes', 'all', 'no', 'no', 'no'),
            'ServiceMaterial'      => $row('yes', 'all', 'all', 'all', 'no'),
            'LowStockAlert'        => $row('no', 'all', 'all', 'no', 'all'),
            'NotificationLog'      => $row('no', 'no', 'no', 'no', 'no'),
            'AssistantActionProposal' => $row('no', 'no', 'no', 'no', 'no'),
            'SalaryProfile'        => $row('no', 'own', 'no', 'no', 'no'),
            'SalaryEntry'          => $row('no', 'own', 'no', 'no', 'own'),
            'SalaryBonus'          => $row('no', 'own', 'no', 'no', 'no'),
            'OrthodonticCard'      => $row('no', 'no', 'no', 'no', 'no'),
            'TreatmentStage'       => $row('no', 'no', 'no', 'no', 'no'),
            'ToothMovementPlan'    => $row('no', 'no', 'no', 'no', 'no'),
            'OrthoPhoto'           => $row('no', 'no', 'no', 'no', 'no'),
            'CephalometricMeasurement' => $row('no', 'no', 'no', 'no', 'no'),
        ];

        return [
            'EspoDental Manager' => [
                'description' => 'Full read/write within the clinic team. Sees finance.',
                'data' => $manager,
            ],
            'EspoDental Doctor' => [
                'description' => 'Sees own appointments and patients; no global finance.',
                'data' => $doctor,
            ],
            'EspoDental Assistant' => [
                'description' => 'Read-only on patients, no visit completion.',
                'data' => $assistant,
            ],
            'EspoDental Administrator' => [
                'description' => 'Front-desk: leads, patients, appointments, invoices.',
                'data' => $administrator,
            ],
            'EspoDental Stock Manager' => [
                'description' => 'Inventory and threshold alerts.',
                'data' => $stockManager,
            ],
        ];
    }
}
