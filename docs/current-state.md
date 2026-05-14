# EspoDental Current State

Last updated: 2026-05-14

This file is the handoff document for future development sessions. It describes
what has been verified, what exists in metadata, and what still needs product
acceptance.

## 1. Repository And Runtime

- Repository root: `/Users/unet1x/Codex/EspoDental`.
- Local Docker stack: `deploy/local/docker-compose.yml`.
- Local URL: `http://localhost:18080`.
- Local admin credentials used for verification: `admin` /
  `espodental-admin`.
- Target EspoCRM version: 9.2.7.
- Module is mounted into the EspoCRM container, so local source changes can be
  tested with `rebuild.php`.

## 2. Verified Baseline

The following checks were completed on 2026-05-14:

- `php rebuild.php` inside the local EspoCRM container passed.
- `php command.php espo-dental-bootstrap` runs and is idempotent.
- 129 module PHP files passed `php -l`.
- 305 JSON files parsed successfully.
- Docker smoke stack booted and passed `deploy/smoke/smoke.sh`.
- All 31 entity API list endpoints responded successfully during verification.
- `EspoDental/Calendar/appointments` responded with cabinets.
- `EspoDental/Report/monthlyRevenue` responded with monthly data.
- Local UI loaded with Russian EspoDental menu and dashboard.
- Compose config validation passed for local, staging and production templates.
- Phase 1 front-desk API flow passed on the local Docker stack:
  `PreliminaryPatient` -> `Appointment` -> questionnaire token/QR -> public
  questionnaire submit -> automatic `Patient` conversion -> appointment
  re-parenting -> `Start Visit`.
- Direct `POST /Patient` is blocked with `403` for normal API creation.

PHPUnit was not run in the local container because composer/phpunit are not
installed there.

## 3. Existing Entity Scopes

Current module metadata contains:

- `Appointment`
- `AppointmentStatusLog`
- `Cabinet`
- `CephalometricMeasurement`
- `Clinic`
- `HealthQuestionnaire`
- `Invoice`
- `InvoiceLine`
- `LowStockAlert`
- `Material`
- `MaterialCategory`
- `NotificationLog`
- `OrthoPhoto`
- `OrthodonticCard`
- `Patient`
- `Payment`
- `PreliminaryPatient`
- `QuestionnaireToken`
- `SalaryBonus`
- `SalaryEntry`
- `SalaryProfile`
- `Service`
- `ServiceCategory`
- `ServiceMaterial`
- `Settings`
- `StockMovement`
- `ToothChartSnapshot`
- `ToothMovementPlan`
- `TreatmentStage`
- `Visit`
- `VisitPhoto`
- `VisitServiceLine`

## 4. Bootstrap State

`espo-dental-bootstrap` prepares the visible workspace:

- teams and roles;
- one default clinic;
- 5 cabinets;
- Russian starter service categories;
- starter service catalog;
- starter material categories and materials;
- opening stock movements;
- scheduled jobs;
- dashboard template;
- menu, quick-create list, calendar settings and base currency.

This is enough for a first usable workspace, but it is not a full accepted MIS
workflow yet.

## 5. Phase 1 Front Desk Intake

Implemented in branch `feature-front-desk-intake`:

- `Patient` create button is disabled in client metadata.
- `Patient` creation is guarded by a hook and allowed only from the module
  conversion service.
- `PreliminaryPatient` can issue a health-questionnaire QR/token before a
  patient record exists.
- `QuestionnaireToken` and `HealthQuestionnaire` can belong to a
  `PreliminaryPatient` before conversion.
- Public questionnaire submit stores the questionnaire and automatically
  converts the preliminary patient.
- Conversion copies personal fields, initializes patient balance to `0`,
  links questionnaire to the new patient and marks questionnaire state.
- Existing appointments linked to the preliminary patient are re-parented to
  the new patient.
- `Appointment` stores `bookedBy` automatically.
- Booking a preliminary patient moves their status to `booked`.
- Appointment conflict checks now include the patient/preliminary patient, not
  only doctor and cabinet.
- `Start Visit` requires a real `Patient` with completed, non-expired
  questionnaire.

## 6. Known Gaps Against Product Spec

The following requirements still need implementation or explicit verification:

- enforce "no visit without appointment";
- enforce "no invoice without visit";
- implement atomic finish visit action: status update, stock movements, invoice,
  balance update;
- make stock balance movement-based and correction-safe;
- make invoice/payment correction workflows explicit;
- finish health questionnaire QR/tablet/signature/PDF flow;
- show questionnaire expiry alert after 1 year;
- wire visit photos into patient card with visit/date context;
- polish patient tabs: tooth chart, history, questionnaire, files, financials,
  orthodontics, family, CBCT;
- implement pediatric/adult tooth chart display rules;
- complete role-based workspaces for administrator, doctor, assistant and
  manager;
- define and implement MCP/LLM/WhatsApp integration layer.

## 7. Development Rule

Future work should be implemented as connected vertical slices. Do not add
isolated fields or screens unless they participate in the accepted patient flow
or an explicitly planned later slice.
