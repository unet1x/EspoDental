# SimpleStom Patient Workspace

Last updated: 2026-05-26

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
- quick actions limited to open card, book appointment and upload file;
- tabs for `basicData`, `toothChart`, `clinicalHistory`, `files`, `finance`
  and `family`.

Clinical history and financial history stay separate. The clinical tab contains
appointment and visit summary data. The finance tab contains invoice/payment
summary data. The workspace can link back to the full EspoCRM patient record
when deeper editing is needed.

## Acceptance Notes

- The endpoint returns only summary data required by the workspace shell.
- The dashlet uses the scoped SimpleStom UI kit from `simple-stom-ui.js`.
- The stage deliberately avoids rewriting
  `views/patient/record/detail.js`; later questionnaire, tooth-chart and file
  stages can improve the existing full record panels independently.
