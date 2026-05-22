<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase19ClinicalUxSimplificationTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testCatalogTabsExposeCategoriesOnly(): void
    {
        $seeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $serviceCategoryRelationships = $this->readJson(
            self::MODULE_ROOT . '/Resources/layouts/ServiceCategory/relationships.json'
        );
        $materialCategoryRelationships = $this->readJson(
            self::MODULE_ROOT . '/Resources/layouts/MaterialCategory/relationships.json'
        );

        $tabListStart = strpos($seeder, 'private function tabList(): array');
        $this->assertIsInt($tabListStart);
        $tabListCode = substr($seeder, $tabListStart);

        $this->assertStringContainsString("'ServiceCategory'", $tabListCode);
        $this->assertStringContainsString("'MaterialCategory'", $tabListCode);
        $this->assertStringNotContainsString("'Service',", $tabListCode);
        $this->assertStringNotContainsString("'Material',", $tabListCode);
        $this->assertStringContainsString('ensureClinicalLineNames', $seeder);
        $this->assertStringContainsString('ensureVisitNames', $seeder);
        $this->assertStringContainsString('ensureToothChartNames', $seeder);
        $this->assertSame(['services'], $serviceCategoryRelationships);
        $this->assertSame(['materials'], $materialCategoryRelationships);
        $this->assertSame(['serviceMaterials'], $this->readJson(
            self::MODULE_ROOT . '/Resources/layouts/Service/relationships.json'
        ));
    }

    public function testAppointmentQuickFormAndSystemStatusGuard(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json');
        $detail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Appointment/detail.json');
        $detailSmall = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Appointment/detailSmall.json');
        $editSmall = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Appointment/editSmall.json');
        $filters = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Appointment/filters.json');
        $hook = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/Appointment/FrontDeskFlow.php');
        $appointmentService = (string) file_get_contents(self::MODULE_ROOT . '/Services/AppointmentService.php');
        $visitService = (string) file_get_contents(self::MODULE_ROOT . '/Services/VisitService.php');
        $appointmentClient = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/views/appointment/fields/date-start-slot.js'
        );
        $defaultClinicClient = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/views/appointment/fields/default-clinic.js'
        );
        $appointmentClientDefs = $this->readJson(
            self::MODULE_ROOT . '/Resources/metadata/clientDefs/Appointment.json'
        );

        $encodedDetail = json_encode($detail, JSON_THROW_ON_ERROR);
        $encodedSmall = json_encode([$detailSmall, $editSmall], JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('source', $def['fields']);
        $this->assertArrayHasKey('confirmed', $def['fields']);
        $this->assertSame(
            'espo-dental:views/appointment/fields/date-start-slot',
            $def['fields']['dateStart']['view']
        );
        $this->assertSame(
            'espo-dental:views/appointment/fields/default-clinic',
            $def['fields']['clinic']['view']
        );
        $this->assertFalse($def['fields']['dateEnd']['required']);
        $this->assertStringNotContainsString('source', $encodedDetail);
        $this->assertStringNotContainsString('dateEnd', $encodedDetail);
        $this->assertStringNotContainsString('assistant', $encodedDetail);
        $this->assertStringNotContainsString('assignedUser', $encodedDetail);
        $this->assertStringNotContainsString('teams', $encodedDetail);
        $this->assertStringNotContainsString('parent', $encodedSmall);
        $this->assertStringNotContainsString('status', $encodedSmall);
        $this->assertStringNotContainsString('assistant', $encodedSmall);
        $this->assertStringContainsString('confirmed', $encodedSmall);
        $this->assertNotContains('source', $filters);
        $this->assertSame(
            'espo-dental:views/appointment/modals/edit',
            $appointmentClientDefs['modalViews']['edit']
        );
        $this->assertFileExists(self::CLIENT_ROOT . '/src/views/appointment/modals/edit.js');
        $appointmentModal = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/views/appointment/modals/edit.js'
        );
        $this->assertStringContainsString('fullFormDisabled: true', $appointmentModal);
        $this->assertStringContainsString('sideDisabled: true', $appointmentModal);

        $this->assertStringContainsString('public static int $order = 1', $hook);
        $this->assertStringContainsString('applyDateEndFromDuration', $hook);
        $this->assertStringContainsString('formatClinicDateTime', $hook);
        $this->assertStringContainsString('resolveTimeZone', $hook);
        $this->assertStringContainsString('espoDentalDefaultClinicId', $hook);
        $this->assertStringContainsString('Appointment status is controlled by visit workflow', $hook);
        $this->assertStringContainsString('espodentalAllowAppointmentSystemStatus', $appointmentService);
        $this->assertStringContainsString('espodentalAllowAppointmentSystemStatus', $visitService);
        $this->assertStringContainsString('EspoDental/Calendar/freeSlots', $appointmentClient);
        $this->assertStringContainsString('excludeAppointmentId', $appointmentClient);
        $this->assertStringContainsString('parentType', $appointmentClient);
        $this->assertStringContainsString('parentId', $appointmentClient);
        $this->assertStringContainsString('hideNativeDateTimeControl', $appointmentClient);
        $this->assertStringContainsString('getDurationSecondsFromField', $appointmentClient);
        $this->assertStringContainsString('bindDurationFieldWatcher', $appointmentClient);
        $this->assertStringContainsString('localStart', $appointmentClient);
        $this->assertStringContainsString('espoDentalSelectedSlotClinicTime', $appointmentClient);
        $this->assertStringContainsString('data[this.name] = this.selectedSlotStart', $appointmentClient);
        $this->assertStringContainsString('applyDefaultClinic', $defaultClinicClient);
        $this->assertStringContainsString('espoDentalDefaultClinicId', $defaultClinicClient);
        $this->assertStringContainsString("Espo.Ajax.getRequest('Clinic'", $defaultClinicClient);
    }

    public function testPatientHasBookAppointmentAction(): void
    {
        $patientClient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $buttons = $patientClient['menu']['detail']['buttons'];
        $bookButton = null;

        $patientRelationships = $this->readJson(
            self::MODULE_ROOT . '/Resources/layouts/Patient/relationships.json'
        );
        $appointmentsPanel = $patientRelationships[0];

        $this->assertSame('appointments', $appointmentsPanel['name']);
        $this->assertTrue($appointmentsPanel['createDisabled']);
        $this->assertTrue($appointmentsPanel['selectDisabled']);
        $this->assertFalse($patientClient['relationshipPanels']['appointments']['create']);
        $this->assertFalse($patientClient['relationshipPanels']['appointments']['select']);
        $this->assertTrue($patientClient['relationshipPanels']['appointments']['createDisabled']);
        $this->assertTrue($patientClient['relationshipPanels']['appointments']['selectDisabled']);

        foreach ($buttons as $button) {
            if (($button['name'] ?? null) === 'bookAppointment') {
                $bookButton = $button;
                break;
            }
        }

        $this->assertIsArray($bookButton);
        $this->assertSame('Book Appointment', $bookButton['label']);
        $this->assertSame('espo-dental:handlers/patient/book-appointment', $bookButton['handler']);
        $this->assertSame('actionBookAppointment', $bookButton['actionFunction']);
        $this->assertFileExists(self::CLIENT_ROOT . '/src/handlers/patient/book-appointment.js');

        $handler = (string) file_get_contents(self::CLIENT_ROOT . '/src/handlers/patient/book-appointment.js');
        $this->assertStringContainsString('helpers/record-modal', $handler);
        $this->assertStringContainsString("entityType: 'Appointment'", $handler);
        $this->assertStringContainsString("parentType: 'Patient'", $handler);
        $this->assertStringContainsString('fullFormDisabled: true', $handler);
        $this->assertStringContainsString("link: 'parent'", $handler);
        $this->assertStringContainsString('Appointment booked for', $handler);
    }

    public function testVisitIsClinicalWorkspaceNotCashdesk(): void
    {
        $detail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Visit/detail.json');
        $relationships = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Visit/relationships.json');
        $scope = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/scopes/Visit.json');
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Visit.json');
        $visitService = (string) file_get_contents(self::MODULE_ROOT . '/Services/VisitService.php');
        $appointmentService = (string) file_get_contents(self::MODULE_ROOT . '/Services/AppointmentService.php');

        $encodedDetail = json_encode($detail, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('status', $encodedDetail);
        $this->assertStringNotContainsString('appointment', $encodedDetail);
        $this->assertFalse($scope['stream']);
        $this->assertNotContains('invoices', $relationships);
        $this->assertSame(
            'espo-dental:views/visit/record/detail',
            $clientDefs['recordViews']['detail']
        );
        $this->assertFileExists(self::CLIENT_ROOT . '/src/views/visit/record/detail.js');
        $this->assertStringContainsString('getActionToothChart', (string) file_get_contents(
            self::MODULE_ROOT . '/Controllers/Visit.php'
        ));
        $this->assertStringContainsString('getToothChartData', $visitService);
        $this->assertStringContainsString('ToothChartSnapshot::ENTITY_TYPE', $appointmentService);
    }

    public function testServiceAndMaterialLinesAreDoctorFriendlyAndGuarded(): void
    {
        $serviceLineDef = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitServiceLine.json');
        $materialLineDef = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitMaterialLine.json');
        $serviceLineDetail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitServiceLine/detail.json');
        $serviceLineList = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitServiceLine/list.json');
        $serviceLineListSmall = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitServiceLine/listSmall.json');
        $materialLineDetail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitMaterialLine/detail.json');
        $materialLineList = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitMaterialLine/list.json');
        $materialLineListSmall = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitMaterialLine/listSmall.json');
        $serviceLineClient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/VisitServiceLine.json');

        $encodedServiceDetail = json_encode($serviceLineDetail, JSON_THROW_ON_ERROR);
        $encodedServiceList = json_encode($serviceLineList, JSON_THROW_ON_ERROR);
        $encodedMaterialDetail = json_encode($materialLineDetail, JSON_THROW_ON_ERROR);
        $encodedMaterialList = json_encode($materialLineList, JSON_THROW_ON_ERROR);

        $this->assertTrue($serviceLineDef['fields']['unitPrice']['readOnly']);
        $this->assertTrue($serviceLineDef['fields']['vatRate']['readOnly']);
        $this->assertTrue($materialLineDef['fields']['unitPrice']['readOnly']);
        $this->assertFalse($materialLineDef['fields']['visitServiceLine']['required']);
        $this->assertStringNotContainsString('unitPrice', $encodedServiceDetail);
        $this->assertStringNotContainsString('teethNumbers', $encodedServiceDetail);
        $this->assertStringNotContainsString('unitPrice', $encodedServiceList);
        $this->assertSame('service', $serviceLineListSmall[0]['name']);
        $this->assertStringNotContainsString('unitPrice', $encodedMaterialDetail);
        $this->assertStringNotContainsString('totalCost', $encodedMaterialDetail);
        $this->assertStringNotContainsString('totalCost', $encodedMaterialList);
        $this->assertSame('material', $materialLineListSmall[0]['name']);
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/VisitServiceLine/GuardActiveVisit.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/VisitMaterialLine/GuardActiveVisit.php');
        $this->assertSame(
            'espo-dental:views/visit-service-line/record/edit',
            $serviceLineClient['recordViews']['edit']
        );
        $this->assertFileExists(self::CLIENT_ROOT . '/src/views/visit-service-line/record/edit.js');
        $serviceLineEditView = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/views/visit-service-line/record/edit.js'
        );
        $this->assertStringContainsString('ServiceCategory', $serviceLineEditView);
        $this->assertStringContainsString('serviceCatalogTree', $serviceLineEditView);
        $this->assertStringContainsString('serviceCategoryToggle', $serviceLineEditView);
        $this->assertStringContainsString('serviceCatalogItem', $serviceLineEditView);
        $this->assertStringContainsString('maxSize: 200', $serviceLineEditView);
    }

    public function testVisitPhotoQuickAddGetsDefaults(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitPhoto.json');
        $detailSmall = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitPhoto/detailSmall.json');
        $editSmall = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/VisitPhoto/editSmall.json');
        $encodedSmall = json_encode([$detailSmall, $editSmall], JSON_THROW_ON_ERROR);

        $this->assertFalse($def['fields']['name']['required']);
        $this->assertTrue($def['fields']['name']['readOnly']);
        $this->assertStringNotContainsString('recordedAt', $encodedSmall);
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/VisitPhoto/Defaults.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Resources/layouts/VisitPhoto/detailSmall.json');
        $this->assertFileExists(self::MODULE_ROOT . '/Resources/layouts/VisitPhoto/editSmall.json');
    }

    public function testPatientDeletionIsBlockedAndChildParentFlowIsModeled(): void
    {
        $patientDef = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $patientClient = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Patient.json');
        $preliminaryClient = $this->readJson(
            self::MODULE_ROOT . '/Resources/metadata/clientDefs/PreliminaryPatient.json'
        );
        $conversion = (string) file_get_contents(self::MODULE_ROOT . '/Services/PreliminaryPatientConversion.php');
        $childDefaults = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/Patient/ChildDefaults.php');
        $reminders = (string) file_get_contents(self::MODULE_ROOT . '/Services/ReminderService.php');
        $seeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        $this->assertTrue($patientClient['removeDisabled']);
        $this->assertTrue($preliminaryClient['removeDisabled']);
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/Patient/PreventManualRemove.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/PreliminaryPatient/PreventManualRemove.php');
        $this->assertStringContainsString('espodentalAllowPreliminaryPatientRemove', $conversion);

        $this->assertArrayHasKey('parentPatient', $patientDef['fields']);
        $this->assertSame('Patient', $patientDef['links']['parentPatient']['entity']);
        $this->assertSame('Patient', $patientDef['links']['childPatients']['entity']);
        $this->assertStringContainsString('$age <= 14', $childDefaults);
        $this->assertStringContainsString('parentPatientId', $childDefaults);
        $this->assertStringContainsString('ensureChildFlags', $seeder);
        $this->assertStringContainsString('resolveRecipientSource', $reminders);
        $this->assertStringContainsString('getParentPatientId', $reminders);
    }

    public function testToothChartIsSurfaceEditableAndMixedDentitionRendersBothCharts(): void
    {
        $renderer = (string) file_get_contents(self::CLIENT_ROOT . '/src/tooth-chart/renderer.js');
        $detailView = (string) file_get_contents(
            self::CLIENT_ROOT . '/src/views/tooth-chart-snapshot/record/detail.js'
        );

        $this->assertStringContainsString("key: 'o'", $renderer);
        $this->assertStringContainsString("key: 'm'", $renderer);
        $this->assertStringContainsString("key: 'd'", $renderer);
        $this->assertStringContainsString("key: 'b'", $renderer);
        $this->assertStringContainsString("key: 'l'", $renderer);
        $this->assertStringContainsString("dentition === 'mixed'", $renderer);
        $this->assertStringContainsString('ADULT_TOP', $renderer);
        $this->assertStringContainsString('CHILD_TOP', $renderer);
        $this->assertStringContainsString('getConditionItems', $renderer);
        $this->assertStringContainsString('getSurfaceItems', $renderer);
        $this->assertStringContainsString('espoDentalToothChartConditions', $detailView);
        $this->assertStringContainsString('espoDentalToothChartSurfaces', $detailView);
        $this->assertStringContainsString("this.type !== 'edit'", $detailView);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: {$path}");

        return $data;
    }
}
