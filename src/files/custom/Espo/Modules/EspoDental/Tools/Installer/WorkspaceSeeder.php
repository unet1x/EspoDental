<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Installer;

// phpcs:disable Generic.Files.LineLength.TooLong

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Tools\PatientBalanceCalculator;
use Espo\ORM\Entity;
use stdClass;

/**
 * Turns a fresh EspoCRM instance into a usable dental workspace: menu,
 * dashboard, clinic, cabinets, starter price list, stock catalog and jobs.
 */
class WorkspaceSeeder
{
    private const CLINIC_CODE = 'MAIN';
    private const OPENING_STOCK_REASON = 'EspoDental opening stock';

    /** @var list<array{name: string, code: string, order: int, color: string, equipment: string}> */
    private const CABINETS = [
        ['name' => 'Кабинет 1', 'code' => 'CAB-1', 'order' => 10, 'color' => '#1F77B4', 'equipment' => 'Терапия / первичные приёмы'],
        ['name' => 'Кабинет 2', 'code' => 'CAB-2', 'order' => 20, 'color' => '#2CA02C', 'equipment' => 'Терапия / хирургия'],
        ['name' => 'Кабинет 3', 'code' => 'CAB-3', 'order' => 30, 'color' => '#FF7F0E', 'equipment' => 'Ортопедия'],
        ['name' => 'Кабинет 4', 'code' => 'CAB-4', 'order' => 40, 'color' => '#9467BD', 'equipment' => 'Ортодонтия'],
        ['name' => 'Кабинет 5', 'code' => 'CAB-5', 'order' => 50, 'color' => '#17BECF', 'equipment' => 'Гигиена / резерв'],
    ];

    /** @var list<array{name: string, code: string, order: int, color: string}> */
    private const MATERIAL_CATEGORIES = [
        ['name' => 'Анестезия', 'code' => 'ANE', 'order' => 10, 'color' => '#1F77B4'],
        ['name' => 'Дезинфекция и СИЗ', 'code' => 'DIS', 'order' => 20, 'color' => '#2CA02C'],
        ['name' => 'Терапевтические материалы', 'code' => 'REST', 'order' => 30, 'color' => '#FF7F0E'],
        ['name' => 'Эндодонтия', 'code' => 'ENDO', 'order' => 40, 'color' => '#9467BD'],
        ['name' => 'Ортодонтия', 'code' => 'ORTH', 'order' => 50, 'color' => '#E377C2'],
    ];

    /** @var list<array{name: string, code: string, categoryCode: string, unit: string, price: float, minStock: float, criticalStock: float, openingStock: float}> */
    private const MATERIALS = [
        ['name' => 'Анестетик карпульный', 'code' => 'ANE-CARP', 'categoryCode' => 'ANE', 'unit' => 'cartridge', 'price' => 350, 'minStock' => 20, 'criticalStock' => 10, 'openingStock' => 100],
        ['name' => 'Перчатки нитриловые', 'code' => 'DIS-GLOVES', 'categoryCode' => 'DIS', 'unit' => 'pcs', 'price' => 12, 'minStock' => 200, 'criticalStock' => 100, 'openingStock' => 1000],
        ['name' => 'Маски медицинские', 'code' => 'DIS-MASK', 'categoryCode' => 'DIS', 'unit' => 'pcs', 'price' => 8, 'minStock' => 200, 'criticalStock' => 100, 'openingStock' => 500],
        ['name' => 'Композит универсальный', 'code' => 'REST-COMP', 'categoryCode' => 'REST', 'unit' => 'g', 'price' => 900, 'minStock' => 10, 'criticalStock' => 5, 'openingStock' => 30],
        ['name' => 'Травильный гель', 'code' => 'REST-ETCH', 'categoryCode' => 'REST', 'unit' => 'ml', 'price' => 80, 'minStock' => 20, 'criticalStock' => 10, 'openingStock' => 50],
        ['name' => 'Гуттаперчевые штифты', 'code' => 'ENDO-GP', 'categoryCode' => 'ENDO', 'unit' => 'pack', 'price' => 1200, 'minStock' => 3, 'criticalStock' => 1, 'openingStock' => 10],
        ['name' => 'Дуга NiTi', 'code' => 'ORTH-NITI', 'categoryCode' => 'ORTH', 'unit' => 'pcs', 'price' => 500, 'minStock' => 20, 'criticalStock' => 10, 'openingStock' => 60],
    ];

