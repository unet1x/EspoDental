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

## 5. Known Gaps Against Product Spec

The following requirements still need implementation or explicit verification:

- block normal direct `Patient` creation;
- implement/verify preliminary patient to patient conversion as the only normal
  patient creation path;
- create patient balance ledger automatically at conversion;
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

## 6. Development Rule

Future work should be implemented as connected vertical slices. Do not add
isolated fields or screens unless they participate in the accepted patient flow
or an explicitly planned later slice.
