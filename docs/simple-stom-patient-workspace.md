# SimpleStom Patient Workspace

Last updated: 2026-05-27

Stage 6 adds the first pass of the SimpleStom patient workspace from
`17-feedback-patients.png`.

## Runtime Pieces

- Workspace endpoint:
  `GET /EspoDental/Patient/workspace`
- Service:
  `PatientWorkspaceService::getWorkspace`
- Dashlet metadata:
  `Resources/metadata/dashlets/PatientWorkspace.json`
- Client view:
  `src/files/client/custom/modules/espo-dental/src/views/dashlets/patient-workspace.js`
- Role placement:
  clinic, admin, doctor and assistant dashboard templates in `WorkspaceSeeder`.

## Workspace Contract

The workspace is a separate operational surface, not a wholesale rewrite of the
existing `Patient` detail view. It gives reception, doctors and assistants a
SimpleStom-style split screen:

- searchable patient list on the left;
- compact selected patient card on the right;
- richer list rows with age, preferred communication channel, nearest
  appointment and operational alert badges;
- quick actions limited to open card, book appointment and upload file;
- tabs for `basicData`, `toothChart`, `clinicalHistory`, `files`, `finance`
  and `family`.

Clinical history and financial history stay separate. The clinical tab contains
appointment and visit summary data. The finance tab contains invoice/payment
summary data. The workspace can link back to the full EspoCRM patient record
when deeper editing is needed.

## Stage D Enrichment Pass

The 2026-05-27 enrichment pass keeps the same endpoint and dashlet but adds the
missing receptionist summary data:

- `PatientWorkspaceService` calculates age from `dateOfBirth`;
- patient rows and the selected card expose `preferredChannel`;
- VIP, restrictions, questionnaire alerts, expired questionnaire, debt/credit
  and child status are returned as alert badges;
- the next active future appointment is returned as `nextAppointment` and shown
  in the list, compact card and clinical-history tab;
- finance summary includes `openInvoiceBalance` in addition to open invoice and
  payment counts;
- clinical-history, files and finance tabs now include direct source-record
  links: recent `Appointment` and `Visit` rows, recent `HealthQuestionnaire`
  rows, open `Invoice` rows and recent `Payment` rows.

Browser smoke confirmed the dashboard patient workspace renders the new channel,
nearest appointment and alert markers after `update-app-timestamp`. A second
smoke pass on the demo dashboard selected the seeded patient workspace and
confirmed the clinical tab renders appointment and visit links, while the
finance tab renders payment links and keeps the open-invoice section separate.

## Acceptance Notes

- The endpoint returns only summary data required by the workspace shell.
- The dashlet uses the scoped SimpleStom UI kit from `simple-stom-ui.js`.
- The stage deliberately avoids rewriting
  `views/patient/record/detail.js`; later questionnaire, tooth-chart and file
  stages can improve the existing full record panels independently.