    /** @var list<array{name: string, code: string, categoryCode: string, price: float, duration: int, color: string}> */
    private const SERVICES = [
        ['name' => 'Первичная консультация', 'code' => 'DIA-001', 'categoryCode' => 'DIA', 'price' => 1500, 'duration' => 30, 'color' => '#9467BD'],
        ['name' => 'Консультация ортодонта', 'code' => 'ORD-001', 'categoryCode' => 'ORD', 'price' => 2000, 'duration' => 30, 'color' => '#2CA02C'],
        ['name' => 'Профессиональная гигиена', 'code' => 'HYG-001', 'categoryCode' => 'HYG', 'price' => 4500, 'duration' => 60, 'color' => '#17BECF'],
        ['name' => 'Лечение кариеса', 'code' => 'THE-001', 'categoryCode' => 'THE', 'price' => 6000, 'duration' => 60, 'color' => '#1F77B4'],
        ['name' => 'Временная пломба', 'code' => 'THE-002', 'categoryCode' => 'THE', 'price' => 1500, 'duration' => 30, 'color' => '#1F77B4'],
        ['name' => 'Удаление зуба простое', 'code' => 'SUR-001', 'categoryCode' => 'SUR', 'price' => 4500, 'duration' => 45, 'color' => '#D62728'],
        ['name' => 'Снятие слепков / сканирование', 'code' => 'ORP-001', 'categoryCode' => 'ORP', 'price' => 2500, 'duration' => 30, 'color' => '#FF7F0E'],
        ['name' => 'Консультация имплантолога', 'code' => 'IMP-001', 'categoryCode' => 'IMP', 'price' => 2000, 'duration' => 30, 'color' => '#8C564B'],
        ['name' => 'Детский осмотр', 'code' => 'PED-001', 'categoryCode' => 'PED', 'price' => 1500, 'duration' => 30, 'color' => '#E377C2'],
    ];

    /** @var list<array{serviceCode: string, materialCode: string, quantity: float}> */
    private const SERVICE_MATERIALS = [
        ['serviceCode' => 'THE-001', 'materialCode' => 'ANE-CARP', 'quantity' => 1],
        ['serviceCode' => 'THE-001', 'materialCode' => 'REST-COMP', 'quantity' => 0.2],
        ['serviceCode' => 'THE-001', 'materialCode' => 'REST-ETCH', 'quantity' => 0.5],
        ['serviceCode' => 'SUR-001', 'materialCode' => 'ANE-CARP', 'quantity' => 2],
        ['serviceCode' => 'HYG-001', 'materialCode' => 'DIS-GLOVES', 'quantity' => 2],
        ['serviceCode' => 'HYG-001', 'materialCode' => 'DIS-MASK', 'quantity' => 1],
        ['serviceCode' => 'ORD-001', 'materialCode' => 'ORTH-NITI', 'quantity' => 1],
    ];

