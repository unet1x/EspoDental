<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Role;
use Espo\Entities\Team;

class AfterInstall
{
    private const TEAMS = [
        'EspoDental Doctors',
        'EspoDental Assistants',
        'EspoDental Administrators',
        'EspoDental Managers',
        'EspoDental Stock Managers',
    ];

    private const SCOPES = [
        'Clinic',
        'Cabinet',
        'PreliminaryPatient',
        'Patient',
        'Appointment',
        'AppointmentStatusLog',
        'Visit',
        'ServiceCategory',
        'Service',
        'VisitServiceLine',
        'ToothChartSnapshot',
        'VisitPhoto',
        'Invoice',
        'InvoiceLine',
        'Payment',
        'MaterialCategory',
        'Material',
        'StockMovement',
        'ServiceMaterial',
        'LowStockAlert',
        'NotificationLog',
        'SalaryProfile',
        'SalaryEntry',
        'SalaryBonus',
        'OrthodonticCard',
        'TreatmentStage',
        'ToothMovementPlan',
        'OrthoPhoto',
        'CephalometricMeasurement',
    ];

    private const ROLES = [
        'EspoDental Manager' => [
            'description' => 'Full read/write within the clinic team. Sees finance.',
            'data' => [
                'Clinic'               => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'Cabinet'              => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'PreliminaryPatient'   => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'Patient'              => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'Appointment'          => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'AppointmentStatusLog' => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Visit'                => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'ServiceCategory'      => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'Service'              => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'VisitServiceLine'     => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'ToothChartSnapshot'   => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'VisitPhoto'           => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'Invoice'              => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'InvoiceLine'          => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'Payment'              => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'MaterialCategory'     => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'Material'             => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'StockMovement'        => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'ServiceMaterial'      => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'LowStockAlert'        => ['create' => 'no',  'read' => 'all',  'edit' => 'all',  'delete' => 'no',   'stream' => 'all'],
                'NotificationLog'      => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'SalaryProfile'        => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'SalaryEntry'          => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'SalaryBonus'          => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'OrthodonticCard'      => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'TreatmentStage'       => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'ToothMovementPlan'    => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'OrthoPhoto'           => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'CephalometricMeasurement' => ['create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all', 'stream' => 'no'],
            ],
        ],
        'EspoDental Doctor' => [
            'description' => 'Sees own appointments and patients; no global finance.',
            'data' => [
                'Clinic'               => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'Cabinet'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'PreliminaryPatient'   => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'team'],
                'Patient'              => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'team'],
                'Appointment'          => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'team'],
                'AppointmentStatusLog' => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Visit'                => ['create' => 'yes', 'read' => 'team', 'edit' => 'own',  'delete' => 'no',   'stream' => 'team'],
                'ServiceCategory'      => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Service'              => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'VisitServiceLine'     => ['create' => 'yes', 'read' => 'team', 'edit' => 'own',  'delete' => 'own',  'stream' => 'no'],
                'ToothChartSnapshot'   => ['create' => 'yes', 'read' => 'team', 'edit' => 'own',  'delete' => 'no',   'stream' => 'no'],
                'VisitPhoto'           => ['create' => 'yes', 'read' => 'team', 'edit' => 'own',  'delete' => 'own',  'stream' => 'no'],
                'Invoice'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'InvoiceLine'          => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Payment'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'MaterialCategory'     => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Material'             => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'StockMovement'        => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ServiceMaterial'      => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'LowStockAlert'        => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'NotificationLog'      => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'SalaryProfile'        => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'SalaryEntry'          => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'own'],
                'SalaryBonus'          => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'OrthodonticCard'      => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'team'],
                'TreatmentStage'       => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'team', 'stream' => 'no'],
                'ToothMovementPlan'    => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'team', 'stream' => 'no'],
                'OrthoPhoto'           => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'team', 'stream' => 'no'],
                'CephalometricMeasurement' => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'team', 'stream' => 'no'],
            ],
        ],
        'EspoDental Assistant' => [
            'description' => 'Read-only on patients, no visit completion.',
            'data' => [
                'Clinic'               => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'Cabinet'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'PreliminaryPatient'   => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'Patient'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'Appointment'          => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'AppointmentStatusLog' => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Visit'                => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'ServiceCategory'      => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Service'              => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'VisitServiceLine'     => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ToothChartSnapshot'   => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'VisitPhoto'           => ['create' => 'yes', 'read' => 'team', 'edit' => 'own',  'delete' => 'own',  'stream' => 'no'],
                'Invoice'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'InvoiceLine'          => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Payment'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'MaterialCategory'     => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Material'             => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'StockMovement'        => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ServiceMaterial'      => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'LowStockAlert'        => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'NotificationLog'      => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'SalaryProfile'        => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'SalaryEntry'          => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'own'],
                'SalaryBonus'          => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'OrthodonticCard'      => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'TreatmentStage'       => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ToothMovementPlan'    => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'OrthoPhoto'           => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'no'],
                'CephalometricMeasurement' => ['create' => 'no', 'read' => 'team', 'edit' => 'no', 'delete' => 'no',   'stream' => 'no'],
            ],
        ],
        'EspoDental Administrator' => [
            'description' => 'Front-desk: leads, patients, appointments, invoices.',
            'data' => [
                'Clinic'               => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'Cabinet'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'PreliminaryPatient'   => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'team', 'stream' => 'team'],
                'Patient'              => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'team'],
                'Appointment'          => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'team', 'stream' => 'team'],
                'AppointmentStatusLog' => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Visit'                => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'ServiceCategory'      => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Service'              => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'VisitServiceLine'     => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ToothChartSnapshot'   => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'VisitPhoto'           => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Invoice'              => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'team'],
                'InvoiceLine'          => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'team', 'stream' => 'no'],
                'Payment'              => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'team'],
                'MaterialCategory'     => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Material'             => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'StockMovement'        => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ServiceMaterial'      => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'LowStockAlert'        => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'NotificationLog'      => ['create' => 'yes', 'read' => 'team', 'edit' => 'team', 'delete' => 'no',   'stream' => 'no'],
                'SalaryProfile'        => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'no',   'stream' => 'all'],
                'SalaryEntry'          => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'no',   'stream' => 'all'],
                'SalaryBonus'          => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'OrthodonticCard'      => ['create' => 'no',  'read' => 'all',  'edit' => 'team', 'delete' => 'no',   'stream' => 'all'],
                'TreatmentStage'       => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ToothMovementPlan'    => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'OrthoPhoto'           => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'CephalometricMeasurement' => ['create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no',   'stream' => 'no'],
            ],
        ],
        'EspoDental Stock Manager' => [
            'description' => 'Inventory and threshold alerts.',
            'data' => [
                'Clinic'               => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'Cabinet'              => ['create' => 'no',  'read' => 'team', 'edit' => 'no',   'delete' => 'no',   'stream' => 'team'],
                'PreliminaryPatient'   => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Patient'              => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Appointment'          => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'AppointmentStatusLog' => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Visit'                => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ServiceCategory'      => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Service'              => ['create' => 'no',  'read' => 'all',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'VisitServiceLine'     => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ToothChartSnapshot'   => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'VisitPhoto'           => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Invoice'              => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'InvoiceLine'          => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'Payment'              => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'MaterialCategory'     => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'Material'             => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'all'],
                'StockMovement'        => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'ServiceMaterial'      => ['create' => 'yes', 'read' => 'all',  'edit' => 'all',  'delete' => 'all',  'stream' => 'no'],
                'LowStockAlert'        => ['create' => 'no',  'read' => 'all',  'edit' => 'all',  'delete' => 'no',   'stream' => 'all'],
                'NotificationLog'      => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'SalaryProfile'        => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'SalaryEntry'          => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'own'],
                'SalaryBonus'          => ['create' => 'no',  'read' => 'own',  'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'OrthodonticCard'      => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'TreatmentStage'       => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'ToothMovementPlan'    => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'OrthoPhoto'           => ['create' => 'no',  'read' => 'no',   'edit' => 'no',   'delete' => 'no',   'stream' => 'no'],
                'CephalometricMeasurement' => ['create' => 'no', 'read' => 'no', 'edit' => 'no',  'delete' => 'no',   'stream' => 'no'],
            ],
        ],
    ];

    private const SERVICE_CATEGORIES = [
        ['name' => 'Therapy',       'code' => 'THE', 'order' => 10, 'color' => '#1F77B4'],
        ['name' => 'Surgery',       'code' => 'SUR', 'order' => 20, 'color' => '#D62728'],
        ['name' => 'Orthopedics',   'code' => 'ORP', 'order' => 30, 'color' => '#FF7F0E'],
        ['name' => 'Orthodontics',  'code' => 'ORD', 'order' => 40, 'color' => '#2CA02C'],
        ['name' => 'Hygiene',       'code' => 'HYG', 'order' => 50, 'color' => '#17BECF'],
        ['name' => 'Diagnostics',   'code' => 'DIA', 'order' => 60, 'color' => '#9467BD'],
        ['name' => 'Implantology',  'code' => 'IMP', 'order' => 70, 'color' => '#8C564B'],
        ['name' => 'Pediatric',     'code' => 'PED', 'order' => 80, 'color' => '#E377C2'],
    ];

    public function run(Container $container): void
    {
        /** @var EntityManager $em */
        $em = $container->getByClass(EntityManager::class);

        $this->ensureTeams($em);
        $this->ensureRoles($em);
        $this->ensureServiceCategories($em);
    }

    private function ensureServiceCategories(EntityManager $em): void
    {
        foreach (self::SERVICE_CATEGORIES as $cfg) {
            $existing = $em->getRDBRepository('ServiceCategory')
                ->where(['code' => $cfg['code']])
                ->findOne();
            if ($existing) {
                continue;
            }
            $cat = $em->getRDBRepository('ServiceCategory')->getNew();
            foreach ($cfg as $k => $v) {
                $cat->set($k, $v);
            }
            $cat->set('isActive', true);
            $em->saveEntity($cat);
        }
    }

    private function ensureTeams(EntityManager $em): void
    {
        foreach (self::TEAMS as $teamName) {
            $existing = $em->getRDBRepository(Team::ENTITY_TYPE)
                ->where(['name' => $teamName])
                ->findOne();

            if ($existing) {
                continue;
            }

            $team = $em->getRDBRepository(Team::ENTITY_TYPE)->getNew();
            $team->set('name', $teamName);
            $em->saveEntity($team);
        }
    }

    private function ensureRoles(EntityManager $em): void
    {
        foreach (self::ROLES as $roleName => $cfg) {
            $existing = $em->getRDBRepository(Role::ENTITY_TYPE)
                ->where(['name' => $roleName])
                ->findOne();

            if ($existing) {
                continue;
            }

            $role = $em->getRDBRepository(Role::ENTITY_TYPE)->getNew();
            $role->set('name', $roleName);
            $role->set('description', $cfg['description']);
            $role->set('data', (object) $cfg['data']);
            $role->set('fieldData', (object) []);

            $em->saveEntity($role);
        }
    }
}
