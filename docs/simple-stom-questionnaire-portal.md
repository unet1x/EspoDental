# SimpleStom Questionnaire And Patient Portal

Last updated: 2026-05-26

Stage 7 brings the SimpleStom questionnaire and patient portal contract into
EspoDental without replacing the existing questionnaire flow.

## Questionnaire Contract

- Public questionnaire entrypoint:
  `/?entryPoint=healthQuestionnaire&token=...`
- Submit endpoint:
  `POST /EspoDental/Public/HealthQuestionnaire/{token}/submit`
- Schema provider:
  `QuestionnaireSchemaProvider`
- Public renderer:
  `HealthQuestionnaireRenderer`
- PDF builder:
  `QuestionnairePdfBuilder`

The questionnaire keeps RU/EN/ES UI support and now sends all language schemas
to the public page so patients can switch language before saving without losing
answers. The schema records `templateType` (`adult` or `child`), version and
`pdfLanguageMode`. Child mode is selected from `Patient.isChild` or age under
18.

The generated questionnaire PDF is always ES/RU. UI language can be RU, EN or
ES, but the stored PDF uses bilingual Spanish/Russian labels and includes the
signature image with the signature date outside the image frame.

## Patient Portal Contract

- Public portal entrypoint:
  `/?entryPoint=patientPortal`
- Request OTP:
  `POST /EspoDental/Public/PatientPortal/requestCode`
- Verify OTP:
  `POST /EspoDental/Public/PatientPortal/verifyCode`
- Future appointments:
  `GET /EspoDental/Public/PatientPortal/appointments`
- Create reschedule request:
  `POST /EspoDental/Public/PatientPortal/rescheduleRequests`
- Cancel reschedule request:
  `POST /EspoDental/Public/PatientPortal/rescheduleRequests/{id}/cancel`
- Logout:
  `POST /EspoDental/Public/PatientPortal/logout`

Portal access uses `PatientPortalSession`. OTP values and access tokens are
stored only as hashes. Public calls after verification use
`X-Patient-Portal-Token` or `Authorization: Bearer ...`.

The appointments endpoint returns only a safe future-appointment view and
does not expose occupied slots:
appointment id, start/end time, status, doctor display name, clinic name,
cabinet name and active reschedule request status. It also omits other
patients, internal notes, files, clinical records, financial data or past visits.

Reschedule requests are stored in `AppointmentRescheduleRequest`. A patient
request does not move the appointment directly; clinic staff must review and
confirm it later. Portal actions are logged in `PatientPortalEvent` without
storing OTP codes or raw portal tokens.