    /** @var list<array{name: string, job: string, scheduling: string}> */
    private const SCHEDULED_JOBS = [
        ['name' => 'EspoDental: напоминания о приёмах', 'job' => 'EspoDentalSendAppointmentReminders', 'scheduling' => '*/15 * * * *'],
        ['name' => 'EspoDental: проверка анкет здоровья', 'job' => 'EspoDentalCheckExpiredQuestionnaires', 'scheduling' => '10 8 * * *'],
        ['name' => 'EspoDental: контроль складских остатков', 'job' => 'EspoDentalCheckStockThresholds', 'scheduling' => '*/30 * * * *'],
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ?ConfigWriter $configWriter = null
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function seed(): array
    {
        $result = (new RoleSeeder($this->entityManager))->seed();
        $clinicResult = $this->ensureClinic();
        $clinic = $clinicResult['entity'];

        return $result + [
            'clinics' => $clinicResult['created'],
            'cabinets' => $this->ensureCabinets($clinic),
            'materialCategories' => $this->ensureMaterialCategories(),
            'materials' => $this->ensureMaterials(),
            'stockMovements' => $this->ensureOpeningStock($clinic),
            'services' => $this->ensureServices(),
            'serviceMaterials' => $this->ensureServiceMaterials(),
            'scheduledJobs' => $this->ensureScheduledJobs(),
            'dashboardTemplates' => $this->ensureDashboardTemplate(),
            'settings' => $this->ensureSettings($clinic),
            'clinicalLineNames' => $this->ensureClinicalLineNames(),
            'visitNames' => $this->ensureVisitNames(),
            'toothChartNames' => $this->ensureToothChartNames(),
            'childFlags' => $this->ensureChildFlags(),
            'patientBalances' => $this->ensurePatientBalances(),
        ];
    }

    /**
     * @return array{entity: Entity, created: int}
     */
    private function ensureClinic(): array
    {
        $clinic = $this->findOneByCode('Clinic', self::CLINIC_CODE);

        if ($clinic) {
            return ['entity' => $clinic, 'created' => 0];
        }

        $clinic = $this->entityManager->getRDBRepository('Clinic')->getNew();
        $clinic->set('name', 'Основная клиника');
        $clinic->set('code', self::CLINIC_CODE);
        $clinic->set('timezone', 'Europe/Moscow');
        $clinic->set('defaultCurrency', 'RUB');
        $clinic->set('color', '#1F77B4');
        $clinic->set('isActive', true);
        $clinic->set('description', 'Стартовая клиника EspoDental. Переименуйте и заполните реквизиты перед запуском в работу.');
        $this->entityManager->saveEntity($clinic);

        return ['entity' => $clinic, 'created' => 1];
    }

    private function ensureCabinets(Entity $clinic): int
    {
        $created = 0;

        foreach (self::CABINETS as $cfg) {
            $existing = $this->entityManager
                ->getRDBRepository('Cabinet')
                ->where(['clinicId' => $clinic->getId(), 'code' => $cfg['code']])
                ->findOne();

            if ($existing) {
                continue;
            }

            $cabinet = $this->entityManager->getRDBRepository('Cabinet')->getNew();
            $this->setValues($cabinet, $cfg);
            $cabinet->set('clinicId', $clinic->getId());
            $cabinet->set('isActive', true);
            $this->entityManager->saveEntity($cabinet);
            $created++;
        }

        return $created;
    }

    private function ensureMaterialCategories(): int
    {
        $created = 0;

        foreach (self::MATERIAL_CATEGORIES as $cfg) {
            if ($this->findOneByCode('MaterialCategory', $cfg['code'])) {
                continue;
            }

            $category = $this->entityManager->getRDBRepository('MaterialCategory')->getNew();
            $this->setValues($category, $cfg);
            $category->set('isActive', true);
            $this->entityManager->saveEntity($category);
            $created++;
        }

        return $created;
    }

    private function ensureMaterials(): int
    {
        $created = 0;

        foreach (self::MATERIALS as $cfg) {
            if ($this->findOneByCode('Material', $cfg['code'])) {
                continue;
            }

            $category = $this->findOneByCode('MaterialCategory', $cfg['categoryCode']);
            if (!$category) {
                continue;
            }

            $material = $this->entityManager->getRDBRepository('Material')->getNew();
            $this->setValues($material, [
                'name' => $cfg['name'],
                'code' => $cfg['code'],
                'unit' => $cfg['unit'],
                'price' => $cfg['price'],
                'priceCurrency' => 'RUB',
                'minStock' => $cfg['minStock'],
                'criticalStock' => $cfg['criticalStock'],
                'stockLevel' => 'ok',
                'isActive' => true,
                'categoryId' => $category->getId(),
            ]);
            $this->entityManager->saveEntity($material);
            $created++;
        }

        return $created;
    }

    private function ensureOpeningStock(Entity $clinic): int
    {
        $created = 0;
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach (self::MATERIALS as $cfg) {
            if ($cfg['openingStock'] <= 0) {
                continue;
            }

            $material = $this->findOneByCode('Material', $cfg['code']);
            if (!$material) {
                continue;
            }

            $existing = $this->entityManager
                ->getRDBRepository('StockMovement')
                ->where([
                    'materialId' => $material->getId(),
                    'clinicId' => $clinic->getId(),
                    'type' => 'receipt',
                    'reason' => self::OPENING_STOCK_REASON,
                ])
                ->findOne();

            if ($existing) {
                continue;
            }

            $movement = $this->entityManager->getRDBRepository('StockMovement')->getNew();
            $this->setValues($movement, [
                'materialId' => $material->getId(),
                'clinicId' => $clinic->getId(),
                'type' => 'receipt',
                'quantity' => $cfg['openingStock'],
                'unitPrice' => $cfg['price'],
                'unitPriceCurrency' => 'RUB',
                'performedAt' => $now,
                'reason' => self::OPENING_STOCK_REASON,
            ]);
            $this->entityManager->saveEntity($movement);
            $created++;
        }

        return $created;
    }

    private function ensureServices(): int
    {
        $created = 0;

        foreach (self::SERVICES as $cfg) {
            if ($this->findOneByCode('Service', $cfg['code'])) {
                continue;
            }

            $category = $this->findOneByCode('ServiceCategory', $cfg['categoryCode']);
            if (!$category) {
                continue;
            }

            $service = $this->entityManager->getRDBRepository('Service')->getNew();
            $this->setValues($service, [
                'name' => $cfg['name'],
                'code' => $cfg['code'],
                'categoryId' => $category->getId(),
                'price' => $cfg['price'],
                'priceCurrency' => 'RUB',
                'duration' => $cfg['duration'],
                'color' => $cfg['color'],
                'isActive' => true,
                'vatRate' => 0,
            ]);
            $this->entityManager->saveEntity($service);
            $created++;
        }

        return $created;
    }

    private function ensureServiceMaterials(): int
    {
        $created = 0;

        foreach (self::SERVICE_MATERIALS as $cfg) {
            $service = $this->findOneByCode('Service', $cfg['serviceCode']);
            $material = $this->findOneByCode('Material', $cfg['materialCode']);

            if (!$service || !$material) {
                continue;
            }

            $existing = $this->entityManager
                ->getRDBRepository('ServiceMaterial')
                ->where(['serviceId' => $service->getId(), 'materialId' => $material->getId()])
                ->findOne();

            if ($existing) {
                continue;
            }

            $link = $this->entityManager->getRDBRepository('ServiceMaterial')->getNew();
            $link->set('serviceId', $service->getId());
            $link->set('materialId', $material->getId());
            $link->set('quantity', $cfg['quantity']);
            $link->set('notes', 'Стартовая норма списания. Проверьте под протокол клиники.');
            $this->entityManager->saveEntity($link);
            $created++;
        }

        return $created;
    }

    private function ensureScheduledJobs(): int
    {
        $created = 0;

        foreach (self::SCHEDULED_JOBS as $cfg) {
            $existing = $this->entityManager
                ->getRDBRepository('ScheduledJob')
                ->where(['job' => $cfg['job']])
                ->findOne();

            if ($existing) {
                continue;
            }

            $job = $this->entityManager->getRDBRepository('ScheduledJob')->getNew();
            $this->setValues($job, [
                'name' => $cfg['name'],
                'job' => $cfg['job'],
                'scheduling' => $cfg['scheduling'],
                'status' => 'Active',
                'isInternal' => false,
            ]);
            $this->entityManager->saveEntity($job);
            $created++;
        }

        return $created;
    }

    private function ensureDashboardTemplate(): int
    {
        $existing = $this->entityManager
            ->getRDBRepository('DashboardTemplate')
            ->where(['name' => 'EspoDental: рабочее место клиники'])
            ->findOne();

        if ($existing) {
            return 0;
        }

        $template = $this->entityManager->getRDBRepository('DashboardTemplate')->getNew();
        $template->set('name', 'EspoDental: рабочее место клиники');
        $template->set('layout', $this->dashboardLayout());
        $template->set('dashletsOptions', $this->dashletsOptions());
        $this->entityManager->saveEntity($template);

        return 1;
    }

    private function ensureSettings(Entity $clinic): int
    {
        if (!$this->configWriter) {
            return 0;
        }

        $this->configWriter->setMultiple([
            'applicationName' => 'EspoDental',
            'language' => 'ru_RU',
            'timeZone' => 'Europe/Moscow',
            'defaultCurrency' => 'RUB',
            'baseCurrency' => 'RUB',
            'currencyList' => ['RUB', 'USD', 'EUR'],
            'globalSearchEntityList' => [
                'Patient',
                'PreliminaryPatient',
                'Appointment',
                'Visit',
                'Invoice',
                'Service',
                'Material',
                'OrthodonticCard',
            ],
            'tabList' => $this->tabList(),
            'quickCreateList' => [
                'PreliminaryPatient',
                'Payment',
                'OrthodonticCard',
            ],
            'calendarEntityList' => ['Appointment', 'Meeting', 'Call', 'Task'],
            'busyRangesEntityList' => ['Appointment', 'Meeting', 'Call'],
            'dashboardLayout' => $this->dashboardLayout(),
            'dashletsOptions' => $this->dashletsOptions(),
            'espoDentalDefaultCurrency' => 'RUB',
            'espoDentalDefaultClinicId' => $clinic->getId(),
            'espoDentalDefaultClinicName' => $clinic->get('name'),
            'espoDentalReminderHoursBefore' => 24,
            'espoDentalReminderSecondHoursBefore' => 2,
            'espoDentalReminderWindowMinutes' => 20,
        ]);
        $this->configWriter->save();

        return 1;
    }

    private function ensureClinicalLineNames(): int
    {
        $updated = 0;

        $serviceLines = $this->entityManager
            ->getRDBRepository('VisitServiceLine')
            ->where(['deleted' => false])
            ->find();

        foreach ($serviceLines as $line) {
            if ($line->get('name') || !$line->get('serviceId')) {
                continue;
            }

            $service = $this->entityManager->getEntityById('Service', (string) $line->get('serviceId'));
            if (!$service) {
                continue;
            }

            $line->set('name', (string) $service->get('name'));
            $this->entityManager->saveEntity($line, ['skipHooks' => true]);
            $updated++;
        }

        $materialLines = $this->entityManager
            ->getRDBRepository('VisitMaterialLine')
            ->where(['deleted' => false])
            ->find();

        foreach ($materialLines as $line) {
            if ($line->get('name') || !$line->get('materialId')) {
                continue;
            }

            $material = $this->entityManager->getEntityById('Material', (string) $line->get('materialId'));
            if (!$material) {
                continue;
            }

            $line->set('name', (string) $material->get('name'));
            $this->entityManager->saveEntity($line, ['skipHooks' => true]);
            $updated++;
        }

        return $updated;
    }

    private function ensureVisitNames(): int
    {
        $updated = 0;
        $visits = $this->entityManager
            ->getRDBRepository('Visit')
            ->where(['deleted' => false])
            ->find();

        foreach ($visits as $visit) {
            $name = (string) $visit->get('name');
            if ($name !== '' && !str_starts_with($name, 'patient')) {
                continue;
            }

            if (!$visit->get('patientId')) {
                continue;
            }

            $patient = $this->entityManager->getEntityById('Patient', (string) $visit->get('patientId'));
            if (!$patient) {
                continue;
            }

            $patientName = $this->buildPatientName($patient);
            if ($patientName === '') {
                continue;
            }

            $date = substr((string) ($visit->get('startedAt') ?: $visit->get('createdAt')), 0, 10);
            $visit->set('name', $patientName . ($date !== '' ? ' — ' . $date : ''));
            $this->entityManager->saveEntity($visit, ['skipHooks' => true]);
            $updated++;
        }

        return $updated;
    }

    private function ensureToothChartNames(): int
    {
        $updated = 0;
        $snapshots = $this->entityManager
            ->getRDBRepository('ToothChartSnapshot')
            ->where(['deleted' => false])
            ->find();

        foreach ($snapshots as $snapshot) {
            $name = (string) $snapshot->get('name');
            if ($name !== '' && !str_contains($name, 'patient')) {
                continue;
            }

            if (!$snapshot->get('visitId')) {
                continue;
            }

            $visit = $this->entityManager->getEntityById('Visit', (string) $snapshot->get('visitId'));
            if (!$visit || !$visit->get('name')) {
                continue;
            }

            $snapshot->set('name', 'Зубная формула — ' . (string) $visit->get('name'));
            $this->entityManager->saveEntity($snapshot, ['skipHooks' => true]);
            $updated++;
        }

        return $updated;
    }

    private function ensureChildFlags(): int
    {
        $updated = 0;
        $today = new DateTimeImmutable('today');
        $patients = $this->entityManager
            ->getRDBRepository('Patient')
            ->where(['deleted' => false])
            ->find();

        foreach ($patients as $patient) {
            if ((bool) $patient->get('isChild')) {
                continue;
            }

            $dateOfBirth = (string) ($patient->get('dateOfBirth') ?? '');
            if ($dateOfBirth === '') {
                continue;
            }

            try {
                $birthDate = new DateTimeImmutable($dateOfBirth);
            } catch (\Throwable) {
                continue;
            }

            if ($birthDate > $today || $birthDate->diff($today)->y > 14) {
                continue;
            }

            $patient->set('isChild', true);
            $this->entityManager->saveEntity($patient, ['skipHooks' => true]);
            $updated++;
        }

        return $updated;
    }

    private function ensurePatientBalances(): int
    {
        $updated = 0;
        $calculator = new PatientBalanceCalculator($this->entityManager);
        $patients = $this->entityManager
            ->getRDBRepository(Patient::ENTITY_TYPE)
            ->where(['deleted' => false])
            ->find();

        /** @var Patient $patient */
        foreach ($patients as $patient) {
            $previous = round((float) ($patient->get('balance') ?? 0.0), 2);
            $calculator->recalculate($patient);
            $next = round((float) ($patient->get('balance') ?? 0.0), 2);

            if ($previous === $next) {
                continue;
            }

            $this->entityManager->saveEntity($patient, ['skipHooks' => true]);
            $updated++;
        }

        return $updated;
    }

    private function buildPatientName(Entity $patient): string
    {
        $parts = array_filter([
            trim((string) $patient->get('lastName')),
            trim((string) $patient->get('firstName')),
            trim((string) $patient->get('middleName')),
        ]);

        $fullName = trim(implode(' ', $parts));

        return $fullName !== '' ? $fullName : trim((string) $patient->get('name'));
    }

    /**
     * @return list<string|stdClass>
     */
    private function tabList(): array
    {
        return [
            $this->divider('ed-clinic', 'Клиника'),
            'PreliminaryPatient',
            'Patient',
            'Appointment',
            'DoctorShiftTemplate',
            'DoctorShift',
            'Visit',
            'HealthQuestionnaire',
            $this->divider('ed-cashdesk', 'Касса'),
            'Invoice',
            'Payment',
            $this->divider('ed-catalogs', 'Прайс и склад'),
            'ServiceCategory',
            'MaterialCategory',
            'StockMovement',
            'LowStockAlert',
            $this->divider('ed-ortho', 'Ортодонтия'),
            'OrthodonticCard',
            $this->divider('ed-management', 'Управление'),
            'Clinic',
            'Cabinet',
            'SalaryProfile',
            'SalaryEntry',
            'SalaryBonus',
            'NotificationLog',
            'User',
            'Team',
        ];
    }

    /**
     * @return list<stdClass>
     */
    private function dashboardLayout(): array
    {
        return [
            (object) [
                'name' => 'Клиника',
                'layout' => [
                    $this->dashlet('ed-resource-calendar', 'ResourceCalendar', 0, 0, 4, 6),
                    $this->dashlet('ed-today', 'TodaysAppointments', 0, 6, 2, 4),
                    $this->dashlet('ed-open-invoices', 'OpenInvoices', 2, 6, 2, 4),
                    $this->dashlet('ed-recent-visits', 'RecentVisits', 0, 10, 2, 4),
                    $this->dashlet('ed-low-stock', 'LowStockMaterials', 2, 10, 2, 4),
                    $this->dashlet('ed-monthly-revenue', 'MonthlyRevenue', 0, 14, 2, 4),
                    $this->dashlet('ed-ortho-cases', 'ActiveOrthoCases', 2, 14, 1, 4),
                    $this->dashlet('ed-payroll', 'PayrollThisMonth', 3, 14, 1, 4),
                ],
            ],
        ];
    }

    private function dashletsOptions(): stdClass
    {
        $options = new stdClass();
        $options->{'ed-resource-calendar'} = (object) [
            'title' => 'Календарь ресурсов',
            'defaultView' => 'day',
            'rowMinutes' => 30,
            'startHour' => 8,
            'endHour' => 21,
            'autorefreshInterval' => 1,
        ];
        $options->{'ed-today'} = (object) ['title' => 'Сегодняшние приёмы', 'displayRecords' => 8, 'autorefreshInterval' => 1];
        $options->{'ed-open-invoices'} = (object) ['title' => 'Открытые счета', 'displayRecords' => 8];
        $options->{'ed-recent-visits'} = (object) ['title' => 'Недавние приёмы', 'displayRecords' => 8];
        $options->{'ed-low-stock'} = (object) ['title' => 'Низкий остаток', 'displayRecords' => 8];
        $options->{'ed-monthly-revenue'} = (object) ['title' => 'Выручка по месяцам', 'monthsBack' => 12];
        $options->{'ed-ortho-cases'} = (object) ['title' => 'Активная ортодонтия', 'displayRecords' => 8];
        $options->{'ed-payroll'} = (object) ['title' => 'ЗП за месяц', 'displayRecords' => 8];

        return $options;
    }

    private function findOneByCode(string $entityType, string $code): ?Entity
    {
        /** @var Entity|null */
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->where(['code' => $code])
            ->findOne();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function setValues(Entity $entity, array $values): void
    {
        foreach ($values as $key => $value) {
            if ($key === 'categoryCode' || $key === 'openingStock') {
                continue;
            }

            $entity->set($key, $value);
        }
    }

    private function divider(string $id, string $text): stdClass
    {
        return (object) [
            'type' => 'divider',
            'id' => $id,
            'text' => $text,
        ];
    }

    private function dashlet(string $id, string $name, int $x, int $y, int $width, int $height): stdClass
    {
        return (object) [
            'id' => $id,
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ];
    }
}
